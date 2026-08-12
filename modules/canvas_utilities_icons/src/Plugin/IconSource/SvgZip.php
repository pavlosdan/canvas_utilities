<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Plugin\IconSource;

use Drupal\canvas_utilities_icons\Attribute\IconSource;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Reads SVG candidates from a bounded ZIP archive without extracting it.
 */
#[IconSource(
  id: 'svg_zip',
  label: new TranslatableMarkup('SVG ZIP archive'),
  description: new TranslatableMarkup('A ZIP archive containing SVG files.'),
)]
final class SvgZip extends IconSourceBase {

  private const int MAX_FILES = 500;
  private const int MAX_UNCOMPRESSED = 25 * 1024 * 1024;

  /**
   * {@inheritdoc} */
  public function extract(array $uploads): array {
    $upload = $uploads[0] ?? NULL;
    if (!$upload instanceof UploadedFile || count($uploads) !== 1 || !$upload->isValid() || strtolower($upload->getClientOriginalExtension()) !== 'zip' || $upload->getSize() > 10 * 1024 * 1024) {
      throw new \InvalidArgumentException('Upload one valid ZIP archive no larger than 10 MB.');
    }
    $zip = new \ZipArchive();
    if ($zip->open($upload->getRealPath()) !== TRUE || $zip->numFiles > self::MAX_FILES) {
      throw new \InvalidArgumentException('The ZIP is invalid or contains too many files.');
    }
    $total = 0;
    $candidates = [];
    try {
      for ($index = 0; $index < $zip->numFiles; $index++) {
        $stat = $zip->statIndex($index);
        if (!is_array($stat)) {
          throw new \InvalidArgumentException('A ZIP entry could not be read.');
        }
        $name = str_replace('\\', '/', (string) $stat['name']);
        if ($name === '' || str_starts_with($name, '/') || str_contains('/' . $name . '/', '/../') || strlen($name) > 240 || str_ends_with($name, '/')) {
          throw new \InvalidArgumentException('The ZIP contains an unsafe path or unsupported directory entry.');
        }
        $operating_system = 0;
        $attributes = 0;
        if ($zip->getExternalAttributesIndex($index, $operating_system, $attributes)
          && $operating_system === \ZipArchive::OPSYS_UNIX
          && (($attributes >> 16) & 0xF000) === 0xA000) {
          throw new \InvalidArgumentException('The ZIP may not contain symbolic links.');
        }
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'svg') {
          throw new \InvalidArgumentException('ZIP archives may contain SVG files only.');
        }
        $size = (int) $stat['size'];
        $compressed = max(1, (int) $stat['comp_size']);
        $total += $size;
        if ($total > self::MAX_UNCOMPRESSED || $size > 1024 * 1024 || $size / $compressed > 100) {
          throw new \InvalidArgumentException('The ZIP exceeds safe expansion limits.');
        }
        $svg = $zip->getFromIndex($index);
        if (!is_string($svg)) {
          throw new \InvalidArgumentException('A ZIP entry could not be read.');
        }
        $base = pathinfo($name, PATHINFO_FILENAME);
        $group = dirname($name) === '.' ? '' : dirname($name);
        $candidates[] = ['id' => $this->iconId($base), 'label' => $base, 'group' => $group, 'svg' => $svg];
      }
    }
    finally {
      $zip->close();
    }
    if ($candidates === []) {
      throw new \InvalidArgumentException('The ZIP does not contain any SVG files.');
    }
    return $candidates;
  }

}
