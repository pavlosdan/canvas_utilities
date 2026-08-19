<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\GeneratedCss;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Invalidates generated CSS and Drupal's resolved asset collection selectively.
 */
final class GeneratedCssInvalidator {

  /**
   * Constructs the generated CSS invalidator.
   */
  public function __construct(
    private readonly GeneratedCssProviderRegistry $providers,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Invalidates assets affected by an entity save or deletion.
   */
  public function invalidateForEntity(EntityInterface $entity): void {
    $tags = [];
    foreach ($this->providers->all() as $provider) {
      foreach ($provider->getAffectedThemes($entity) as $theme) {
        $tags = array_merge($tags, $provider->getCacheTags($theme), [
          GeneratedCssAssetManager::getCacheTag($provider->id(), $theme),
        ]);
      }
    }
    if ($tags !== []) {
      // The CSS resolver caches hook_css_alter() output under this tag. This is
      // targeted invalidation, unlike resetting Drupal's global asset token.
      $tags[] = 'library_info';
      $this->cacheTagsInvalidator->invalidateTags(array_values(array_unique($tags)));
    }
  }

}
