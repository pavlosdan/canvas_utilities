<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_palette\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a reusable design-system palette.
 */
#[ConfigEntityType(
  id: 'canvas_utilities_palette',
  label: new TranslatableMarkup('Canvas Utilities palette'),
  label_collection: new TranslatableMarkup('Canvas Utilities palettes'),
  config_prefix: 'palette',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
    'status' => 'status',
  ],
  admin_permission: 'administer canvas utilities palettes',
  config_export: ['id', 'label', 'uuid', 'status', 'theme', 'description', 'prefix', 'weight', 'colors'],
)]
final class Palette extends ConfigEntityBase {

  /**
   * The entity ID. */
  protected string $id;

  /**
   * The palette label. */
  protected string $label;

  /**
   * The theme machine name. */
  protected string $theme;

  /**
   * The palette description. */
  protected string $description = '';

  /**
   * The CSS custom-property prefix. */
  protected string $prefix = 'color';

  /**
   * The palette weight. */
  protected int $weight = 0;

  /**
   * Ordered colors.
   *
   * @var array<string, array{id: string, label: string, value: string, role: string}>
   */
  protected array $colors = [];

  /**
   * Returns client-safe palette data.
   *
   * @return array<string, mixed>
   *   Palette data.
   */
  public function toClientArray(): array {
    return [
      'id' => $this->id(),
      'label' => $this->label(),
      'theme' => $this->theme,
      'description' => $this->description,
      'prefix' => $this->prefix,
      'weight' => $this->weight,
      'status' => $this->status(),
      'colors' => array_values($this->colors),
    ];
  }

  /**
   * Returns ordered palette colors.
   *
   * @return array<string, array{id: string, label: string, value: string, role: string}>
   *   Colors keyed by stable color ID.
   */
  public function getColors(): array {
    $colors = [];
    foreach ($this->colors as $color) {
      $colors[$color['id']] = $color;
    }
    return $colors;
  }

  /**
   * Returns the CSS variable prefix. */
  public function getPrefix(): string {
    return $this->prefix;
  }

  /**
   * Returns the configured theme. */
  public function getTheme(): string {
    return $this->theme;
  }

}
