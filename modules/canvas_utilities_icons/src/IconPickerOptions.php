<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons;

use Drupal\canvas_utilities_icons\Entity\IconLibrary;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Builds client-safe options for Canvas icon picker widgets.
 */
final class IconPickerOptions {

  /**
   * Constructs the icon picker options service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Returns enabled icon libraries for a theme.
   *
   * @return array<int, array{id: string, label: string, icons: array<int, array{id: string, label: string, group: string, value: string}>}>
   *   Picker libraries and icons in display order.
   */
  public function forTheme(string $theme): array {
    $libraries = [];
    foreach ($this->entityTypeManager->getStorage('canvas_utilities_icon_lib')->loadMultiple() as $entity) {
      if (!$entity instanceof IconLibrary || !$entity->status() || $entity->getTheme() !== $theme) {
        continue;
      }
      $libraries[] = $entity;
    }
    usort($libraries, static fn (IconLibrary $first, IconLibrary $second): int => strnatcasecmp((string) $first->label(), (string) $second->label()));

    return array_map(function (IconLibrary $library): array {
      $icons = [];
      foreach ($library->getIcons() as $icon) {
        $icons[] = [
          'id' => $icon['id'],
          'label' => $icon['label'],
          'group' => $icon['group'],
          'value' => $this->fileUrlGenerator->generateString($icon['uri']),
        ];
      }
      usort($icons, static fn (array $first, array $second): int => strnatcasecmp($first['label'], $second['label']));
      return [
        'id' => $library->id(),
        'label' => (string) $library->label(),
        'icons' => $icons,
      ];
    }, $libraries);
  }

}
