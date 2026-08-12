<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_custom_css\Plugin\CanvasUtilitiesCapability;

use Drupal\canvas_utilities\Attribute\CanvasUtilitiesCapability;
use Drupal\canvas_utilities\Plugin\CanvasUtilitiesCapability\CanvasUtilitiesCapabilityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Exposes the custom CSS workspace.
 */
#[CanvasUtilitiesCapability(
  id: 'custom_css',
  label: new TranslatableMarkup('Custom CSS'),
  description: new TranslatableMarkup('Add a guarded CSS override without editing theme files.'),
  route: 'custom-css',
  weight: 50,
  permissions: ['administer canvas utilities custom css'],
)]
final class CustomCssCapability extends CanvasUtilitiesCapabilityBase {}
