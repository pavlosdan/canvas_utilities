<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_palette;

use Drupal\canvas_utilities_palette\Entity\Palette;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Builds client-safe options for Canvas color picker widgets.
 */
final class PalettePickerOptions {

  /**
   * Constructs the palette picker options service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns enabled palette colors for a theme.
   *
   * @return array<int, array{palette: string, label: string, value: string, preview: string}>
   *   Picker options in display order.
   */
  public function forTheme(string $theme): array {
    $palettes = [];
    foreach ($this->entityTypeManager->getStorage('canvas_utilities_palette')->loadMultiple() as $entity) {
      if (!$entity instanceof Palette || !$entity->status() || $entity->getTheme() !== $theme) {
        continue;
      }
      $palettes[] = $entity;
    }
    usort($palettes, static function (Palette $first, Palette $second): int {
      $first_data = $first->toClientArray();
      $second_data = $second->toClientArray();
      return [$first_data['weight'], $first->label()] <=> [$second_data['weight'], $second->label()];
    });

    $options = [];
    foreach ($palettes as $palette) {
      // Gradients are deliberately absent. A component color prop feeds
      // properties such as `color` and `border-color`, which accept a
      // `<color>`; a gradient is an `<image>` and would be dropped as invalid,
      // producing a silently unstyled component rather than a visible error.
      // @see \Drupal\canvas_utilities_palette\Color\CssGradient
      foreach ($palette->getSolidColors() as $color) {
        $options[] = [
          'palette' => (string) $palette->label(),
          'label' => $color['label'],
          'value' => sprintf('var(--%s-%s)', $palette->getPrefix(), $color['id']),
          'preview' => $color['value'],
        ];
      }
    }
    return $options;
  }

}
