<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_palette\Plugin\Field\FieldWidget;

use Drupal\canvas_utilities_palette\Canvas\ColorPropContract;
use Drupal\canvas_utilities_palette\Color\CssColor;
use Drupal\canvas_utilities_palette\PalettePickerOptions;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Canvas code-component color picker.
 */
#[FieldWidget(
  id: ColorPropContract::WIDGET_ID,
  label: new TranslatableMarkup('Canvas Utilities color picker'),
  field_types: ['string'],
)]
final class ColorPickerWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Palette picker options service.
   */
  protected PalettePickerOptions $pickerOptions;

  /**
   * Config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Current account.
   */
  protected AccountInterface $currentUser;

  /**
   * Constructs a color picker widget.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\canvas_utilities_palette\PalettePickerOptions $pickerOptions
   *   Palette picker options service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current account.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    PalettePickerOptions $pickerOptions,
    ConfigFactoryInterface $configFactory,
    AccountInterface $currentUser,
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
    $this->currentUser = $currentUser;
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
      $container->get(PalettePickerOptions::class),
      $container->get('config.factory'),
      $container->get('current_user'),
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
    $colors = $this->pickerOptions->forTheme($theme);
    $allowed_values = array_column($colors, 'value');
    $allow_custom = $this->currentUser->hasPermission('use unrestricted canvas utilities colors');

    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->value ?? '',
      '#maxlength' => 255,
      '#placeholder' => $allow_custom ? $this->t('Choose a palette color or enter a CSS color') : $this->t('Choose a palette color'),
      '#attributes' => [
        'class' => ['js-canvas-utilities-color-picker', 'canvas-utilities-color-picker__value'],
        'autocomplete' => 'off',
        'data-canvas-utilities-allow-custom' => $allow_custom ? 'true' : 'false',
      ],
      '#element_validate' => [[self::class, 'validateColor']],
      '#canvas_utilities_allowed_values' => $allowed_values,
      '#canvas_utilities_allow_custom' => $allow_custom,
      '#attached' => [
        'library' => ['canvas_utilities_palette/canvas_picker'],
      ],
    ];
    // The palettes travel on the element rather than in drupalSettings, which
    // Drupal merges recursively across AJAX responses: arrays combine index by
    // index instead of being replaced, so a deleted palette would stay
    // selectable until the page was fully reloaded.
    // @see Drupal.AjaxCommands.prototype.settings in core/misc/ajax.js
    $element['value']['#attributes']['data-canvas-utilities-palettes'] = Json::encode($colors);
    if (!$allow_custom) {
      $element['value']['#attributes']['readonly'] = 'readonly';
    }
    $element['value']['#attributes']['data-canvas-utilities-theme'] = $theme;
    $element['#cache']['tags'][] = 'canvas_utilities_palette_list';

    return $element;
  }

  /**
   * Validates a palette reference or authorized custom CSS color.
   *
   * @param array<mixed> $element
   *   The element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array<mixed> $complete_form
   *   Complete form.
   */
  public static function validateColor(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $value = trim((string) $element['#value']);
    $form_state->setValueForElement($element, $value);
    if ($value === '') {
      return;
    }

    $allowed_values = $element['#canvas_utilities_allowed_values'] ?? [];
    if (in_array($value, $allowed_values, TRUE)) {
      return;
    }

    if (!($element['#canvas_utilities_allow_custom'] ?? FALSE)) {
      $form_state->setError($element, new TranslatableMarkup('Select a color from an approved palette.'));
      return;
    }

    if (!CssColor::isValid($value)) {
      $form_state->setError($element, new TranslatableMarkup('Enter a valid CSS color, such as #3366ff, rgb(51 102 255), or var(--brand-primary).'));
    }
  }

}
