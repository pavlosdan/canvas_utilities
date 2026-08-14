<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_utilities_palette\Kernel;

use Drupal\canvas_utilities_palette\Controller\PaletteAssetController;
use Drupal\canvas_utilities_palette\Entity\Palette;
use Drupal\canvas_utilities_palette\PalettePickerOptions;
use Drupal\Tests\canvas\Kernel\CanvasKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests how palettes treat gradient entries.
 */
#[Group('canvas_utilities')]
#[RunTestsInSeparateProcesses]
final class PaletteGradientTest extends CanvasKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    ...self::CANVAS_KERNEL_TEST_MINIMAL_MODULES,
    'canvas_utilities',
    'canvas_utilities_palette',
  ];

  /**
   * Tests that entries stored before gradients existed remain colors.
   *
   * Existing palettes have no `type` key at all. Treating an untyped entry as
   * anything other than a color would drop every color a site already uses.
   */
  public function testUntypedEntriesAreTreatedAsColors(): void {
    $palette = Palette::create([
      'id' => 'stark__legacy',
      'label' => 'Legacy',
      'status' => TRUE,
      'theme' => 'stark',
      'prefix' => 'legacy',
      'weight' => 0,
      'description' => '',
      // Deliberately without a `type` key.
      'colors' => ['primary' => ['id' => 'primary', 'label' => 'Primary', 'value' => '#3366ff', 'role' => '']],
    ]);
    $palette->save();

    $entries = $palette->getColors();
    self::assertSame(Palette::TYPE_COLOR, $entries['primary']['type']);
    self::assertCount(1, $palette->getSolidColors());
    self::assertCount(0, $palette->getGradients());
  }

  /**
   * Tests that gradients are kept out of the component color picker.
   *
   * A component color prop feeds properties expecting a `<color>`. A gradient
   * is an `<image>`, so offering one would produce a declaration the browser
   * discards, leaving the component silently unstyled.
   */
  public function testGradientsAreNotOfferedToColorProps(): void {
    $palette = Palette::create([
      'id' => 'stark__brand',
      'label' => 'Brand',
      'status' => TRUE,
      'theme' => 'stark',
      'prefix' => 'brand',
      'weight' => 0,
      'description' => '',
      'colors' => [
        'primary' => ['id' => 'primary', 'label' => 'Primary', 'value' => '#3366ff', 'role' => '', 'type' => Palette::TYPE_COLOR],
        'hero' => ['id' => 'hero', 'label' => 'Hero', 'value' => 'linear-gradient(90deg, #fff, #000)', 'role' => '', 'type' => Palette::TYPE_GRADIENT],
      ],
    ]);
    $palette->save();

    $options = $this->container->get(PalettePickerOptions::class)->forTheme('stark');

    self::assertSame(['Primary'], array_column($options, 'label'));
    self::assertSame(['var(--brand-primary)'], array_column($options, 'value'));

    // The gradient is still stored and still published as a CSS variable; it
    // is only withheld from the picker.
    self::assertArrayHasKey('hero', $palette->getGradients());
  }

  /**
   * Tests that gradients are published as CSS custom properties.
   */
  public function testGradientsArePublishedAsCssVariables(): void {
    Palette::create([
      'id' => 'stark__brand',
      'label' => 'Brand',
      'status' => TRUE,
      'theme' => 'stark',
      'prefix' => 'brand',
      'weight' => 0,
      'description' => '',
      'colors' => [
        'hero' => ['id' => 'hero', 'label' => 'Hero', 'value' => 'linear-gradient(90deg, #fff, #000)', 'role' => '', 'type' => Palette::TYPE_GRADIENT],
      ],
    ])->save();

    $css = (string) $this->container->get(PaletteAssetController::class)('stark')->getContent();

    self::assertStringContainsString('--brand-hero: linear-gradient(90deg, #fff, #000);', $css);
  }

}
