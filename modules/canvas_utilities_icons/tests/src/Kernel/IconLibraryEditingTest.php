<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities_icons\Kernel;

use Drupal\canvas_utilities_icons\Entity\IconLibrary;
use Drupal\canvas_utilities_icons\Import\IconImporter;
use Drupal\Tests\canvas\Kernel\CanvasKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests editing an icon library after it has been imported.
 */
#[Group('canvas_utilities')]
#[RunTestsInSeparateProcesses]
final class IconLibraryEditingTest extends CanvasKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    ...self::CANVAS_KERNEL_TEST_MINIMAL_MODULES,
    'file',
    'canvas_utilities',
    'canvas_utilities_icons',
  ];

  /**
   * The importer under test.
   */
  private IconImporter $importer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installSchema('file', ['file_usage']);
    $this->config('system.file')->set('default_scheme', 'public')->save();
    $this->importer = $this->container->get(IconImporter::class);
  }

  /**
   * Tests that icons can be added to a library one upload at a time.
   */
  public function testIconsCanBeAddedToAnExistingLibrary(): void {
    $library = $this->importer->import('stark', 'individual_svg', [
      $this->upload('star.svg', $this->svg('M12 2 L22 22 L2 22 Z')),
    ], ['id' => 'brand', 'label' => 'Brand', 'prefix' => 'brand']);
    self::assertSame(['star'], array_keys($library->getIcons()));

    // A second, separate upload extends the same library.
    $report = $this->importer->addIcons($library, 'individual_svg', [
      $this->upload('circle.svg', $this->svg('M12 2 L22 22 L2 22 Z', 'circle')),
    ]);
    self::assertSame(['added' => ['circle'], 'replaced' => [], 'unchanged' => []], $report);
    self::assertSame(['star', 'circle'], array_keys($this->reload($library)->getIcons()));
  }

  /**
   * Tests that re-uploading the same file changes nothing.
   */
  public function testReuploadingIdenticalIconIsANoOp(): void {
    $library = $this->importer->import('stark', 'individual_svg', [
      $this->upload('star.svg', $this->svg('M12 2 L22 22 L2 22 Z')),
    ], ['id' => 'brand', 'label' => 'Brand']);
    $before = $this->reload($library)->getIcons()['star'];

    $report = $this->importer->addIcons($library, 'individual_svg', [
      $this->upload('star.svg', $this->svg('M12 2 L22 22 L2 22 Z')),
    ]);

    self::assertSame(['added' => [], 'replaced' => [], 'unchanged' => ['star']], $report);
    self::assertSame($before, $this->reload($library)->getIcons()['star']);
    self::assertCount(1, $this->fileStorage()->loadMultiple());
  }

  /**
   * Tests that changed content replaces the icon and releases the old file.
   */
  public function testChangedIconReplacesStoredFile(): void {
    $library = $this->importer->import('stark', 'individual_svg', [
      $this->upload('star.svg', $this->svg('M12 2 L22 22 L2 22 Z')),
    ], ['id' => 'brand', 'label' => 'Brand']);
    $original_uuid = $this->reload($library)->getIcons()['star']['file_uuid'];

    $report = $this->importer->addIcons($library, 'individual_svg', [
      $this->upload('star.svg', $this->svg('M12 3 L21 21 L3 21 Z')),
    ]);

    self::assertSame(['added' => [], 'replaced' => ['star'], 'unchanged' => []], $report);
    $icons = $this->reload($library)->getIcons();
    self::assertNotSame($original_uuid, $icons['star']['file_uuid']);
    // The superseded payload is not left behind.
    self::assertSame([], $this->fileStorage()->loadByProperties(['uuid' => $original_uuid]));
    self::assertCount(1, $this->fileStorage()->loadMultiple());
  }

  /**
   * Tests removing a single icon from a library.
   */
  public function testSingleIconCanBeRemoved(): void {
    $library = $this->importer->import('stark', 'individual_svg', [
      $this->upload('star.svg', $this->svg('M12 2 L22 22 L2 22 Z')),
      $this->upload('circle.svg', $this->svg('M12 2 L22 22 L2 22 Z', 'circle')),
    ], ['id' => 'brand', 'label' => 'Brand']);
    $removed_uuid = $this->reload($library)->getIcons()['star']['file_uuid'];

    $this->importer->removeIcon($library, 'star');

    self::assertSame(['circle'], array_keys($this->reload($library)->getIcons()));
    self::assertSame([], $this->fileStorage()->loadByProperties(['uuid' => $removed_uuid]));
  }

  /**
   * Tests that removing an unknown icon is rejected.
   */
  public function testRemovingUnknownIconFails(): void {
    $library = $this->importer->import('stark', 'individual_svg', [
      $this->upload('star.svg', $this->svg('M12 2 L22 22 L2 22 Z')),
    ], ['id' => 'brand', 'label' => 'Brand']);

    $this->expectException(\InvalidArgumentException::class);
    $this->importer->removeIcon($library, 'nope');
  }

  /**
   * Builds an uploaded SVG file.
   */
  private function upload(string $filename, string $contents): UploadedFile {
    $path = 'temporary://' . uniqid('icon', TRUE) . '-' . $filename;
    $file_system = $this->container->get('file_system');
    $file_system->saveData($contents, $path);
    return new UploadedFile($file_system->realpath($path), $filename, 'image/svg+xml', NULL, TRUE);
  }

  /**
   * Builds SVG markup with a distinguishable path.
   */
  private function svg(string $path, string $shape = 'path'): string {
    $body = $shape === 'circle'
      ? '<circle cx="12" cy="12" r="10"/>'
      : sprintf('<path d="%s"/>', $path);
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' . $body . '</svg>';
  }

  /**
   * Reloads a library from storage so saved values are asserted.
   */
  private function reload(IconLibrary $library): IconLibrary {
    $storage = $this->container->get('entity_type.manager')->getStorage('canvas_utilities_icon_lib');
    $storage->resetCache([$library->id()]);
    $reloaded = $storage->load($library->id());
    self::assertInstanceOf(IconLibrary::class, $reloaded);
    return $reloaded;
  }

  /**
   * Returns the file storage.
   */
  private function fileStorage(): object {
    return $this->container->get('entity_type.manager')->getStorage('file');
  }

}
