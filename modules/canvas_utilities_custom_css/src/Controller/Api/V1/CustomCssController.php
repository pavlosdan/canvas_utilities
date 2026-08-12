<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_custom_css\Controller\Api\V1;

use Drupal\canvas_utilities_custom_css\CssValidator;
use Drupal\canvas_utilities_custom_css\Entity\CustomCssOverride;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the custom CSS API.
 */
final class CustomCssController {

  /**
   * Constructs the controller. */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CssValidator $validator,
  ) {}

  /**
   * Returns a theme's current override. */
  public function item(string $theme): JsonResponse {
    $entity = $this->load($theme);
    return new JsonResponse(['data' => $this->data($entity, $theme)]);
  }

  /**
   * Creates or replaces a theme's current override. */
  public function save(Request $request, string $theme): JsonResponse {
    try {
      $data = json_decode($request->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
      if (!is_array($data)) {
        throw new \InvalidArgumentException('Invalid JSON object.');
      }
      $css = $this->validator->validate((string) ($data['css'] ?? ''));
      $storage = $this->entityTypeManager->getStorage('canvas_utilities_css');
      $entity = $this->load($theme) ?? $storage->create([
        'id' => $theme,
        'label' => $theme . ' custom CSS',
        'theme' => $theme,
        'status' => TRUE,
      ]);
      assert($entity instanceof CustomCssOverride);
      $entity->set('css', $css)->setStatus((bool) ($data['status'] ?? TRUE))->save();
      return new JsonResponse(['data' => $this->data($entity, $theme)]);
    }
    catch (\JsonException | \InvalidArgumentException $exception) {
      return new JsonResponse(['error' => ['code' => 'validation_failed', 'message' => $exception->getMessage()]], 422);
    }
  }

  /**
   * Loads a theme override. */
  private function load(string $theme): ?CustomCssOverride {
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_css')->load($theme);
    return $entity instanceof CustomCssOverride ? $entity : NULL;
  }

  /**
   * Builds client data.
   *
   * @return array<string, mixed>
   *   Client data.
   */
  private function data(?CustomCssOverride $entity, string $theme): array {
    return [
      'theme' => $theme,
      'css' => $entity?->getCss() ?? '',
      'status' => $entity?->status() ?? TRUE,
      'fingerprint' => $entity?->getFingerprint() ?? NULL,
    ];
  }

}
