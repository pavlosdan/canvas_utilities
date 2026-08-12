<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Canvas;

/**
 * Defines the Canvas code-component icon prop contract.
 */
final class IconPropContract {

  /**
   * The JSON Schema reference that opts a string prop into the icon picker.
   */
  public const string SCHEMA_REFERENCE = 'json-schema-definitions://canvas_utilities_icons.module/icon';

  /**
   * The Canvas-only field widget plugin ID.
   */
  public const string WIDGET_ID = 'canvas_utilities_icon_picker';

}
