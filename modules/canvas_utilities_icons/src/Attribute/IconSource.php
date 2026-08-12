<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an icon-source provider plugin.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class IconSource extends Plugin {

  /**
   * Constructs an icon-source attribute. */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
    public readonly bool $multiple = FALSE,
  ) {}

}
