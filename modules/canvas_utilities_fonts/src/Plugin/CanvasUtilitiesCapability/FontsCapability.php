<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_fonts\Plugin\CanvasUtilitiesCapability;

use Drupal\canvas_utilities\Attribute\CanvasUtilitiesCapability;
use Drupal\canvas_utilities\Plugin\CanvasUtilitiesCapability\CanvasUtilitiesCapabilityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Adds the Fonts section to Canvas Utilities.
 */
#[CanvasUtilitiesCapability(
  id: 'fonts',
  label: new TranslatableMarkup('Fonts'),
  description: new TranslatableMarkup('Manage uploaded font faces and remote font stylesheets.'),
  route: 'fonts',
  weight: 20,
  permissions: ['administer canvas utilities fonts'],
)]
final class FontsCapability extends CanvasUtilitiesCapabilityBase {
}
