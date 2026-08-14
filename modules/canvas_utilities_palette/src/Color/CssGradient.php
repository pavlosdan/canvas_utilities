<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_palette\Color;

/**
 * Validates CSS gradient values accepted by palettes.
 *
 * A gradient is a CSS `<image>`, not a `<color>`. It is valid in
 * `background-image` and the `background` shorthand, and invalid in `color`,
 * `border-color` and every other property expecting a color. Palettes
 * therefore keep gradients as a distinct entry type rather than as another
 * kind of color value.
 *
 * @see \Drupal\canvas_utilities_palette\Color\CssColor
 */
final class CssGradient {

  /**
   * The gradient functions a palette entry may use.
   */
  private const FUNCTIONS = [
    'linear-gradient',
    'radial-gradient',
    'conic-gradient',
    'repeating-linear-gradient',
    'repeating-radial-gradient',
    'repeating-conic-gradient',
  ];

  /**
   * Tests whether a value is a safe CSS gradient token.
   *
   * Values are emitted verbatim into a stylesheet, so anything that could end
   * the declaration or the rule is rejected outright rather than escaped.
   */
  public static function isValid(string $value): bool {
    $value = trim($value);
    if ($value === '' || strlen($value) > 2048) {
      return FALSE;
    }
    // No character that could terminate the declaration, open a new rule, or
    // start a comment or markup.
    if (preg_match('/[;{}<>\r\n]|\/\*|\\\\/', $value) === 1) {
      return FALSE;
    }
    // A gradient may be a comma-separated list of layers; every layer must be
    // one of the permitted gradient functions.
    foreach (self::splitLayers($value) as $layer) {
      if (!self::isSingleGradient($layer)) {
        return FALSE;
      }
    }
    return self::isBalanced($value);
  }

  /**
   * Splits a value on the commas that separate whole background layers.
   *
   * Commas inside a function's parentheses belong to that function's
   * arguments, so only commas at nesting depth zero separate layers.
   *
   * @return list<string>
   *   The layers.
   */
  private static function splitLayers(string $value): array {
    $layers = [];
    $current = '';
    $depth = 0;
    foreach (str_split($value) as $character) {
      if ($character === '(') {
        $depth++;
      }
      elseif ($character === ')') {
        $depth--;
      }
      if ($character === ',' && $depth === 0) {
        $layers[] = $current;
        $current = '';
        continue;
      }
      $current .= $character;
    }
    $layers[] = $current;
    return array_values(array_filter(array_map('trim', $layers), static fn (string $layer): bool => $layer !== ''));
  }

  /**
   * Tests whether one layer is a permitted gradient function call.
   */
  private static function isSingleGradient(string $layer): bool {
    $pattern = '/^(?:' . implode('|', array_map('preg_quote', self::FUNCTIONS)) . ')\(.+\)$/i';
    return preg_match($pattern, $layer) === 1;
  }

  /**
   * Tests whether every parenthesis is closed in order.
   */
  private static function isBalanced(string $value): bool {
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
