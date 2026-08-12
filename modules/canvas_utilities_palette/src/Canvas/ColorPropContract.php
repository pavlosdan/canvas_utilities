<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_palette\Canvas;

/**
 * Defines the Canvas code-component color prop contract.
 */
final class ColorPropContract {

  /**
   * The JSON Schema reference that opts a string prop into the color picker.
   */
  public const string SCHEMA_REFERENCE = 'json-schema-definitions://canvas_utilities_palette.module/color';

  /**
   * The Canvas-only field widget plugin ID.
   */
  public const string WIDGET_ID = 'canvas_utilities_color_picker';

}
