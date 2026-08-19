<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\GeneratedCss;

/**
 * Collects generated CSS providers contributed by optional feature modules.
 */
final class GeneratedCssProviderRegistry {

  /**
   * Providers keyed by their stable ID.
   *
   * @var array<string, \Drupal\canvas_utilities\GeneratedCss\GeneratedCssProviderInterface>
   */
  private array $providers = [];

  /**
   * Adds a generated CSS provider from the service collector.
   */
  public function addProvider(GeneratedCssProviderInterface $provider): void {
    $this->providers[$provider->id()] = $provider;
  }

  /**
   * Returns providers sorted by output order and ID.
   *
   * @return \Drupal\canvas_utilities\GeneratedCss\GeneratedCssProviderInterface[]
   *   Ordered providers.
   */
  public function all(): array {
    $providers = $this->providers;
    uasort($providers, static fn (GeneratedCssProviderInterface $a, GeneratedCssProviderInterface $b): int => [$a->getWeight(), $a->id()] <=> [$b->getWeight(), $b->id()]);
    return $providers;
  }

}
