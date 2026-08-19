<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\GeneratedCss;

/**
 * Immutable descriptor for a materialized generated CSS asset.
 */
final class GeneratedCssAsset {

  /**
   * Constructs a generated CSS asset descriptor.
   */
  public function __construct(
    public readonly string $providerId,
    public readonly string $theme,
    public readonly string $fingerprint,
    public readonly string $uri,
  ) {}

}
