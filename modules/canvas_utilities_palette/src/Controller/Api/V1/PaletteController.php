<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_palette\Controller\Api\V1;

use Drupal\canvas_utilities_palette\Entity\Palette;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the palette JSON API.
 */
final class PaletteController {

  /**
   * Constructs the palette controller. */
  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Returns all palettes for a theme. */
  public function collection(string $theme): JsonResponse {
    $items = [];
    foreach ($this->entityTypeManager->getStorage('canvas_utilities_palette')->loadMultiple() as $entity) {
      if ($entity instanceof Palette && $entity->getTheme() === $theme) {
        $items[] = $entity->toClientArray();
      }
    }
    usort($items, static fn (array $a, array $b): int => [$a['weight'], $a['label']] <=> [$b['weight'], $b['label']]);
    return new JsonResponse(['data' => $items]);
  }

  /**
   * Validates and creates a palette. */
  public function create(Request $request, string $theme): JsonResponse {
    try {
      $data = json_decode($request->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
      if (!is_array($data) || !preg_match('/^[a-z][a-z0-9_]*$/', (string) ($data['id'] ?? ''))) {
        return $this->error('invalid_request', 'A valid palette ID is required.', 400);
      }
      $storage = $this->entityTypeManager->getStorage('canvas_utilities_palette');
      $id = $theme . '__' . $data['id'];
      if ($storage->load($id) !== NULL) {
        return $this->error('conflict', 'A palette with this ID already exists.', 409);
      }
      $colors = $this->validateColors($data['colors'] ?? []);
      $entity = $storage->create([
        'id' => $id,
        'label' => trim((string) ($data['label'] ?? $data['id'])),
        'status' => TRUE,
        'theme' => $theme,
        'description' => trim((string) ($data['description'] ?? '')),
        'prefix' => $this->machineName((string) ($data['prefix'] ?? 'color')),
        'weight' => (int) ($data['weight'] ?? 0),
        'colors' => $colors,
      ]);
      assert($entity instanceof Palette);
      $entity->save();
      return new JsonResponse(['data' => $entity->toClientArray()], 201);
    }
    catch (\JsonException | \InvalidArgumentException $exception) {
      return $this->error('validation_failed', $exception->getMessage(), 422);
    }
  }

  /**
   * Deletes a palette owned by the selected theme. */
  public function delete(string $theme, string $palette): JsonResponse {
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_palette')->load($palette);
    if (!$entity instanceof Palette || $entity->getTheme() !== $theme) {
      return $this->error('not_found', 'The palette does not exist.', 404);
    }
    $entity->delete();
    return new JsonResponse(NULL, 204);
  }

  /**
   * Validates normalized palette colors.
   *
   * @return array<string, array{id: string, label: string, value: string, role: string}>
   *   Colors keyed by ID.
   */
  private function validateColors(mixed $candidate): array {
    if (!is_array($candidate) || $candidate === []) {
      throw new \InvalidArgumentException('Add at least one color.');
    }
    $colors = [];
    foreach ($candidate as $color) {
      if (!is_array($color)) {
        throw new \InvalidArgumentException('Each color must be an object.');
      }
      $id = $this->machineName((string) ($color['id'] ?? ''));
      $value = strtolower(trim((string) ($color['value'] ?? '')));
      if ($id === '' || !preg_match('/^#[0-9a-f]{6}([0-9a-f]{2})?$/', $value)) {
        throw new \InvalidArgumentException('Each color requires a unique ID and six- or eight-digit hex value.');
      }
      if (isset($colors[$id])) {
        throw new \InvalidArgumentException(sprintf('The color ID "%s" is duplicated.', $id));
      }
      $colors[$id] = [
        'id' => $id,
        'label' => trim((string) ($color['label'] ?? $id)),
        'value' => $value,
        'role' => trim((string) ($color['role'] ?? '')),
      ];
    }
    return $colors;
  }

  /**
   * Normalizes a machine-name fragment. */
  private function machineName(string $value): string {
    return trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($value)), '_');
  }

  /**
   * Builds a consistent JSON error. */
  private function error(string $code, string $message, int $status): JsonResponse {
    return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
  }

}
