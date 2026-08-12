<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Plugin\IconSource;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Extracts raw SVG candidates from an uploaded source.
 */
interface IconSourceInterface extends PluginInspectionInterface {

  /**
   * Extracts candidates without persisting files or configuration.
   *
   * @param \Symfony\Component\HttpFoundation\File\UploadedFile[] $uploads
   *   Uploaded source files.
   *
   * @return array<int, array{id: string, label: string, group: string, svg: string}>
   *   Raw SVG candidates.
   */
  public function extract(array $uploads): array;

}
