<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Plugin;

use Drupal\canvas_utilities_icons\Attribute\IconSource;
use Drupal\canvas_utilities_icons\Plugin\IconSource\IconSourceInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Discovers icon-source provider plugins.
 */
final class IconSourceManager extends DefaultPluginManager {

  /**
   * Constructs the icon-source manager.
   *
   * @param \Traversable<string, string> $namespaces
   *   PSR-4 namespaces keyed by namespace prefix.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/IconSource', $namespaces, $module_handler, IconSourceInterface::class, IconSource::class);
    $this->alterInfo('canvas_utilities_icon_source_info');
    $this->setCacheBackend($cache_backend, 'canvas_utilities_icon_sources', ['config:core.extension']);
  }

}
