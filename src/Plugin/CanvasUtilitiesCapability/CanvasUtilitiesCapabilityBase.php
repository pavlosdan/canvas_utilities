<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\Plugin\CanvasUtilitiesCapability;

use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for Canvas Utilities capability plugins.
 */
abstract class CanvasUtilitiesCapabilityBase extends PluginBase implements CanvasUtilitiesCapabilityInterface {

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'id' => $this->getPluginId(),
      'label' => (string) $this->pluginDefinition['label'],
      'description' => (string) $this->pluginDefinition['description'],
      'route' => (string) $this->pluginDefinition['route'],
      'weight' => (int) $this->pluginDefinition['weight'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions(): array {
    return $this->pluginDefinition['permissions'];
  }

}
