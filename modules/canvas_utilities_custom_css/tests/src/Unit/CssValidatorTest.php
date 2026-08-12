<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities_custom_css\Unit;

use Drupal\canvas_utilities_custom_css\CssValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests custom CSS validation.
 */
#[Group('canvas_utilities')]
final class CssValidatorTest extends UnitTestCase {

  /**
   * Tests ordinary CSS is normalized. */
  public function testAcceptsCss(): void {
    self::assertSame(':root {' . "\n" . '  --brand: #123456;' . "\n" . '}', (new CssValidator())->validate(":root {\r\n  --brand: #123456;\r\n}\r\n"));
  }

  /**
   * Tests imports are rejected. */
  public function testRejectsImport(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new CssValidator())->validate('@import url("https://example.com/a.css");');
  }

  /**
   * Tests unbalanced blocks are rejected. */
  public function testRejectsUnbalancedBlocks(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new CssValidator())->validate(':root { color: red;');
  }

  /**
   * Tests script URLs are rejected. */
  public function testRejectsScriptUrl(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new CssValidator())->validate('a { background: url(javascript:alert(1)); }');
  }

  /**
   * Tests remote assets are rejected. */
  public function testRejectsRemoteAsset(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new CssValidator())->validate('a { background: url(https://example.com/image.png); }');
  }

}
