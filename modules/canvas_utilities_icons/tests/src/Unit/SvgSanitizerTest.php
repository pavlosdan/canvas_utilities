<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities_icons\Unit;

use Drupal\canvas_utilities_icons\Import\SvgSanitizer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests SVG sanitization boundaries.
 */
#[Group('canvas_utilities')]
final class SvgSanitizerTest extends UnitTestCase {

  /**
   * Tests safe markup and internal ID namespacing. */
  public function testSanitizesAndNamespacesIds(): void {
    $source = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><defs><linearGradient id="paint"/></defs><path fill="url(#paint)" d="M0 0h1v1z"/></svg>';
    $result = (new SvgSanitizer())->sanitize($source, 'library-icon');
    self::assertSame('0 0 16 16', $result['viewBox']);
    self::assertStringContainsString('id="cu-library-icon-paint"', $result['svg']);
    self::assertStringContainsString('url(#cu-library-icon-paint)', $result['svg']);
  }

  /**
   * Tests unsafe document types are rejected. */
  public function testRejectsDocumentType(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new SvgSanitizer())->sanitize('<!DOCTYPE svg><svg xmlns="http://www.w3.org/2000/svg"/>', 'test');
  }

  /**
   * Tests external references are rejected. */
  public function testRejectsExternalReference(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new SvgSanitizer())->sanitize('<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.com/a.png"/></svg>', 'test');
  }

  /**
   * Tests active markup is removed before storage. */
  public function testRemovesActiveMarkup(): void {
    $result = (new SvgSanitizer())->sanitize('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><path d="M0 0h1"/></svg>', 'test');
    self::assertStringNotContainsString('onload', $result['svg']);
    self::assertStringNotContainsString('<script', $result['svg']);
  }

}
