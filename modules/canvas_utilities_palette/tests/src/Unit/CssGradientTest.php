<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities_palette\Unit;

use Drupal\canvas_utilities_palette\Color\CssGradient;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests CSS gradient validation for palette entries.
 */
#[Group('canvas_utilities')]
final class CssGradientTest extends UnitTestCase {

  /**
   * Tests accepted and rejected CSS gradient values.
   */
  #[DataProvider('values')]
  public function testValidation(string $value, bool $expected): void {
    self::assertSame($expected, CssGradient::isValid($value));
  }

  /**
   * Provides CSS gradient values and expected results.
   *
   * @return iterable<string, array{string, bool}>
   *   Test cases.
   */
  public static function values(): iterable {
    yield 'linear' => ['linear-gradient(90deg, #3366ff 0%, #ff33aa 100%)', TRUE];
    yield 'radial' => ['radial-gradient(circle at 30% 30%, #fff, #000)', TRUE];
    yield 'conic' => ['conic-gradient(from 45deg, #fff, #000)', TRUE];
    yield 'repeating' => ['repeating-linear-gradient(45deg, #000 0 10px, #fff 10px 20px)', TRUE];
    yield 'named colors' => ['linear-gradient(to right, red, blue)', TRUE];
    yield 'color functions inside' => ['linear-gradient(90deg, oklch(70% 0.1 200), rgb(0 0 0 / 50%))', TRUE];
    yield 'several layers' => ['linear-gradient(#fff, #000), radial-gradient(#000, #fff)', TRUE];
    yield 'surrounding whitespace' => ['  linear-gradient(90deg, #fff, #000)  ', TRUE];

    yield 'empty' => ['', FALSE];
    yield 'a plain color is not a gradient' => ['#3366ff', FALSE];
    yield 'a named color is not a gradient' => ['rebeccapurple', FALSE];
    // A gradient is one kind of CSS image; the others can load remote assets.
    yield 'url image' => ['url(https://example.com/x.png)', FALSE];
    yield 'image-set' => ['image-set("a.png" 1x)', FALSE];
    // Anything that could end the declaration or open a new rule.
    yield 'declaration break' => ['linear-gradient(90deg, red, blue); color: red', FALSE];
    yield 'rule break' => ['linear-gradient(90deg, #fff, #000)} body{display:none', FALSE];
    yield 'comment' => ['linear-gradient(90deg, #fff, #000) /* x */', FALSE];
    yield 'markup' => ['linear-gradient(90deg, #fff, #000)</style>', FALSE];
    yield 'newline' => ["linear-gradient(90deg,\n#fff, #000)", FALSE];
    yield 'escape sequence' => ['linear-gradient(90deg, \\66 ff, #000)', FALSE];
    // Unbalanced parentheses could let a later declaration be absorbed.
    yield 'unclosed' => ['linear-gradient(90deg, #fff, #000', FALSE];
    yield 'extra close' => ['linear-gradient(90deg, #fff, #000))', FALSE];
    yield 'one bad layer' => ['linear-gradient(#fff, #000), url(x.png)', FALSE];
    yield 'too long' => ['linear-gradient(90deg, ' . str_repeat('#ffffff, ', 300) . '#000)', FALSE];
  }

}
