<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Plugin\Field\FieldWidget;

use Drupal\canvas_utilities_icons\Canvas\IconPropContract;
use Drupal\canvas_utilities_icons\IconPickerOptions;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Canvas code-component icon picker.
 */
#[FieldWidget(
  id: IconPropContract::WIDGET_ID,
  label: new TranslatableMarkup('Canvas Utilities icon picker'),
  field_types: ['string'],
)]
final class IconPickerWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Icon picker options service.
   */
  protected IconPickerOptions $pickerOptions;

  /**
   * Config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs an icon picker widget.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\canvas_utilities_icons\IconPickerOptions $pickerOptions
   *   Icon picker options service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    IconPickerOptions $pickerOptions,
    ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
    );
    $this->pickerOptions = $pickerOptions;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Service container.
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      (string) $plugin_id,
      $plugin_definition,
      $container->get(IconPickerOptions::class),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    $canvas_settings = $field_definition->getDisplayOptions('form')['third_party_settings']['canvas'] ?? NULL;
    return is_array($canvas_settings) && isset($canvas_settings['explicit_input_prop_name']);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   Field items.
   * @param int $delta
   *   Field item delta.
   * @param array<mixed> $element
   *   Base form element.
   * @param array<mixed> $form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array<mixed>
   *   Widget form element.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $theme = (string) $this->configFactory->get('system.theme')->get('default');
    $libraries = $this->pickerOptions->forTheme($theme);
    $allowed_values = [];
    foreach ($libraries as $library) {
      array_push($allowed_values, ...array_column($library['icons'], 'value'));
    }

    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->value ?? '',
      '#maxlength' => 2048,
      '#attributes' => [
        'class' => ['js-canvas-utilities-icon-picker', 'canvas-utilities-icon-picker__value'],
        'autocomplete' => 'off',
        'readonly' => 'readonly',
        'data-canvas-utilities-theme' => $theme,
      ],
      '#element_validate' => [[self::class, 'validateIcon']],
      '#canvas_utilities_allowed_values' => $allowed_values,
      '#attached' => [
        'library' => ['canvas_utilities_icons/canvas_picker'],
        'drupalSettings' => [
          'canvasUtilitiesIcons' => [
            'themes' => [
              $theme => $libraries,
            ],
          ],
        ],
      ],
    ];
    $element['#cache']['tags'][] = 'canvas_utilities_icon_lib_list';

    return $element;
  }

  /**
   * Validates that the submitted value belongs to an enabled icon library.
   *
   * @param array<mixed> $element
   *   The element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array<mixed> $complete_form
   *   Complete form.
   */
  public static function validateIcon(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $value = trim((string) $element['#value']);
    $form_state->setValueForElement($element, $value);
    if ($value === '') {
      return;
    }

    if (!in_array($value, $element['#canvas_utilities_allowed_values'] ?? [], TRUE)) {
      $form_state->setError($element, new TranslatableMarkup('Select an icon from an available Canvas Utilities icon library.'));
    }
  }

}
