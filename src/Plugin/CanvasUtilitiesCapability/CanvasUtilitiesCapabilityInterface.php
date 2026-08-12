<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\Plugin\CanvasUtilitiesCapability;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Describes a section contributed to the Design system workspace.
 */
interface CanvasUtilitiesCapabilityInterface extends PluginInspectionInterface {

  /**
   * Returns a client-safe capability representation.
   *
   * @return array{id: string, label: string, description: string, route: string, weight: int}
   *   The capability data.
   */
  public function toArray(): array;

  /**
   * Returns permissions of which the user must have at least one.
   *
   * @return string[]
   *   Permission names, or an empty array when base access is sufficient.
   */
  public function getPermissions(): array;

}
