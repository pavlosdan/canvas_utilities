<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_custom_css\Controller;

use Drupal\canvas_utilities_custom_css\Entity\CustomCssOverride;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delivers a published custom CSS override.
 */
final class StylesheetController {

  /**
   * Constructs the controller. */
  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Builds a cacheable stylesheet response. */
  public function build(string $theme, string $fingerprint): Response {
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_css')->load($theme);
    if (!$entity instanceof CustomCssOverride || !$entity->status() || $entity->getFingerprint() !== $fingerprint) {
      return new CacheableResponse('', 404, ['Content-Type' => 'text/css; charset=UTF-8']);
    }
    $response = new CacheableResponse($entity->getCss() . "\n", 200, ['Content-Type' => 'text/css; charset=UTF-8']);
    $response->addCacheableDependency($entity);
    return $response;
  }

}
