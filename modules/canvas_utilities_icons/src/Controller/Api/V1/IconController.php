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
   * Deletes a library and its managed files. */
  public function delete(string $theme, string $library): JsonResponse {
    $storage = $this->entityTypeManager->getStorage('canvas_utilities_icon_lib');
    $entity = $storage->load($library);
    if (!$entity instanceof IconLibrary || $entity->getTheme() !== $theme) {
      return new JsonResponse(['error' => ['code' => 'not_found', 'message' => 'The icon library does not exist.']], 404);
    }
    $entity->delete();
    return new JsonResponse(NULL, 204);
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
