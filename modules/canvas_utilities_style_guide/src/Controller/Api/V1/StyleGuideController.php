<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_style_guide\Controller\Api\V1;

use Drupal\canvas_utilities_style_guide\Entity\StyleGuideDefinition;
use Drupal\canvas_utilities_style_guide\StyleGuide\StyleGuideRepository;
use Drupal\canvas_utilities_style_guide\StyleGuide\StyleGuideDiscovery;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the style-guide JSON API.
 */
final class StyleGuideController {

  /**
   * Constructs the style-guide API controller.
   */
  public function __construct(
    private readonly StyleGuideRepository $repository,
    private readonly StyleGuideDiscovery $discovery,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Returns the current account's resolved guides and drafts for a theme.
   */
  public function collection(string $theme): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->repository->getGuides($theme)], headers: ['Cache-Control' => 'private, no-store']);
    }
    catch (\InvalidArgumentException | \UnexpectedValueException $exception) {
      return $this->error('invalid_definition', $exception->getMessage(), 422);
    }
  }

  /**
   * Validates and stores a private style-guide draft.
   */
  public function draft(Request $request, string $theme, string $guide): JsonResponse {
    try {
      $data = json_decode($request->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
      if (!is_array($data['values'] ?? NULL) || !is_string($data['baseHash'] ?? NULL)) {
        return $this->error('invalid_request', 'The request requires values and baseHash.', 400);
      }
      return new JsonResponse(['data' => $this->repository->saveDraft($theme, $guide, $data['values'], $data['baseHash'])]);
    }
    catch (\JsonException | \InvalidArgumentException | \OutOfBoundsException $exception) {
      return $this->error('validation_failed', $exception->getMessage(), 422);
    }
    catch (\RuntimeException $exception) {
      return $this->error('conflict', $exception->getMessage(), 409);
    }
  }

  /**
   * Publishes the current account's style-guide draft.
   */
  public function publish(string $theme, string $guide): JsonResponse {
    try {
      return new JsonResponse(['data' => $this->repository->publish($theme, $guide)]);
    }
    catch (\OutOfBoundsException | \UnderflowException $exception) {
      return $this->error('not_found', $exception->getMessage(), 404);
    }
    catch (\RuntimeException $exception) {
      return $this->error('conflict', $exception->getMessage(), 409);
    }
  }

  /**
   * Creates a visually authored definition.
   */
  public function createDefinition(Request $request, string $theme): JsonResponse {
    return $this->saveDefinition($request, $theme, NULL);
  }

  /**
   * Updates a visually authored definition.
   */
  public function updateDefinition(Request $request, string $theme, string $definition): JsonResponse {
    return $this->saveDefinition($request, $theme, $definition);
  }

  /**
   * Deletes a visually authored definition.
   */
  public function deleteDefinition(string $theme, string $definition): JsonResponse {
    $storage = $this->entityTypeManager->getStorage('canvas_utilities_sg_definition');
    $entity = $storage->load($definition);
    if (!$entity instanceof StyleGuideDefinition || $entity->get('theme') !== $theme) {
      return $this->error('not_found', 'The visual definition does not exist.', 404);
    }
    $entity->delete();
    $this->cacheTagsInvalidator->invalidateTags(['config:canvas_utilities_sg_definition_list']);
    return new JsonResponse(NULL, 204);
  }

  /**
   * Validates and persists a visual definition.
   */
  private function saveDefinition(Request $request, string $theme, ?string $entity_id): JsonResponse {
    try {
      $data = json_decode($request->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
      if (!is_array($data) || !is_string($data['id'] ?? NULL)) {
        return $this->error('invalid_request', 'The definition requires a public ID.', 400);
      }
      $guide_id = $data['id'];
      $storage = $this->entityTypeManager->getStorage('canvas_utilities_sg_definition');
      $id = $entity_id ?? $theme . '__' . $guide_id;
      $existing = $storage->load($id);
      if ($entity_id === NULL && $existing !== NULL) {
        return $this->error('conflict', 'A visual definition with this ID already exists.', 409);
      }
      if ($entity_id !== NULL && (!$existing instanceof StyleGuideDefinition || $existing->get('theme') !== $theme)) {
        return $this->error('not_found', 'The visual definition does not exist.', 404);
      }
      $candidate = $data + [
        'label' => $guide_id,
        'description' => '',
        'weight' => 0,
        'preview' => ['path' => '/'],
        'contexts' => ['default' => ['label' => 'Default', 'selector' => ':root']],
        'groups' => [],
      ];
      $candidate['entity_id'] = $id;
      $this->discovery->validateCandidate($guide_id, $candidate);

      $entity = $existing ?? $storage->create(['id' => $id]);
      assert($entity instanceof StyleGuideDefinition);
      foreach (['label', 'description', 'weight', 'preview', 'contexts', 'groups'] as $property) {
        $entity->set($property, $candidate[$property]);
      }
      $entity->set('status', (bool) ($data['status'] ?? TRUE));
      $entity->set('theme', $theme);
      $entity->set('guide_id', $guide_id);
      $entity->set('replaces', (string) ($data['replaces'] ?? ''));
      $entity->save();
      $this->cacheTagsInvalidator->invalidateTags(['config:canvas_utilities_sg_definition_list']);
      return new JsonResponse(['data' => ['id' => $id, 'guideId' => $guide_id]], $existing === NULL ? 201 : 200);
    }
    catch (\JsonException | \InvalidArgumentException | \UnexpectedValueException $exception) {
      return $this->error('validation_failed', $exception->getMessage(), 422);
    }
  }

  /**
   * Builds a consistent JSON error response.
   */
  private function error(string $code, string $message, int $status): JsonResponse {
    return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
  }

}
