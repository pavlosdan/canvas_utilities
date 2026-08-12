<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities\Kernel;

use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\PropShape\EphemeralPropShapeRepository;
use Drupal\canvas\PropShape\PropShape;
use Drupal\canvas_utilities_icons\Canvas\IconPropContract;
use Drupal\canvas_utilities_icons\Entity\IconLibrary;
use Drupal\canvas_utilities_icons\IconPickerOptions;
use Drupal\canvas_utilities_icons\Plugin\Field\FieldWidget\IconPickerWidget;
use Drupal\canvas_utilities_palette\Canvas\ColorPropContract;
use Drupal\canvas_utilities_palette\Entity\Palette;
use Drupal\canvas_utilities_palette\PalettePickerOptions;
use Drupal\canvas_utilities_palette\Plugin\Field\FieldWidget\ColorPickerWidget;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Tests\canvas\Kernel\CanvasKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Canvas code-component color and icon prop controls.
 */
#[Group('canvas_utilities')]
#[RunTestsInSeparateProcesses]
final class CanvasPropControlTest extends CanvasKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    ...self::CANVAS_KERNEL_TEST_MINIMAL_MODULES,
    'canvas_utilities',
    'canvas_utilities_palette',
    'canvas_utilities_icons',
  ];

  /**
   * Tests shape matching, Canvas transforms, and regular-field exclusion.
   */
  public function testPropShapeMapping(): void {
    $repository = $this->container->get(EphemeralPropShapeRepository::class);
    self::assertInstanceOf(EphemeralPropShapeRepository::class, $repository);

    $color = $repository->getStorablePropShape(PropShape::normalize([
      'type' => 'string',
      '$ref' => ColorPropContract::SCHEMA_REFERENCE,
    ]));
    self::assertNotNull($color);
    self::assertSame('string', $color->fieldTypeProp->getFieldType());
    self::assertSame(ColorPropContract::WIDGET_ID, $color->fieldWidget);
    self::assertSame(['max_length' => 255], $color->fieldStorageSettings);

    $icon = $repository->getStorablePropShape(PropShape::normalize([
      'type' => 'string',
      '$ref' => IconPropContract::SCHEMA_REFERENCE,
    ]));
    self::assertNotNull($icon);
    self::assertSame('string', $icon->fieldTypeProp->getFieldType());
    self::assertSame(IconPropContract::WIDGET_ID, $icon->fieldWidget);
    self::assertSame(['max_length' => 2048], $icon->fieldStorageSettings);

    $widget_manager = $this->container->get('plugin.manager.field.widget');
    self::assertInstanceOf(WidgetPluginManager::class, $widget_manager);
    foreach ([ColorPropContract::WIDGET_ID, IconPropContract::WIDGET_ID] as $widget_id) {
      $definition = $widget_manager->getDefinition($widget_id);
      self::assertSame(['mainProperty' => []], $definition['canvas']['transforms']);
    }

    $regular_field = BaseFieldDefinition::create('string');
    self::assertFalse(ColorPickerWidget::isApplicable($regular_field));
    self::assertFalse(IconPickerWidget::isApplicable($regular_field));
    $canvas_field = BaseFieldDefinition::create('string')->setDisplayOptions('form', [
      'third_party_settings' => [
        'canvas' => ['explicit_input_prop_name' => 'example'],
      ],
    ]);
    self::assertTrue(ColorPickerWidget::isApplicable($canvas_field));
    self::assertTrue(IconPickerWidget::isApplicable($canvas_field));
  }

  /**
   * Tests that Canvas code component config accepts both prop contracts.
   */
  public function testCodeComponentSchemaReferences(): void {
    $component = JavaScriptComponent::createFromClientSide([
      'machineName' => 'canvas_utilities_picker_test',
      'name' => 'Canvas Utilities picker test',
      'status' => FALSE,
      'required' => [],
      'props' => [
        'color' => [
          'title' => 'Color',
          'type' => 'string',
          '$ref' => ColorPropContract::SCHEMA_REFERENCE,
        ],
        'icon' => [
          'title' => 'Icon',
          'type' => 'string',
          '$ref' => IconPropContract::SCHEMA_REFERENCE,
        ],
      ],
      'slots' => [],
      'sourceCodeJs' => '',
      'sourceCodeCss' => '',
      'compiledJs' => '',
      'compiledCss' => '',
      'importedJsComponents' => [],
      'dataDependencies' => [],
    ]);

    self::assertEntityIsValid($component);
    $projected = $component->toSdcDefinition()['props']['properties'];
    self::assertSame(ColorPropContract::SCHEMA_REFERENCE, $projected['color']['$ref']);
    self::assertSame(IconPropContract::SCHEMA_REFERENCE, $projected['icon']['$ref']);
  }

  /**
   * Tests provider entities supply stable picker values.
   */
  public function testProviderEntitiesSupplyPickerValues(): void {
    $library = IconLibrary::create([
      'id' => 'test_icons',
      'label' => 'Test icons',
      'theme' => 'stark',
      'provider' => 'svg',
      'prefix' => 'test',
      'icons' => [
        [
          'id' => 'spark',
          'label' => 'Spark',
          'group' => 'Shapes',
          'uri' => 'public://spark.svg',
          'file_uuid' => '00000000-0000-4000-8000-000000000000',
          'viewBox' => '0 0 24 24',
          'hash' => hash('sha256', '<svg/>'),
        ],
      ],
    ]);

    self::assertSame('spark', $library->getIcons()['spark']['id']);
    $library->save();

    $picker_options = $this->container->get(IconPickerOptions::class);
    self::assertInstanceOf(IconPickerOptions::class, $picker_options);
    $options = $picker_options->forTheme('stark');
    self::assertCount(1, $options);
    self::assertStringEndsWith('/spark.svg', $options[0]['icons'][0]['value']);
    self::assertStringStartsWith('/', $options[0]['icons'][0]['value']);

    $palette = Palette::create([
      'id' => 'test_palette',
      'label' => 'Test palette',
      'status' => TRUE,
      'theme' => 'stark',
      'description' => '',
      'prefix' => 'test',
      'weight' => 0,
      'colors' => [
        [
          'id' => 'violet',
          'label' => 'Violet',
          'value' => '#6e56cf',
          'role' => 'accent',
        ],
      ],
    ]);
    $palette->save();

    $palette_options = $this->container->get(PalettePickerOptions::class);
    self::assertInstanceOf(PalettePickerOptions::class, $palette_options);
    self::assertSame([
      [
        'palette' => 'Test palette',
        'label' => 'Violet',
        'value' => 'var(--test-violet)',
        'preview' => '#6e56cf',
      ],
    ], $palette_options->forTheme('stark'));
  }

}
