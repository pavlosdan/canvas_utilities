<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_style_guide\Controller;

use Drupal\canvas_utilities_style_guide\StyleGuide\StyleGuideCssCompiler;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves compiled, published style-guide CSS.
 */
final class StyleGuideAssetController {

  /**
   * Constructs the style-guide asset controller.
   */
  public function __construct(private readonly StyleGuideCssCompiler $compiler) {}

  /**
   * Returns the published style-guide CSS for a theme.
   */
  public function __invoke(string $theme): Response {
    try {
      $css = $this->compiler->compile($theme);
    }
    catch (\InvalidArgumentException | \UnexpectedValueException) {
      return new Response('/* Canvas Utilities could not compile this theme. */', 404, ['Content-Type' => 'text/css; charset=UTF-8']);
    }
    $response = new Response($css, 200, ['Content-Type' => 'text/css; charset=UTF-8']);
    $response->setEtag(hash('sha256', $css));
    $response->setPublic();
    $response->setMaxAge(300);
    return $response;
  }

}
