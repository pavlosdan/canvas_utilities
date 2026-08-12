<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Plugin\CanvasUtilitiesCapability;

use Drupal\canvas_utilities\Attribute\CanvasUtilitiesCapability;
use Drupal\canvas_utilities\Plugin\CanvasUtilitiesCapability\CanvasUtilitiesCapabilityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Adds the Icons section to Canvas Utilities.
 */
#[CanvasUtilitiesCapability(
  id: 'icons',
  label: new TranslatableMarkup('Icons'),
  description: new TranslatableMarkup('Import and browse safe SVG icon libraries.'),
  route: 'icons',
  weight: 30,
  permissions: ['administer canvas utilities icons'],
)]
final class IconsCapability extends CanvasUtilitiesCapabilityBase {
}
