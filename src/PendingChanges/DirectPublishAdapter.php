<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\PendingChanges;

/**
 * Publishes through Canvas Utilities' conflict-aware feature APIs.
 */
final class DirectPublishAdapter implements PendingChangesAdapterInterface {

  /**
   * {@inheritdoc} */
  public function supportsStaging(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc} */
  public function id(): string {
    return 'direct';
  }

  /**
   * {@inheritdoc} */
  public function description(): string {
    return 'Changes publish directly from this workspace. Canvas pending-changes integration is not available for this Canvas version.';
  }

}
