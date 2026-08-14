<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Controller\Api\V1;

use Drupal\canvas_utilities_icons\Entity\IconLibrary;
use Drupal\canvas_utilities_icons\Import\IconImporter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides icon-library APIs.
 */
final class IconController {

  /**
   * Constructs the icon controller. */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly IconImporter $importer,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Returns all icon libraries for a theme. */
  public function collection(string $theme): JsonResponse {
    $items = [];
    foreach ($this->entityTypeManager->getStorage('canvas_utilities_icon_lib')->loadMultiple() as $entity) {
      if ($entity instanceof IconLibrary && $entity->getTheme() === $theme) {
        $items[] = $this->clientData($entity);
      }
    }
    return new JsonResponse(['data' => $items]);
  }

  /**
   * Imports an uploaded icon source. */
  public function import(Request $request, string $theme): JsonResponse {
    try {
      $value = $request->files->get('source');
      $files = is_array($value) ? $value : ($value instanceof UploadedFile ? [$value] : []);
      $metadata = $request->request->all();
      $entity = $this->importer->import($theme, (string) ($metadata['provider'] ?? ''), $files, $metadata);
      return new JsonResponse(['data' => $this->clientData($entity)], 201);
    }
    catch (\InvalidArgumentException $exception) {
      return new JsonResponse(['error' => ['code' => 'validation_failed', 'message' => $exception->getMessage()]], 422);
    }
  }

  /**
   * Adds icons from a new upload to an existing library. */
  public function addIcons(Request $request, string $theme, string $library): JsonResponse {
    $entity = $this->loadLibrary($theme, $library);
    if (!$entity instanceof IconLibrary) {
      return $this->notFound();
    }
    try {
      $value = $request->files->get('source');
      $files = is_array($value) ? $value : ($value instanceof UploadedFile ? [$value] : []);
      if ($files === []) {
        return new JsonResponse(['error' => ['code' => 'validation_failed', 'message' => 'Choose at least one source file.']], 422);
      }
      $provider = (string) ($request->request->get('provider') ?: $entity->getProvider());
      $report = $this->importer->addIcons($entity, $provider, $files);
      return new JsonResponse(['data' => $this->clientData($entity), 'meta' => $report]);
    }
    catch (\InvalidArgumentException $exception) {
      return new JsonResponse(['error' => ['code' => 'validation_failed', 'message' => $exception->getMessage()]], 422);
    }
  }

  /**
   * Updates the editable metadata of a library. */
  public function update(Request $request, string $theme, string $library): JsonResponse {
    $entity = $this->loadLibrary($theme, $library);
    if (!$entity instanceof IconLibrary) {
      return $this->notFound();
    }
    try {
      $data = json_decode($request->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
      if (!is_array($data)) {
        return new JsonResponse(['error' => ['code' => 'invalid_request', 'message' => 'The request body must be an object.']], 400);
      }
      if (array_key_exists('label', $data)) {
        $label = trim((string) $data['label']);
        if ($label === '') {
          return new JsonResponse(['error' => ['code' => 'validation_failed', 'message' => 'The library name cannot be empty.']], 422);
        }
        $entity->set('label', $label);
      }
      foreach (['license', 'source'] as $key) {
        if (array_key_exists($key, $data)) {
          $entity->set($key, trim((string) $data[$key]));
        }
      }
      if (array_key_exists('status', $data)) {
        $entity->setStatus((bool) $data['status']);
      }
      $entity->save();
      return new JsonResponse(['data' => $this->clientData($entity)]);
    }
    catch (\JsonException $exception) {
      return new JsonResponse(['error' => ['code' => 'invalid_request', 'message' => $exception->getMessage()]], 400);
    }
  }

  /**
   * Deletes a library and its managed files. */
  public function delete(string $theme, string $library): JsonResponse {
    $entity = $this->loadLibrary($theme, $library);
    if (!$entity instanceof IconLibrary) {
      return $this->notFound();
    }
    $entity->delete();
    return new JsonResponse(NULL, 204);
  }

  /**
   * Deletes a single icon from a library. */
  public function deleteIcon(string $theme, string $library, string $icon): JsonResponse {
    $entity = $this->loadLibrary($theme, $library);
    if (!$entity instanceof IconLibrary) {
      return $this->notFound();
    }
    try {
      $this->importer->removeIcon($entity, $icon);
    }
    catch (\InvalidArgumentException $exception) {
      return new JsonResponse(['error' => ['code' => 'not_found', 'message' => $exception->getMessage()]], 404);
    }
    return new JsonResponse(['data' => $this->clientData($entity)]);
  }

  /**
   * Loads a library that belongs to the requested theme. */
  private function loadLibrary(string $theme, string $library): ?IconLibrary {
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_icon_lib')->load($library);
    return $entity instanceof IconLibrary && $entity->getTheme() === $theme ? $entity : NULL;
  }

  /**
   * Builds the shared missing-library response. */
  private function notFound(): JsonResponse {
    return new JsonResponse(['error' => ['code' => 'not_found', 'message' => 'The icon library does not exist.']], 404);
  }

  /**
   * Adds browser-safe public URLs to config entity data.
   *
   * @return array<string, mixed>
   *   Client data.
   */
  private function clientData(IconLibrary $entity): array {
    $data = $entity->toClientArray();
    foreach ($data['icons'] as &$icon) {
      $icon['url'] = $this->fileUrlGenerator->generateString($icon['uri']);
      unset($icon['uri'], $icon['file_uuid']);
    }
    return $data;
  }

}
