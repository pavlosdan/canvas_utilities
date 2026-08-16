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
   * An entry holding a CSS color, usable anywhere a color is expected.
   */
  public const string TYPE_COLOR = 'color';

  /**
   * An entry holding a CSS gradient, usable only as a background image.
   */
  public const string TYPE_GRADIENT = 'gradient';

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
   * @var array<string, array{id: string, label: string, value: string, role: string, type?: string}>
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
      'colors' => array_values(array_map(self::normalizeEntry(...), $this->colors)),
    ];
  }

  /**
   * Returns ordered palette colors.
   *
   * @return array<string, array{id: string, label: string, value: string, role: string, type: string}>
   *   Colors keyed by stable color ID.
   */
  public function getColors(): array {
    $colors = [];
    foreach ($this->colors as $color) {
      $color = self::normalizeEntry($color);
      $colors[$color['id']] = $color;
    }
    return $colors;
  }

  /**
   * Returns only the entries usable wherever a CSS color is expected.
   *
   * @return array<string, array<string, mixed>>
   *   Solid color entries keyed by ID.
   */
  public function getSolidColors(): array {
    return array_filter($this->getColors(), static fn (array $entry): bool => $entry['type'] === self::TYPE_COLOR);
  }

  /**
   * Returns only the gradient entries.
   *
   * @return array<string, array<string, mixed>>
   *   Gradient entries keyed by ID.
   */
  public function getGradients(): array {
    return array_filter($this->getColors(), static fn (array $entry): bool => $entry['type'] === self::TYPE_GRADIENT);
  }

  /**
   * Adds the entry type that palettes stored before gradients existed lack.
   *
   * @param array<string, mixed> $entry
   *   A stored entry.
   *
   * @return array<string, mixed>
   *   The entry with an explicit type.
   */
  private static function normalizeEntry(array $entry): array {
    $entry['type'] = ($entry['type'] ?? NULL) === self::TYPE_GRADIENT ? self::TYPE_GRADIENT : self::TYPE_COLOR;
    return $entry;
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
