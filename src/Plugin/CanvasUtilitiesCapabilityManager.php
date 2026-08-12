<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\Plugin;

use Drupal\canvas_utilities\Attribute\CanvasUtilitiesCapability;
use Drupal\canvas_utilities\Plugin\CanvasUtilitiesCapability\CanvasUtilitiesCapabilityInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Discovers enabled Canvas Utilities capabilities.
 */
final class CanvasUtilitiesCapabilityManager extends DefaultPluginManager {

  /**
   * Constructs the capability plugin manager.
   *
   * @param \Traversable<string, string> $namespaces
   *   PSR-4 namespaces keyed by namespace prefix.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Discovery cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/CanvasUtilitiesCapability',
      $namespaces,
      $module_handler,
      CanvasUtilitiesCapabilityInterface::class,
      CanvasUtilitiesCapability::class,
    );
    $this->alterInfo('canvas_utilities_capability_info');
    $this->setCacheBackend($cache_backend, 'canvas_utilities_capabilities', [
      'config:core.extension',
    ]);
  }

}
