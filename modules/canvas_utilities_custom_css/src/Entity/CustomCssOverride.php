<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_custom_css\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a theme-scoped custom CSS override.
 */
#[ConfigEntityType(
  id: 'canvas_utilities_css',
  label: new TranslatableMarkup('Canvas Utilities custom CSS'),
  label_collection: new TranslatableMarkup('Canvas Utilities custom CSS overrides'),
  config_prefix: 'override',
  entity_keys: ['id' => 'id', 'label' => 'label', 'uuid' => 'uuid', 'status' => 'status'],
  admin_permission: 'administer canvas utilities custom css',
  config_export: ['id', 'label', 'uuid', 'status', 'theme', 'css'],
)]
final class CustomCssOverride extends ConfigEntityBase {

  /**
   * The entity ID. */
  protected string $id;

  /**
   * The entity label. */
  protected string $label;

  /**
   * The theme machine name. */
  protected string $theme;

  /**
   * The validated CSS source. */
  protected string $css = '';

  /**
   * Returns the configured theme. */
  public function getTheme(): string {
    return $this->theme;
  }

  /**
   * Returns the CSS source. */
  public function getCss(): string {
    return $this->css;
  }

  /**
   * Returns a stable content fingerprint. */
  public function getFingerprint(): string {
    return substr(hash('sha256', $this->css), 0, 12);
  }

}
