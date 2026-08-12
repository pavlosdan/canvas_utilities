<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Canvas Utilities capability plugin.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class CanvasUtilitiesCapability extends Plugin {

  /**
   * Constructs a Canvas Utilities capability attribute.
   *
   * @param string $id
   *   The stable capability ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The capability label.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The capability description.
   * @param string $route
   *   The client-side route, without a leading slash.
   * @param int $weight
   *   The navigation weight.
   * @param string[] $permissions
   *   Permissions of which the user must have at least one.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
    public readonly string $route,
    public readonly int $weight = 0,
    public readonly array $permissions = [],
  ) {}

}
