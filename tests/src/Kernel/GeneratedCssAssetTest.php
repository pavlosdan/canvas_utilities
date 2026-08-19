<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities\Kernel;

use Drupal\canvas_utilities\GeneratedCss\GeneratedCssAssetManager;
use Drupal\canvas_utilities\GeneratedCss\GeneratedCssProviderRegistry;
use Drupal\canvas_utilities_custom_css\Entity\CustomCssOverride;
use Drupal\canvas_utilities_style_guide\Entity\StyleGuideDefinition;
use Drupal\Tests\canvas\Kernel\CanvasKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests content-addressed generated CSS assets.
 */
#[Group('canvas_utilities')]
#[RunTestsInSeparateProcesses]
final class GeneratedCssAssetTest extends CanvasKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    ...self::CANVAS_KERNEL_TEST_MINIMAL_MODULES,
    'canvas_utilities',
    'canvas_utilities_custom_css',
    'canvas_utilities_style_guide',
  ];

  /**
   * Tests that changed Custom CSS receives a new immutable URL.
   */
  public function testCustomCssUsesContentAddressedFiles(): void {
    $registry = $this->container->get(GeneratedCssProviderRegistry::class);
    self::assertInstanceOf(GeneratedCssProviderRegistry::class, $registry);
    $providers = $registry->all();
    self::assertArrayHasKey('custom-css', $providers);

    $manager = $this->container->get(GeneratedCssAssetManager::class);
    self::assertInstanceOf(GeneratedCssAssetManager::class, $manager);
    self::assertNull($manager->getAsset($providers['custom-css'], 'stark'));

    $override = CustomCssOverride::create([
      'id' => 'stark',
      'label' => 'Stark custom CSS',
      'status' => TRUE,
      'theme' => 'stark',
      'css' => ':root { --brand: #123456; }',
    ]);
    $override->save();

    $first = $manager->getAsset($providers['custom-css'], 'stark');
    self::assertNotNull($first);
    self::assertFileExists($first->uri);
    self::assertSame(':root { --brand: #123456; }' . "\n", file_get_contents($first->uri));

    $override->set('css', ':root { --brand: #abcdef; }')->save();
    $second = $manager->getAsset($providers['custom-css'], 'stark');
    self::assertNotNull($second);
    self::assertNotSame($first->uri, $second->uri);
    self::assertFileExists($first->uri);
    self::assertFileExists($second->uri);
    self::assertSame(':root { --brand: #abcdef; }' . "\n", file_get_contents($second->uri));
  }

  /**
   * Tests that Style Guide definitions produce invalidated immutable assets.
   */
  public function testStyleGuideUsesContentAddressedFiles(): void {
    $registry = $this->container->get(GeneratedCssProviderRegistry::class);
    self::assertInstanceOf(GeneratedCssProviderRegistry::class, $registry);
    $providers = $registry->all();
    self::assertSame(['style-guide', 'custom-css'], array_keys($providers));

    $manager = $this->container->get(GeneratedCssAssetManager::class);
    self::assertInstanceOf(GeneratedCssAssetManager::class, $manager);
    self::assertNull($manager->getAsset($providers['style-guide'], 'stark'));

    $definition = StyleGuideDefinition::create([
      'id' => 'stark_brand',
      'label' => 'Stark brand',
      'status' => TRUE,
      'theme' => 'stark',
      'guide_id' => 'brand',
      'preview' => ['path' => '/'],
      'contexts' => [
        'default' => [
          'label' => 'Default',
          'selector' => ':root',
        ],
      ],
      'groups' => [
        'colors' => [
          'label' => 'Colors',
          'weight' => 0,
          'controls' => [
            'brand' => [
              'label' => 'Brand',
              'description' => '',
              'type' => 'color',
              'targets' => [
                'default' => ['property' => '--brand'],
              ],
              'default' => [
                'default' => '#123456',
              ],
              'constraints' => [],
            ],
          ],
        ],
      ],
    ]);
    $definition->save();

    $first = $manager->getAsset($providers['style-guide'], 'stark');
    self::assertNotNull($first);
    self::assertFileExists($first->uri);
    self::assertStringContainsString('--brand: #123456;', (string) file_get_contents($first->uri));

    $definition->set('groups', [
      'colors' => [
        'label' => 'Colors',
        'weight' => 0,
        'controls' => [
          'brand' => [
            'label' => 'Brand',
            'description' => '',
            'type' => 'color',
            'targets' => [
              'default' => ['property' => '--brand'],
            ],
            'default' => [
              'default' => '#abcdef',
            ],
            'constraints' => [],
          ],
        ],
      ],
    ])->save();

    $second = $manager->getAsset($providers['style-guide'], 'stark');
    self::assertNotNull($second);
    self::assertNotSame($first->uri, $second->uri);
    self::assertFileExists($first->uri);
    self::assertFileExists($second->uri);
    self::assertStringContainsString('--brand: #abcdef;', (string) file_get_contents($second->uri));
  }

}
