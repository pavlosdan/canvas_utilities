<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\PendingChanges;

/**
 * Describes how Canvas Utilities changes participate in review workflows.
 */
interface PendingChangesAdapterInterface {

  /**
   * Returns whether the current adapter can stage changes for Canvas review. */
  public function supportsStaging(): bool;

  /**
   * Returns the stable adapter ID. */
  public function id(): string;

  /**
   * Returns a user-facing explanation of the active behavior. */
  public function description(): string;

}
