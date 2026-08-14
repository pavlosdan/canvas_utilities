<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a sanitized icon library.
 */
#[ConfigEntityType(
  id: 'canvas_utilities_icon_lib',
  label: new TranslatableMarkup('Canvas Utilities icon library'),
  label_collection: new TranslatableMarkup('Canvas Utilities icon libraries'),
  config_prefix: 'library',
  entity_keys: ['id' => 'id', 'label' => 'label', 'uuid' => 'uuid', 'status' => 'status'],
  admin_permission: 'administer canvas utilities icons',
  config_export: ['id', 'label', 'uuid', 'status', 'theme', 'provider', 'prefix', 'license', 'source', 'icons'],
)]
final class IconLibrary extends ConfigEntityBase {

  /**
   * The entity ID. */
  protected string $id;

  /**
   * The library label. */
  protected string $label;

  /**
   * The theme machine name. */
  protected string $theme;

  /**
   * The source provider ID. */
  protected string $provider;

  /**
   * The public icon prefix. */
  protected string $prefix;

  /**
   * The license identifier. */
  protected string $license = '';

  /**
   * The source attribution. */
  protected string $source = '';

  /**
   * Imported icon metadata.
   *
   * @var array<string, array{id: string, label: string, group: string, uri: string, file_uuid: string, viewBox: string, hash: string}>
   */
  protected array $icons = [];

  /**
   * Returns the configured theme. */
  public function getTheme(): string {
    return $this->theme;
  }

  /**
   * Returns the source provider plugin ID. */
  public function getProvider(): string {
    return $this->provider;
  }

  /**
   * Returns the library ID without its theme prefix.
   *
   * This is the namespace applied to internal SVG IDs at import time, so later
   * uploads must reuse it to stay consistent with already-stored icons.
   */
  public function getShortId(): string {
    $prefix = $this->theme . '__';
    return str_starts_with($this->id, $prefix) ? substr($this->id, strlen($prefix)) : $this->id;
  }

  /**
   * Returns icon metadata.
   *
   * @return array<string, array{id: string, label: string, group: string, uri: string, file_uuid: string, viewBox: string, hash: string}>
   *   Icons keyed by ID.
   */
  public function getIcons(): array {
    $icons = [];
    foreach ($this->icons as $icon) {
      $icons[$icon['id']] = $icon;
    }
    return $icons;
  }

  /**
   * Returns client-safe library data.
   *
   * @return array<string, mixed>
   *   Library data.
   */
  public function toClientArray(): array {
    return [
      'id' => $this->id(),
      'label' => $this->label(),
      'theme' => $this->theme,
      'provider' => $this->provider,
      'prefix' => $this->prefix,
      'license' => $this->license,
      'source' => $this->source,
      'status' => $this->status(),
      'icons' => array_values($this->icons),
    ];
  }

}
