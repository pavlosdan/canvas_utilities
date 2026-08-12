<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities_icons\Unit;

use Drupal\canvas_utilities_icons\Plugin\IconSource\SvgSprite;
use Drupal\canvas_utilities_icons\Plugin\IconSource\SvgZip;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests bounded archive and sprite extraction.
 */
#[Group('canvas_utilities')]
final class IconSourceProviderTest extends UnitTestCase {

  /**
   * Temporary paths created by a test.
   *
   * @var string[]
   */
  private array $paths = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->paths as $path) {
      if (file_exists($path)) {
        unlink($path);
      }
    }
    parent::tearDown();
  }

  /**
   * Tests valid SVG-only archives.
   */
  public function testZipExtraction(): void {
    $path = $this->zip(['group/check.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1"/></svg>']);
    $icons = (new SvgZip([], 'svg_zip', []))->extract([$this->upload($path, 'icons.zip')]);
    self::assertSame('check', $icons[0]['id']);
    self::assertSame('group', $icons[0]['group']);
  }

  /**
   * Tests archive traversal is rejected.
   */
  public function testZipRejectsTraversal(): void {
    $path = $this->zip(['../escape.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>']);
    $this->expectException(\InvalidArgumentException::class);
    (new SvgZip([], 'svg_zip', []))->extract([$this->upload($path, 'icons.zip')]);
  }

  /**
   * Tests archive expansion-ratio limits.
   */
  public function testZipRejectsHighCompressionRatio(): void {
    $path = $this->zip(['large.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><!--' . str_repeat('A', 200000) . '--></svg>']);
    $this->expectException(\InvalidArgumentException::class);
    (new SvgZip([], 'svg_zip', []))->extract([$this->upload($path, 'icons.zip')]);
  }

  /**
   * Tests sprite symbol extraction.
   */
  public function testSpriteExtraction(): void {
    $path = $this->temporaryPath('svg');
    file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><symbol id="check" viewBox="0 0 16 16"><path d="M0 0h1"/></symbol></svg>');
    $icons = (new SvgSprite([], 'svg_sprite', []))->extract([$this->upload($path, 'sprite.svg')]);
    self::assertSame('check', $icons[0]['id']);
    self::assertStringContainsString('viewBox="0 0 16 16"', $icons[0]['svg']);
  }

  /**
   * Creates a temporary ZIP file.
   *
   * @param array<string, string> $entries
   *   Archive entries keyed by path.
   */
  private function zip(array $entries): string {
    $path = $this->temporaryPath('zip');
    $zip = new \ZipArchive();
    self::assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
    foreach ($entries as $name => $contents) {
      self::assertTrue($zip->addFromString($name, $contents));
    }
    $zip->close();
    return $path;
  }

  /**
   * Creates a tracked temporary path.
   */
  private function temporaryPath(string $extension): string {
    $base = tempnam(sys_get_temp_dir(), 'cu-test-');
    if ($base === FALSE) {
      throw new \RuntimeException('Unable to create a temporary file.');
    }
    $path = $base . '.' . $extension;
    rename($base, $path);
    $this->paths[] = $path;
    return $path;
  }

  /**
   * Creates a test-mode uploaded file.
   */
  private function upload(string $path, string $name): UploadedFile {
    return new UploadedFile($path, $name, NULL, UPLOAD_ERR_OK, TRUE);
  }

}
