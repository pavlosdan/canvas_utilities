<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Hook;

use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropShape\CandidateStorablePropShape;
use Drupal\canvas_utilities_icons\Canvas\IconPropContract;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Integrates the icon picker with Canvas code-component props.
 */
final class CanvasIconPickerHooks {

  /**
   * Implements hook_canvas_storable_prop_shape_alter().
   */
  #[Hook('canvas_storable_prop_shape_alter')]
  public static function storablePropShapeAlter(CandidateStorablePropShape $candidate): void {
    $schema = $candidate->shape->schema;
    if (($schema['$ref'] ?? NULL) !== IconPropContract::SCHEMA_REFERENCE
      && ($schema['x-canvas-utilities-picker'] ?? NULL) !== 'icon') {
      return;
    }

    $candidate->fieldTypeProp = new FieldTypePropExpression('string', 'value');
    $candidate->fieldStorageSettings = ['max_length' => 2048];
    $candidate->fieldInstanceSettings = [];
    $candidate->fieldWidget = IconPropContract::WIDGET_ID;
  }

  /**
   * Implements hook_field_widget_info_alter().
   *
   * @param array<string, mixed> $info
   *   Field widget definitions.
   */
  #[Hook('field_widget_info_alter')]
  public static function fieldWidgetInfoAlter(array &$info): void {
    if (isset($info[IconPropContract::WIDGET_ID])) {
      $info[IconPropContract::WIDGET_ID]['canvas']['transforms'] = [
        'mainProperty' => [],
      ];
    }
  }

  /**
   * Implements hook_config_schema_info_alter().
   *
   * Allows Canvas code component config to retain this module's string $ref.
   *
   * @param array<string, mixed> $definitions
   *   Configuration schema definitions.
   */
  #[Hook('config_schema_info_alter')]
  public static function configSchemaInfoAlter(array &$definitions): void {
    $reference = &$definitions['canvas.json_schema.prop_shape.string']['mapping']['$ref'];
    $reference ??= [
      'requiredKey' => FALSE,
      'type' => 'string',
      'label' => 'Referenced string prop shape',
      'constraints' => [
        'Choice' => [
          'choices' => [],
        ],
      ],
    ];
    $choices = &$reference['constraints']['Choice']['choices'];
    if (!in_array(IconPropContract::SCHEMA_REFERENCE, $choices, TRUE)) {
      $choices[] = IconPropContract::SCHEMA_REFERENCE;
    }
  }

}
