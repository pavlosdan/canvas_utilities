<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Import;

use Drupal\canvas_utilities_icons\Entity\IconLibrary;
use Drupal\canvas_utilities_icons\Plugin\IconSourceManager;
use Drupal\canvas_utilities_icons\Plugin\IconSource\IconSourceInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/**
 * Imports an icon source transactionally into sanitized managed files.
 */
final class IconImporter {

  /**
   * The managed-file destination for sanitized icon payloads.
   */
  private const DESTINATION = 'public://canvas-utilities/icons';

  /**
   * Constructs an icon importer.
   */
  public function __construct(
    private readonly IconSourceManager $sourceManager,
    private readonly SvgSanitizer $sanitizer,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileUsageInterface $fileUsage,
    private readonly FileSystemInterface $fileSystem,
    private readonly Connection $connection,
  ) {}

  /**
   * Imports one library.
   *
   * @param string $theme
   *   Theme machine name.
   * @param string $provider
   *   Icon source plugin ID.
   * @param \Symfony\Component\HttpFoundation\File\UploadedFile[] $uploads
   *   Uploaded source files.
   * @param array<string, mixed> $metadata
   *   Normalized request fields.
   */
  public function import(string $theme, string $provider, array $uploads, array $metadata): IconLibrary {
    $short_id = $this->machineName((string) ($metadata['id'] ?? $metadata['label'] ?? ''));
    if ($short_id === '') {
      throw new \InvalidArgumentException('A valid library ID is required.');
    }
    $storage = $this->entityTypeManager->getStorage('canvas_utilities_icon_lib');
    $entity_id = $theme . '__' . $short_id;
    if ($storage->load($entity_id) !== NULL) {
      throw new \InvalidArgumentException('An icon library with this ID already exists.');
    }

    $prepared = $this->prepareCandidates($provider, $uploads, $short_id);
    if ($prepared === []) {
      throw new \InvalidArgumentException('The source contained no importable SVG icons.');
    }

    $transaction = $this->connection->startTransaction();
    $written = [];
    try {
      $this->prepareDestination();
      $icons = [];
      foreach ($prepared as $id => $record) {
        $file = $this->store($record);
        $written[] = $file;
        $icons[$id] = $this->iconRecord($record, $file);
      }
      $entity = $storage->create([
        'id' => $entity_id,
        'label' => trim((string) ($metadata['label'] ?? $short_id)),
        'status' => TRUE,
        'theme' => $theme,
        'provider' => $provider,
        'prefix' => $this->machineName((string) ($metadata['prefix'] ?? $short_id)),
        'license' => trim((string) ($metadata['license'] ?? '')),
        'source' => trim((string) ($metadata['source'] ?? '')),
        'icons' => $icons,
      ]);
      assert($entity instanceof IconLibrary);
      $entity->save();
      foreach ($written as $file) {
        $this->fileUsage->add($file, 'canvas_utilities_icons', 'canvas_utilities_icon_lib', $entity->id());
      }
      return $entity;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      foreach ($written as $file) {
        $file->delete();
      }
      throw $exception;
    }
  }

