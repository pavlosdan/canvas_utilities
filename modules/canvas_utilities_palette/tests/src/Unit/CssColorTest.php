<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities_palette\Unit;

use Drupal\canvas_utilities_palette\Color\CssColor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests CSS color validation for Canvas color props.
 */
#[Group('canvas_utilities')]
final class CssColorTest extends UnitTestCase {

  /**
   * Tests accepted and rejected CSS color values.
   */
  #[DataProvider('values')]
  public function testValidation(string $value, bool $expected): void {
    self::assertSame($expected, CssColor::isValid($value));
  }

  /**
   * Provides CSS color values and expected results.
   *
   * @return iterable<string, array{string, bool}>
   *   Test cases.
   */
  public static function values(): iterable {
    yield 'six digit hex' => ['#3366ff', TRUE];
    yield 'hex with alpha' => ['#3366ff80', TRUE];
    yield 'short hex' => ['#fff', TRUE];
    yield 'named color' => ['rebeccapurple', TRUE];
    yield 'modern rgb' => ['rgb(51 102 255 / 80%)', TRUE];
    yield 'palette variable' => ['var(--brand-primary)', TRUE];
    yield 'color mix' => ['color-mix(in srgb, red 40%, blue)', TRUE];
    yield 'empty' => ['', FALSE];
    yield 'invalid hex' => ['#12', FALSE];
    yield 'declaration breakout' => ['rgb(0 0 0); color: red', FALSE];
    yield 'markup' => ['<script>alert(1)</script>', FALSE];
    yield 'unbalanced function' => ['rgb(0 0 0', FALSE];
  }

}
