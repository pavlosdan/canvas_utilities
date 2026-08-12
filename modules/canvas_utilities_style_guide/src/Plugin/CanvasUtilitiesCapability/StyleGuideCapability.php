<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_style_guide\Plugin\CanvasUtilitiesCapability;

use Drupal\canvas_utilities\Attribute\CanvasUtilitiesCapability;
use Drupal\canvas_utilities\Plugin\CanvasUtilitiesCapability\CanvasUtilitiesCapabilityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Adds the Style guide section to Canvas Utilities.
 */
#[CanvasUtilitiesCapability(
  id: 'style_guide',
  label: new TranslatableMarkup('Style guide'),
  description: new TranslatableMarkup('Adjust structured theme styles and design tokens.'),
  route: 'style-guide',
  weight: 0,
  permissions: ['edit canvas utilities style guide values', 'administer canvas utilities style guide definitions'],
)]
final class StyleGuideCapability extends CanvasUtilitiesCapabilityBase {
}
