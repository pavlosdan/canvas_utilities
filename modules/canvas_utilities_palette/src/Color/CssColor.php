<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_palette\Color;

/**
 * Validates CSS color values accepted by the Canvas color picker.
 */
final class CssColor {

  /**
   * Tests whether a value is a safe CSS color token.
   */
  public static function isValid(string $value): bool {
    $value = trim($value);
    if ($value === '' || strlen($value) > 255) {
      return FALSE;
    }

    if (preg_match('/^#[0-9a-f]{3,4}(?:[0-9a-f]{2}){0,2}$/i', $value) === 1) {
      return TRUE;
    }

    if (preg_match('/^[a-z]+(?:-[a-z]+)*$/i', $value) === 1) {
      return TRUE;
    }

    if (preg_match('/^(?:rgb|rgba|hsl|hsla|hwb|lab|lch|oklab|oklch|color|color-mix|light-dark|var)\((?!.*[;{}<>])[^\r\n]+\)$/i', $value) !== 1) {
      return FALSE;
    }

    $depth = 0;
    foreach (str_split($value) as $character) {
      if ($character === '(') {
        $depth++;
      }
      elseif ($character === ')' && --$depth < 0) {
        return FALSE;
      }
    }
    return $depth === 0;
  }

}
