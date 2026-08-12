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
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/**
 * Imports an icon source transactionally into sanitized managed files.
 */
final class IconImporter {

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
    $plugin = $this->sourceManager->createInstance($provider);
    if (!$plugin instanceof IconSourceInterface) {
      throw new \InvalidArgumentException('Unknown icon source provider.');
    }
    $candidates = $plugin->extract($uploads);
    $short_id = $this->machineName((string) ($metadata['id'] ?? $metadata['label'] ?? ''));
    if ($short_id === '') {
      throw new \InvalidArgumentException('A valid library ID is required.');
    }
    $storage = $this->entityTypeManager->getStorage('canvas_utilities_icon_lib');
    $entity_id = $theme . '__' . $short_id;
    if ($storage->load($entity_id) !== NULL) {
      throw new \InvalidArgumentException('An icon library with this ID already exists.');
    }

    $transaction = $this->connection->startTransaction();
    $files = [];
    try {
      $destination = 'public://canvas-utilities/icons';
      if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        throw new \RuntimeException('The icon destination is not writable.');
      }
      $icons = [];
      foreach ($candidates as $candidate) {
        $id = $this->machineName($candidate['id']);
        if ($id === '' || isset($icons[$id])) {
          throw new \InvalidArgumentException(sprintf('The icon ID "%s" is invalid or duplicated.', $id));
        }
        $safe = $this->sanitizer->sanitize($candidate['svg'], $short_id . '-' . $id);
        $hash = hash('sha256', $safe['svg']);
        $file = $this->fileRepository->writeData($safe['svg'], $destination . '/' . $hash . '.svg', FileExists::Rename);
        $files[] = $file;
        $icons[$id] = [
          'id' => $id,
          'label' => $candidate['label'],
          'group' => $candidate['group'],
          'uri' => $file->getFileUri(),
          'file_uuid' => $file->uuid(),
          'viewBox' => $safe['viewBox'],
          'hash' => $hash,
        ];
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
      foreach ($files as $file) {
        $this->fileUsage->add($file, 'canvas_utilities_icons', 'canvas_utilities_icon_lib', $entity->id());
      }
      return $entity;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      foreach ($files as $file) {
        $file->delete();
      }
      throw $exception;
    }
  }

  /**
   * Normalizes a machine-name fragment.
   */
  private function machineName(string $value): string {
    return trim((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower($value)), '-_');
  }

}
