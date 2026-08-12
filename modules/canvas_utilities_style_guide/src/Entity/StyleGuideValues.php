<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_style_guide\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Stores per-theme style-guide overrides.
 */
#[ConfigEntityType(
  id: 'canvas_utilities_sg_values',
  label: new TranslatableMarkup('Canvas Utilities style-guide values'),
  label_collection: new TranslatableMarkup('Canvas Utilities style-guide values'),
  config_prefix: 'values',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  admin_permission: 'edit canvas utilities style guide values',
  config_export: [
    'id',
    'label',
    'uuid',
    'theme',
    'guide_id',
    'definition_hash',
    'values',
  ],
)]
final class StyleGuideValues extends ConfigEntityBase {

  /**
   * The entity ID.
   */
  protected string $id;

  /**
   * The administrative label.
   */
  protected string $label;

  /**
   * The theme machine name.
   */
  protected string $theme;

  /**
   * The public guide ID.
   */
  protected string $guide_id;

  /**
   * The definition fingerprint used for the stored values.
   */
  protected string $definition_hash = '';

  /**
   * Overrides keyed by control ID and context ID.
   *
   * @var array<string, array<string, string|int|float|bool>>
   */
  protected array $values = [];

  /**
   * Returns stored overrides.
   *
   * @return array<string, array<string, string|int|float|bool>>
   *   Values keyed by control ID and context ID.
   */
  public function getValues(): array {
    return $this->values;
  }

  /**
   * Returns the definition fingerprint used for validation.
   */
  public function getDefinitionHash(): string {
    return $this->definition_hash;
  }

}
