<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_custom_css\GeneratedCss;

use Drupal\canvas_utilities\GeneratedCss\GeneratedCssProviderInterface;
use Drupal\canvas_utilities\GeneratedCss\GeneratedCssSource;
use Drupal\canvas_utilities_custom_css\Entity\CustomCssOverride;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;

/**
 * Produces content-addressed Custom CSS override assets.
 */
final class CustomCssGeneratedCssProvider implements GeneratedCssProviderInterface {

  /**
   * Constructs the Custom CSS generated CSS provider.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ExtensionPathResolver $extensionPathResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'custom-css';
  }

  /**
   * {@inheritdoc}
   */
  public function getLibrary(): string {
    return 'canvas_utilities_custom_css/override';
  }

  /**
   * {@inheritdoc}
   */
  public function getPlaceholderPath(): string {
    return $this->extensionPathResolver->getPath('module', 'canvas_utilities_custom_css') . '/css/generated-css-placeholder.css';
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return 100;
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $theme): ?GeneratedCssSource {
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_css')->load($theme);
    if (!$entity instanceof CustomCssOverride || !$entity->status() || $entity->getCss() === '') {
      return NULL;
    }
    return new GeneratedCssSource($entity->getCss(), array_merge($this->getCacheTags($theme), [
      'config:' . $entity->getConfigDependencyName(),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(string $theme): array {
    return [
      'config:canvas_utilities_css_list',
      'config:system.theme',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAffectedThemes(EntityInterface $entity): array {
    if ($entity->getEntityTypeId() !== 'canvas_utilities_css') {
      return [];
    }
    $values = $entity->toArray();
    $theme = $values['theme'] ?? NULL;
    return is_string($theme) && preg_match('/^[a-z][a-z0-9_]*$/', $theme) ? [$theme] : [];
  }

}
