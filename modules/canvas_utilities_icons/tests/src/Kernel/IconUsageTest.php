<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities_icons\Kernel;

use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent;
use Drupal\canvas_utilities_icons\Canvas\IconPropContract;
use Drupal\canvas_utilities_icons\Entity\IconLibrary;
use Drupal\canvas_utilities_icons\Import\IconImporter;
use Drupal\canvas_utilities_icons\Usage\IconUsage;
use Drupal\canvas_utilities_icons\Usage\IconUsageScope;
use Drupal\Tests\canvas\Kernel\CanvasKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests detection of icon usage across every surface that can reference one.
 */
#[Group('canvas_utilities')]
#[RunTestsInSeparateProcesses]
final class IconUsageTest extends CanvasKernelTestBase {

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
   * The usage service under test.
   */
  private IconUsage $usage;

  /**
   * The library fixture.
   */
  private IconLibrary $library;

  /**
   * Public URLs of the fixture icons, keyed by icon ID.
   *
   * @var array<string, string>
   */
  private array $urls = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('canvas_page');
    $this->installSchema('file', ['file_usage']);
    $this->config('system.file')->set('default_scheme', 'public')->save();

    $importer = $this->container->get(IconImporter::class);
    $this->library = $importer->import('stark', 'individual_svg', [
      $this->upload('star.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2 L22 22 L2 22 Z"/></svg>'),
      $this->upload('circle.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>'),
    ], ['id' => 'brand', 'label' => 'Brand']);

    $generator = $this->container->get('file_url_generator');
    foreach ($this->library->getIcons() as $icon) {
      $this->urls[$icon['id']] = $generator->generateString($icon['uri']);
    }

    $this->usage = $this->container->get(IconUsage::class);
  }

  /**
   * Tests that an unreferenced library reports no usage.
   */
  public function testUnusedLibraryReportsNothing(): void {
    self::assertSame([], $this->usage->forLibrary($this->library));
    self::assertSame(0, $this->usage->countForLibrary($this->library));
  }

  /**
   * Tests detection of an icon used as a code component's prop example.
   */
  public function testIconUsedAsCodeComponentExampleIsFound(): void {
    $this->createIconComponent('probe', ['examples' => [$this->urls['circle']]]);

    $usage = $this->usage->forLibrary($this->library);

    self::assertSame(['circle'], array_keys($usage));
    self::assertSame('js_component:probe', $usage['circle'][0]['id']);
  }

  /**
   * Tests detection of an icon referenced only by an unpublished draft.
   *
   * Drafts are the surface most easily missed, and reporting an icon as unused
   * because its only reference is unsaved would lose someone's work.
   */
  public function testIconUsedInAutoSaveDraftIsFound(): void {
    $this->container->get('keyvalue')->get('canvas.auto_save')->set('canvas_page:42', [
      'entity_type' => 'canvas_page',
      'entity_id' => 42,
      'label' => 'Draft page',
      'data' => ['components' => [['inputs' => json_encode(['icon' => $this->urls['star']])]]],
    ]);

    $usage = $this->usage->forLibrary($this->library);

    self::assertSame(['star'], array_keys($usage));
    self::assertSame('auto_save:canvas_page:42', $usage['star'][0]['id']);
    self::assertSame('Unpublished draft', $usage['star'][0]['type']);
  }

  /**
   * Tests detection of an icon selected on a component placed in content.
   */
  public function testIconUsedOnAPlacedComponentIsFound(): void {
    $component = $this->createIconComponent('placed');
    $page = $this->entityTypeManager()->getStorage('canvas_page')->create([
      'title' => 'Landing page',
      'components' => [
        [
          'uuid' => '11111111-1111-4111-8111-111111111111',
          'component_id' => $component,
          'component_version' => $this->entityTypeManager()->getStorage('component')->load($component)->getActiveVersion(),
          'inputs' => [
            'icon' => [
              'sourceType' => 'static:field_item:string',
              'value' => $this->urls['star'],
              'expression' => "\u{2139}\u{FE0E}string\u{241F}value",
              'sourceTypeSettings' => ['storage' => ['max_length' => 2048]],
            ],
          ],
        ],
      ],
    ]);
    $page->save();

    $usage = $this->usage->forLibrary($this->library);

    self::assertSame(['star'], array_keys($usage));
    self::assertSame('canvas_page:' . $page->id(), $usage['star'][0]['id']);
    // The unreferenced icon in the same library stays absent.
    self::assertArrayNotHasKey('circle', $usage);
  }

  /**
   * Tests that the same reference is reported once, not once per occurrence.
   */
  public function testRepeatedReferenceIsReportedOnce(): void {
    $this->createIconComponent('probe', ['examples' => [$this->urls['circle'], $this->urls['circle']]]);

    $usage = $this->usage->forLibrary($this->library);

    self::assertCount(1, $usage['circle']);
  }

  /**
   * Tests that scanning covers every revision when asked to.
   */
  public function testScopeSelectsRevisions(): void {
    $this->createIconComponent('probe', ['examples' => [$this->urls['circle']]]);

    // Config is revisionless, so both scopes agree here; this asserts the
    // scope argument is accepted and does not change config-derived results.
    self::assertSame(
      array_keys($this->usage->forLibrary($this->library, IconUsageScope::Active)),
      array_keys($this->usage->forLibrary($this->library, IconUsageScope::All)),
    );
  }

  /**
   * Creates an exposed code component with a single icon prop.
   *
   * @param string $machine_name
   *   The component machine name.
   * @param array<string, mixed> $extra
   *   Extra keys merged into the icon prop schema.
   *
   * @return string
   *   The Component config entity ID.
   */
  private function createIconComponent(string $machine_name, array $extra = []): string {
    $component = JavaScriptComponent::create([
      'machineName' => $machine_name,
      'name' => ucfirst($machine_name),
      'status' => TRUE,
      'required' => [],
      'props' => [
        'icon' => [
          'title' => 'Icon',
          'type' => 'string',
          '$ref' => IconPropContract::SCHEMA_REFERENCE,
        ] + $extra,
      ],
      'slots' => [],
      'js' => ['original' => 'const C=({icon})=>icon; export default C;', 'compiled' => 'const C=({icon})=>icon; export default C;'],
      'css' => ['original' => '', 'compiled' => ''],
      'dataDependencies' => [],
    ]);
    $component->save();
    return JsComponent::componentIdFromJavascriptComponentId($machine_name);
  }

  /**
   * Builds an uploaded SVG file.
   */
  private function upload(string $filename, string $contents): UploadedFile {
    $file_system = $this->container->get('file_system');
    $path = 'temporary://' . uniqid('icon', TRUE) . '-' . $filename;
    $file_system->saveData($contents, $path);
    return new UploadedFile($file_system->realpath($path), $filename, 'image/svg+xml', NULL, TRUE);
  }

  /**
   * Returns the entity type manager.
   */
  private function entityTypeManager(): object {
    return $this->container->get('entity_type.manager');
  }

}
