<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_style_guide\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a visually authored style-guide definition.
 */
#[ConfigEntityType(
  id: 'canvas_utilities_sg_definition',
  label: new TranslatableMarkup('Canvas Utilities style-guide definition'),
  label_collection: new TranslatableMarkup('Canvas Utilities style-guide definitions'),
  config_prefix: 'definition',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
    'status' => 'status',
  ],
  admin_permission: 'administer canvas utilities style guide definitions',
  config_export: [
    'id',
    'label',
    'uuid',
    'status',
    'description',
    'theme',
    'guide_id',
    'weight',
    'replaces',
    'preview',
    'contexts',
    'groups',
  ],
)]
final class StyleGuideDefinition extends ConfigEntityBase {

  /**
   * The entity ID.
   */
  protected string $id;

  /**
   * The administrative label.
   */
  protected string $label;

  /**
   * The guide description.
   */
  protected string $description = '';

  /**
   * The theme machine name.
   */
  protected string $theme;

  /**
   * The public guide ID.
   */
  protected string $guide_id;

  /**
   * The guide weight.
   */
  protected int $weight = 0;

  /**
   * The explicitly replaced provider, if any.
   */
  protected string $replaces = '';

  /**
   * The preview configuration.
   *
   * @var array<string, mixed>
   */
  protected array $preview = [];

  /**
   * Context definitions keyed by context ID.
   *
   * @var array<string, array<string, mixed>>
   */
  protected array $contexts = [];

  /**
   * Group definitions keyed by group ID.
   *
   * @var array<string, array<string, mixed>>
   */
  protected array $groups = [];

  /**
   * Returns the normalized definition data.
   *
   * @return array<string, mixed>
   *   Definition data.
   */
  public function toDefinition(): array {
    return [
      'id' => $this->guide_id,
      'label' => $this->label,
      'description' => $this->description,
      'theme' => $this->theme,
      'weight' => $this->weight,
      'replaces' => $this->replaces,
      'preview' => $this->preview,
      'contexts' => $this->contexts,
      'groups' => $this->groups,
      'source' => [
        'type' => 'config',
        'id' => $this->id(),
        'label' => (string) $this->label(),
      ],
    ];
  }

}
