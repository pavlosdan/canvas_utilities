<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities_icons\Kernel;

use Drupal\canvas_utilities_icons\Import\IconImporter;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\canvas\Kernel\CanvasKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests how the icon picker widget hands its options to the browser.
 */
#[Group('canvas_utilities')]
#[RunTestsInSeparateProcesses]
final class IconPickerWidgetTest extends CanvasKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    ...self::CANVAS_KERNEL_TEST_MINIMAL_MODULES,
    'field',
    'file',
    'entity_test',
    'canvas_utilities',
    'canvas_utilities_icons',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('entity_test');
    $this->installSchema('file', ['file_usage']);
    $this->config('system.file')->set('default_scheme', 'public')->save();
    $this->config('system.theme')->set('default', 'stark')->save();

    FieldStorageConfig::create([
      'field_name' => 'field_icon',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'settings' => ['max_length' => 2048],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_icon',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Tests that the options travel on the element, not in drupalSettings.
   *
   * Drupal merges the settings of an AJAX response into the browser's existing
   * drupalSettings recursively, combining arrays index by index rather than
   * replacing them. A list of libraries that has grown shorter therefore keeps
   * its removed entries, so a deleted library stayed selectable in the picker
   * until the page was fully reloaded — and no server-side cache clear could
   * fix it. Keeping the options on the element means every freshly rendered
   * form carries exactly the libraries that exist at that moment.
   */
  public function testOptionsAreCarriedOnTheElementRatherThanInDrupalSettings(): void {
    $this->importLibrary('keep', 'Keep');
    $this->importLibrary('doomed', 'Doomed');

    $element = $this->buildWidgetElement();

    self::assertArrayNotHasKey(
      'drupalSettings',
      $element['value']['#attached'],
      'The widget must not publish its options through drupalSettings.',
    );
    self::assertSame(
      ['Doomed', 'Keep'],
      $this->labelsOn($element),
      'Both libraries are offered while both exist.',
    );
  }

  /**
   * Tests that a deleted library disappears from the next rendered element.
   */
  public function testDeletedLibraryIsAbsentFromTheNextRender(): void {
    $this->importLibrary('keep', 'Keep');
    $doomed = $this->importLibrary('doomed', 'Doomed');

    self::assertSame(['Doomed', 'Keep'], $this->labelsOn($this->buildWidgetElement()));

    $doomed->delete();

    self::assertSame(
      ['Keep'],
      $this->labelsOn($this->buildWidgetElement()),
      'A rebuilt element offers only the libraries that still exist.',
    );
  }

  /**
   * Tests that an emptied set of libraries produces an empty option list.
   *
   * This is the case a recursive merge could never express: going from some
   * libraries to none.
   */
  public function testDeletingEveryLibraryLeavesNoOptions(): void {
    $library = $this->importLibrary('only', 'Only');
    self::assertSame(['Only'], $this->labelsOn($this->buildWidgetElement()));

    $library->delete();

    self::assertSame([], $this->labelsOn($this->buildWidgetElement()));
  }

  /**
   * Builds the widget's form element for an empty icon field.
   *
   * @return array<string, mixed>
   *   The widget element.
   */
  private function buildWidgetElement(): array {
    // The options service caches nothing, but the entity storage does.
    $this->container->get('entity_type.manager')
      ->getStorage('canvas_utilities_icon_lib')
      ->resetCache();

    $entity = EntityTest::create(['name' => 'Host']);
    $items = $entity->get('field_icon');

    $widget = $this->container->get('plugin.manager.field.widget')->createInstance(
      'canvas_utilities_icon_picker',
      [
        'field_definition' => $items->getFieldDefinition(),
        'settings' => [],
        'third_party_settings' => [],
      ],
    );

    $form = [];
    $form_state = new FormState();
    return $widget->formElement($items, 0, ['#parents' => ['field_icon', 0]], $form, $form_state);
  }

  /**
   * Reads the library labels the element offers to the browser.
   *
   * @param array<string, mixed> $element
   *   The widget element.
   *
   * @return list<string>
   *   Library labels, sorted.
   */
  private function labelsOn(array $element): array {
    $encoded = $element['value']['#attributes']['data-canvas-utilities-icon-libraries'] ?? NULL;
    self::assertIsString($encoded, 'The element carries its options as JSON.');
    $labels = array_column((array) Json::decode($encoded), 'label');
    sort($labels);
    return $labels;
  }

  /**
   * Imports a one-icon library.
   */
  private function importLibrary(string $id, string $label): object {
    $file_system = $this->container->get('file_system');
    $path = 'temporary://' . uniqid('icon', TRUE) . '.svg';
    $file_system->saveData(
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>',
      $path,
    );
    $upload = new UploadedFile($file_system->realpath($path), $id . '.svg', 'image/svg+xml', NULL, TRUE);
    return $this->container->get(IconImporter::class)
      ->import('stark', 'individual_svg', [$upload], ['id' => $id, 'label' => $label]);
  }

}
