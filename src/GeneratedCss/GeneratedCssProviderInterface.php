<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\GeneratedCss;

use Drupal\Core\Entity\EntityInterface;

/**
 * Describes a feature that produces a generated CSS override asset.
 */
interface GeneratedCssProviderInterface {

  /**
   * Returns the stable provider ID.
   */
  public function id(): string;

  /**
   * Returns the library containing this provider's placeholder CSS file.
   */
  public function getLibrary(): string;

  /**
   * Returns the placeholder asset path used by hook_css_alter().
   */
  public function getPlaceholderPath(): string;

  /**
   * Returns the ordering weight within the generated override group.
   */
  public function getWeight(): int;

  /**
   * Builds the current CSS source for a theme, when one exists.
   */
  public function build(string $theme): ?GeneratedCssSource;

  /**
   * Returns tags needed when attaching this provider's library.
   *
   * These tags must cover creation and deletion as well as updates.
   *
   * @return string[]
   *   Cache tags.
   */
  public function getCacheTags(string $theme): array;

  /**
   * Returns themes whose CSS can be affected by an entity change.
   *
   * @return string[]
   *   Theme machine names.
   */
  public function getAffectedThemes(EntityInterface $entity): array;

}
