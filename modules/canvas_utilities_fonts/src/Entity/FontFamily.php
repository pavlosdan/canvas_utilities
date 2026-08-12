<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_fonts\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Canvas Utilities font family.
 */
#[ConfigEntityType(
  id: 'canvas_utilities_font',
  label: new TranslatableMarkup('Canvas Utilities font family'),
  label_collection: new TranslatableMarkup('Canvas Utilities font families'),
  config_prefix: 'font',
  entity_keys: ['id' => 'id', 'label' => 'label', 'uuid' => 'uuid', 'status' => 'status'],
  admin_permission: 'administer canvas utilities fonts',
  config_export: ['id', 'label', 'uuid', 'status', 'theme', 'family', 'fallbacks', 'provider', 'remote_url', 'faces'],
)]
final class FontFamily extends ConfigEntityBase {

  /**
   * The entity ID. */
  protected string $id;

  /**
   * The administrative label. */
  protected string $label;

  /**
   * The theme machine name. */
  protected string $theme;

  /**
   * The CSS font-family name. */
  protected string $family;

  /**
   * The CSS fallback list. */
  protected string $fallbacks = 'sans-serif';

  /**
   * The source provider ID. */
  protected string $provider;

  /**
   * The remote stylesheet URL. */
  protected string $remote_url = '';

  /**
   * Local font faces.
   *
   * @var array<int, array{file_uuid: string, uri: string, format: string, weight: string, style: string}>
   */
  protected array $faces = [];

  /**
   * Returns the theme machine name. */
  public function getTheme(): string {
    return $this->theme;
  }

  /**
   * Returns the provider ID. */
  public function getProvider(): string {
    return $this->provider;
  }

  /**
   * Returns the remote stylesheet URL. */
  public function getRemoteUrl(): string {
    return $this->remote_url;
  }

  /**
   * Returns the CSS family name. */
  public function getFamily(): string {
    return $this->family;
  }

  /**
   * Returns the fallback list. */
  public function getFallbacks(): string {
    return $this->fallbacks;
  }

  /**
   * Returns local font faces.
   *
   * @return array<int, array{file_uuid: string, uri: string, format: string, weight: string, style: string}>
   *   Font faces.
   */
  public function getFaces(): array {
    return $this->faces;
  }

  /**
   * Returns client-safe font data.
   *
   * @return array<string, mixed>
   *   Font family data.
   */
  public function toClientArray(): array {
    return [
      'id' => $this->id(),
      'label' => $this->label(),
      'theme' => $this->theme,
      'family' => $this->family,
      'fallbacks' => $this->fallbacks,
      'provider' => $this->provider,
      'remoteUrl' => $this->remote_url,
      'faces' => $this->faces,
      'status' => $this->status(),
    ];
  }

}