  /**
   * Adds icons from a new upload to an existing library.
   *
   * Icons whose ID and content already match are left untouched, so uploading
   * the same file twice is a no-op. An icon whose ID matches but whose content
   * differs replaces the stored icon and its managed file.
   *
   * @param \Drupal\canvas_utilities_icons\Entity\IconLibrary $library
   *   The library to extend.
   * @param string $provider
   *   Icon source plugin ID for this upload.
   * @param \Symfony\Component\HttpFoundation\File\UploadedFile[] $uploads
   *   Uploaded source files.
   *
   * @return array{added: string[], replaced: string[], unchanged: string[]}
   *   The IDs of the icons in each outcome group.
   */
  public function addIcons(IconLibrary $library, string $provider, array $uploads): array {
    $prepared = $this->prepareCandidates($provider, $uploads, $library->getShortId());
    if ($prepared === []) {
      throw new \InvalidArgumentException('The source contained no importable SVG icons.');
    }

    $icons = $library->getIcons();
    $report = ['added' => [], 'replaced' => [], 'unchanged' => []];
    foreach ($prepared as $id => $record) {
      if (!isset($icons[$id])) {
        $report['added'][] = $id;
      }
      elseif ($icons[$id]['hash'] !== $record['hash']) {
        $report['replaced'][] = $id;
      }
      else {
        $report['unchanged'][] = $id;
      }
    }
    if ($report['added'] === [] && $report['replaced'] === []) {
      return $report;
    }

    $transaction = $this->connection->startTransaction();
    $written = [];
    $superseded = [];
    try {
      $this->prepareDestination();
      foreach ([...$report['added'], ...$report['replaced']] as $id) {
        if (isset($icons[$id])) {
          $superseded[] = $icons[$id]['file_uuid'];
        }
        $file = $this->store($prepared[$id]);
        $written[] = $file;
        $icons[$id] = $this->iconRecord($prepared[$id], $file);
      }
      $library->set('icons', $icons);
      $library->save();
      foreach ($written as $file) {
        $this->fileUsage->add($file, 'canvas_utilities_icons', 'canvas_utilities_icon_lib', $library->id());
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      foreach ($written as $file) {
        $file->delete();
      }
      throw $exception;
    }

    // The library no longer points at the replaced payloads, so releasing them
    // cannot orphan the saved configuration.
    $this->releaseFiles($superseded, $library->id());
    return $report;
  }

  /**
   * Removes one icon and its managed file from a library.
   *
   * @param \Drupal\canvas_utilities_icons\Entity\IconLibrary $library
   *   The library to edit.
   * @param string $icon_id
   *   The icon ID to remove.
   */
  public function removeIcon(IconLibrary $library, string $icon_id): void {
    $icons = $library->getIcons();
    if (!isset($icons[$icon_id])) {
      throw new \InvalidArgumentException(sprintf('The icon "%s" is not in this library.', $icon_id));
    }
    $file_uuid = $icons[$icon_id]['file_uuid'];
    unset($icons[$icon_id]);
    $library->set('icons', $icons);
    $library->save();
    $this->releaseFiles([$file_uuid], $library->id());
  }

  /**
   * Sanitizes uploaded candidates without persisting anything.
   *
   * @param string $provider
   *   Icon source plugin ID.
   * @param \Symfony\Component\HttpFoundation\File\UploadedFile[] $uploads
   *   Uploaded source files.
   * @param string $namespace
   *   The library-scoped namespace applied to internal SVG IDs.
   *
   * @return array<string, array{id: string, label: string, group: string, svg: string, viewBox: string, hash: string}>
   *   Sanitized icon payloads keyed by icon ID.
   */
  private function prepareCandidates(string $provider, array $uploads, string $namespace): array {
    $plugin = $this->sourceManager->createInstance($provider);
    if (!$plugin instanceof IconSourceInterface) {
      throw new \InvalidArgumentException('Unknown icon source provider.');
    }
    $prepared = [];
    foreach ($plugin->extract($uploads) as $candidate) {
      $id = $this->machineName($candidate['id']);
      if ($id === '' || isset($prepared[$id])) {
        throw new \InvalidArgumentException(sprintf('The icon ID "%s" is invalid or duplicated.', $id));
      }
      $safe = $this->sanitizer->sanitize($candidate['svg'], $namespace . '-' . $id);
      $prepared[$id] = [
        'id' => $id,
        'label' => $candidate['label'],
        'group' => $candidate['group'],
        'svg' => $safe['svg'],
        'viewBox' => $safe['viewBox'],
        'hash' => hash('sha256', $safe['svg']),
      ];
    }
    return $prepared;
  }

  /**
   * Ensures the managed-file destination exists and is writable.
   */
  private function prepareDestination(): void {
    // prepareDirectory() takes its argument by reference, so it needs a
    // variable rather than the constant.
    $destination = self::DESTINATION;
    if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new \RuntimeException('The icon destination is not writable.');
    }
  }

  /**
   * Writes one sanitized payload to a content-addressed managed file.
   *
   * @param array{id: string, label: string, group: string, svg: string, viewBox: string, hash: string} $record
   *   A sanitized icon payload.
   */
  private function store(array $record): FileInterface {
    return $this->fileRepository->writeData($record['svg'], self::DESTINATION . '/' . $record['hash'] . '.svg', FileExists::Rename);
  }

  /**
   * Builds the stored configuration record for one icon.
   *
   * @param array{id: string, label: string, group: string, svg: string, viewBox: string, hash: string} $record
   *   A sanitized icon payload.
   * @param \Drupal\file\FileInterface $file
   *   The managed file holding the payload.
   *
   * @return array{id: string, label: string, group: string, uri: string, file_uuid: string, viewBox: string, hash: string}
   *   The icon as stored in configuration.
   */
  private function iconRecord(array $record, FileInterface $file): array {
    return [
      'id' => $record['id'],
      'label' => $record['label'],
      'group' => $record['group'],
      'uri' => $file->getFileUri(),
      'file_uuid' => $file->uuid(),
      'viewBox' => $record['viewBox'],
      'hash' => $record['hash'],
    ];
  }

  /**
   * Drops file usage and deletes managed files a library no longer references.
   *
   * @param string[] $uuids
   *   File UUIDs that are no longer referenced.
   * @param string $library_id
   *   The library that released them.
   */
  private function releaseFiles(array $uuids, string $library_id): void {
    if ($uuids === []) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('file');
    foreach ($storage->loadByProperties(['uuid' => $uuids]) as $file) {
      $this->fileUsage->delete($file, 'canvas_utilities_icons', 'canvas_utilities_icon_lib', $library_id);
      $file->delete();
    }
  }

  /**
   * Normalizes a machine-name fragment.
   */
  private function machineName(string $value): string {
    return trim((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower($value)), '-_');
  }

}
