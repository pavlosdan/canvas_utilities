<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_palette\Plugin\CanvasUtilitiesCapability;

use Drupal\canvas_utilities\Attribute\CanvasUtilitiesCapability;
use Drupal\canvas_utilities\Plugin\CanvasUtilitiesCapability\CanvasUtilitiesCapabilityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Adds the Palettes section to Canvas Utilities.
 */
#[CanvasUtilitiesCapability(
  id: 'palette',
  label: new TranslatableMarkup('Colors'),
  description: new TranslatableMarkup('Create reusable color palettes and semantic roles.'),
  route: 'palettes',
  weight: 10,
  permissions: ['administer canvas utilities palettes'],
)]
final class PaletteCapability extends CanvasUtilitiesCapabilityBase {
}
