<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests access to the Canvas Utilities bootstrap API.
 */
#[Group('canvas_utilities')]
#[RunTestsInSeparateProcesses]
final class BootstrapApiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['canvas', 'canvas_utilities'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the permission boundary and response shape.
   */
  public function testBootstrapAccess(): void {
    $this->drupalGet('/canvas-utilities/api/v1/bootstrap');
    $this->assertSession()->statusCodeEquals(403);

    $account = $this->drupalCreateUser(['access canvas utilities']);
    $this->drupalLogin($account);
    $this->drupalGet('/canvas-utilities/api/v1/bootstrap');
    $this->assertSession()->statusCodeEquals(200);

    $data = json_decode($this->getSession()->getPage()->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
    self::assertSame('1.0', $data['apiVersion']);
    self::assertSame('stark', $data['activeTheme']);
    self::assertFalse($data['permissions']['publish']);
    self::assertSame('direct', $data['reviewWorkflow']['adapter']);
    self::assertFalse($data['reviewWorkflow']['supportsStaging']);
  }

}
