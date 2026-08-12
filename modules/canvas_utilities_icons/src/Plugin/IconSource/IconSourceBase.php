<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Plugin\IconSource;

use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for icon-source providers.
 */
abstract class IconSourceBase extends PluginBase implements IconSourceInterface {

  /**
   * Normalizes a source icon ID. */
  protected function iconId(string $value): string {
    $id = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower($value)), '-_');
    if ($id === '' || !preg_match('/^[a-z]/', $id)) {
      $id = 'icon-' . $id;
    }
    return $id;
  }

}
