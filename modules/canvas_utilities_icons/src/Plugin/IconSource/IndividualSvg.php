<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Plugin\IconSource;

use Drupal\canvas_utilities_icons\Attribute\IconSource;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Extracts one or more individual SVG uploads.
 */
#[IconSource(
  id: 'individual_svg',
  label: new TranslatableMarkup('Individual SVGs'),
  description: new TranslatableMarkup('One or more standalone SVG files.'),
  multiple: TRUE,
)]
final class IndividualSvg extends IconSourceBase {

  /**
   * {@inheritdoc} */
  public function extract(array $uploads): array {
    if ($uploads === [] || count($uploads) > 100) {
      throw new \InvalidArgumentException('Upload between 1 and 100 SVG files.');
    }
    $candidates = [];
    foreach ($uploads as $upload) {
      if (!$upload->isValid() || strtolower($upload->getClientOriginalExtension()) !== 'svg' || $upload->getSize() > 1024 * 1024) {
        throw new \InvalidArgumentException('Every source must be a valid SVG no larger than 1 MB.');
      }
      $svg = file_get_contents($upload->getRealPath());
      if ($svg === FALSE) {
        throw new \InvalidArgumentException('An uploaded SVG could not be read.');
      }
      $name = pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME);
      $candidates[] = ['id' => $this->iconId($name), 'label' => $name, 'group' => '', 'svg' => $svg];
    }
    return $candidates;
  }

}
