<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_custom_css;

use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Parsing\SourceException;

/**
 * Enforces conservative boundaries for administrator-authored CSS.
 */
final class CssValidator {

  /**
   * Validates and normalizes a CSS document. */
  public function validate(string $css): string {
    $css = str_replace(["\r\n", "\r"], "\n", trim($css));
    if (strlen($css) > 100 * 1024) {
      throw new \InvalidArgumentException('Custom CSS may not exceed 100 KB.');
    }
    if (preg_match('/@import\b/i', $css)) {
      throw new \InvalidArgumentException('@import is not supported. Add remote assets through the appropriate feature instead.');
    }
    if (preg_match('/<\/?style\b|<script\b|javascript\s*:/i', $css)) {
      throw new \InvalidArgumentException('The CSS contains forbidden markup or a script URL.');
    }
    if (preg_match('/url\(\s*["\']?\s*(?:https?:|\/\/|data:)/i', $css)) {
      throw new \InvalidArgumentException('External and data URLs are not supported in custom CSS.');
    }
    try {
      (new Parser($css))->parse();
    }
    catch (SourceException $exception) {
      throw new \InvalidArgumentException('The CSS could not be parsed: ' . $exception->getMessage(), previous: $exception);
    }
    if (substr_count($css, '{') !== substr_count($css, '}')) {
      throw new \InvalidArgumentException('The CSS contains unbalanced block braces.');
    }
    return $css;
  }

}
