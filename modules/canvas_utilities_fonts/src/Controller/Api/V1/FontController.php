<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_fonts\Controller\Api\V1;

use Drupal\canvas_utilities_fonts\Entity\FontFamily;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\file\Upload\FileUploadHandlerInterface;
use Drupal\file\Upload\FormUploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides local and remote font-family APIs.
 */
final class FontController {

  /**
   * Constructs the font controller. */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUploadHandlerInterface $fileUploadHandler,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileUsageInterface $fileUsage,
  ) {}

  /**
   * Returns all font families for a theme. */
  public function collection(string $theme): JsonResponse {
    $items = [];
    foreach ($this->entityTypeManager->getStorage('canvas_utilities_font')->loadMultiple() as $entity) {
      if ($entity instanceof FontFamily && $entity->getTheme() === $theme) {
        $items[] = $entity->toClientArray();
      }
    }
    return new JsonResponse(['data' => $items]);
  }

  /**
   * Creates a remote-stylesheet font family without fetching it. */
  public function createRemote(Request $request, string $theme): JsonResponse {
    try {
      $data = json_decode($request->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
      if (!is_array($data)) {
        throw new \InvalidArgumentException('Invalid JSON object.');
      }
      $url = (string) ($data['url'] ?? '');
      if (!$this->isSafeHttpsUrl($url)) {
        throw new \InvalidArgumentException('Remote stylesheets require a public HTTPS URL without credentials.');
      }
      $entity = $this->createEntity($theme, $data, 'remote_stylesheet', ['remote_url' => $url]);
      return new JsonResponse(['data' => $entity->toClientArray()], 201);
    }
    catch (\JsonException | \InvalidArgumentException $exception) {
      return $this->error($exception->getMessage(), 422);
    }
  }

  /**
   * Validates and stores one uploaded local font face. */
  public function upload(Request $request, string $theme): JsonResponse {
    $upload = $request->files->get('font');
    if (!$upload instanceof UploadedFile || !$upload->isValid()) {
      return $this->error('A valid font upload is required.', 400);
    }
    try {
      $format = $this->validateFontSignature($upload);
      $destination = 'public://canvas-utilities/fonts';
      if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        throw new \RuntimeException('The font destination is not writable.');
      }
      $result = $this->fileUploadHandler->handleFileUpload(
        new FormUploadedFile($upload),
        validators: [
          'FileNameLength' => [],
          'FileExtension' => ['extensions' => 'woff2 woff ttf otf'],
          'FileSizeLimit' => ['fileLimit' => 10 * 1024 * 1024],
        ],
        destination: $destination,
        fileExists: FileExists::Rename,
      );
      if ($result->hasViolations()) {
        $messages = [];
        foreach ($result->getViolations() as $violation) {
          $messages[] = (string) $violation->getMessage();
        }
        throw new \InvalidArgumentException(implode(' ', $messages) ?: 'The font upload failed validation.');
      }
      $file = $result->getFile();
      $extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
      $hash = hash_file('sha256', $file->getFileUri());
      if ($hash === FALSE) {
        throw new \RuntimeException('The uploaded font could not be hashed.');
      }
      $file = $this->fileRepository->move($file, 'public://canvas-utilities/fonts/' . $hash . '.' . $extension, FileExists::Rename);
      $data = $request->request->all();
      $face = [
        'file_uuid' => $file->uuid(),
        'uri' => $file->getFileUri(),
        'format' => $format,
        'weight' => $this->fontWeight((string) ($data['weight'] ?? '400')),
        'style' => in_array($data['style'] ?? 'normal', ['normal', 'italic', 'oblique'], TRUE) ? $data['style'] : 'normal',
      ];
      $entity = $this->createEntity($theme, $data, 'local_file', ['faces' => [$face]]);
      $this->fileUsage->add($file, 'canvas_utilities_fonts', 'canvas_utilities_font', $entity->id());
      return new JsonResponse(['data' => $entity->toClientArray()], 201);
    }
    catch (\InvalidArgumentException $exception) {
      return $this->error($exception->getMessage(), 422);
    }
  }

  /**
   * Deletes a font family and any managed local faces. */
  /**
   * Updates the editable metadata of a font family.
   *
   * The ID and provider are fixed once a family exists: the ID appears in the
   * stored CSS variable names, and the provider decides whether the family is
   * backed by uploaded files or a remote stylesheet, which is not something an
   * edit can convert between.
   */
  public function update(Request $request, string $theme, string $font): JsonResponse {
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_font')->load($font);
    if (!$entity instanceof FontFamily || $entity->getTheme() !== $theme) {
      return $this->error('The font family does not exist.', 404);
    }
    try {
      $data = json_decode($request->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
      if (!is_array($data)) {
        throw new \InvalidArgumentException('Invalid JSON object.');
      }
      if (array_key_exists('label', $data)) {
        $label = trim((string) $data['label']);
        if ($label === '') {
          throw new \InvalidArgumentException('The font name cannot be empty.');
        }
        $entity->set('label', $label);
      }
      if (array_key_exists('family', $data)) {
        $family = trim((string) $data['family']);
        if ($family === '') {
          throw new \InvalidArgumentException('The family name cannot be empty.');
        }
        $entity->set('family', $family);
      }
      if (array_key_exists('fallbacks', $data)) {
        $entity->set('fallbacks', $this->fallbacks((string) $data['fallbacks']));
      }
      if (array_key_exists('status', $data)) {
        $entity->setStatus((bool) $data['status']);
      }
      if (array_key_exists('url', $data)) {
        if ($entity->getProvider() !== 'remote_stylesheet') {
          throw new \InvalidArgumentException('Only a remote stylesheet family has a URL.');
        }
        $url = (string) $data['url'];
        if (!$this->isSafeHttpsUrl($url)) {
          throw new \InvalidArgumentException('Remote stylesheets require a public HTTPS URL without credentials.');
        }
        $entity->set('remote_url', $url);
      }
      $entity->save();
      return new JsonResponse(['data' => $entity->toClientArray()]);
    }
    catch (\JsonException | \InvalidArgumentException $exception) {
      return $this->error($exception->getMessage(), 422);
    }
  }

  public function delete(string $theme, string $font): JsonResponse {
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_font')->load($font);
    if (!$entity instanceof FontFamily || $entity->getTheme() !== $theme) {
      return $this->error('The font family does not exist.', 404);
    }
    $entity->delete();
    return new JsonResponse(NULL, 204);
  }

  /**
   * Creates and saves a normalized font entity.
   *
   * @param string $theme
   *   Theme machine name.
   * @param array<string, mixed> $data
   *   Request data.
   * @param string $provider
   *   Font source-provider ID.
   * @param array<string, mixed> $extra
   *   Provider-specific properties.
   */
  private function createEntity(string $theme, array $data, string $provider, array $extra): FontFamily {
    $family = trim((string) ($data['family'] ?? ''));
    $short_id = $this->machineName((string) ($data['id'] ?? $family));
    if ($family === '' || $short_id === '') {
      throw new \InvalidArgumentException('A family name and valid ID are required.');
    }
    $storage = $this->entityTypeManager->getStorage('canvas_utilities_font');
    $id = $theme . '__' . $short_id;
    if ($storage->load($id) !== NULL) {
      throw new \InvalidArgumentException('A font family with this ID already exists.');
    }
    $entity = $storage->create([
      'id' => $id,
      'label' => trim((string) ($data['label'] ?? $family)),
      'status' => TRUE,
      'theme' => $theme,
      'family' => $family,
      'fallbacks' => $this->fallbacks((string) ($data['fallbacks'] ?? 'sans-serif')),
      'provider' => $provider,
    ] + $extra);
    assert($entity instanceof FontFamily);
    $entity->save();
    return $entity;
  }

  /**
   * Detects a supported font container from magic bytes. */
  private function validateFontSignature(UploadedFile $upload): string {
    $handle = fopen($upload->getRealPath(), 'rb');
    $signature = $handle === FALSE ? FALSE : fread($handle, 4);
    if (is_resource($handle)) {
      fclose($handle);
    }
    $formats = ["\x00\x01\x00\x00" => 'truetype', 'OTTO' => 'opentype', 'wOFF' => 'woff', 'wOF2' => 'woff2'];
    if (!is_string($signature) || !isset($formats[$signature])) {
      throw new \InvalidArgumentException('The file is not a supported WOFF2, WOFF, TTF, or OTF font.');
    }
    return $formats[$signature];
  }

  /**
   * Validates a remote stylesheet URL without making a request. */
  private function isSafeHttpsUrl(string $url): bool {
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
      return FALSE;
    }
    $host = strtolower($parts['host']);
    return $host !== 'localhost' && filter_var($host, FILTER_VALIDATE_IP) === FALSE;
  }

  /**
   * Normalizes a font weight or range. */
  private function fontWeight(string $weight): string {
    return preg_match('/^(normal|bold|[1-9]00|[1-9]00 [1-9]00)$/', $weight) ? $weight : '400';
  }

  /**
   * Normalizes a safe fallback-family list. */
  private function fallbacks(string $fallbacks): string {
    if (!preg_match('/^[a-zA-Z0-9 _,-]+$/', $fallbacks)) {
      throw new \InvalidArgumentException('The fallback list contains unsupported characters.');
    }
    return trim($fallbacks);
  }

  /**
   * Normalizes a machine-name fragment. */
  private function machineName(string $value): string {
    return trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($value)), '_');
  }

  /**
   * Builds a consistent JSON error. */
  private function error(string $message, int $status): JsonResponse {
    return new JsonResponse(['error' => ['code' => 'validation_failed', 'message' => $message]], $status);
  }

}
