<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Plugin\IconSource;

use Drupal\canvas_utilities_icons\Attribute\IconSource;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Extracts symbols from an uploaded SVG sprite.
 */
#[IconSource(
  id: 'svg_sprite',
  label: new TranslatableMarkup('SVG sprite'),
  description: new TranslatableMarkup('An SVG containing symbol elements.'),
)]
final class SvgSprite extends IconSourceBase {

  /**
   * {@inheritdoc} */
  public function extract(array $uploads): array {
    $upload = $uploads[0] ?? NULL;
    if (!$upload instanceof UploadedFile || count($uploads) !== 1 || !$upload->isValid() || strtolower($upload->getClientOriginalExtension()) !== 'svg' || $upload->getSize() > 5 * 1024 * 1024) {
      throw new \InvalidArgumentException('Upload one valid SVG sprite no larger than 5 MB.');
    }
    $raw = file_get_contents($upload->getRealPath());
    if (!is_string($raw) || stripos($raw, '<!DOCTYPE') !== FALSE) {
      throw new \InvalidArgumentException('The SVG sprite is unreadable or contains a forbidden document type.');
    }
    $document = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    $loaded = $document->loadXML($raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
      throw new \InvalidArgumentException('The SVG sprite is not valid XML.');
    }
    $symbols = $document->getElementsByTagName('symbol');
    if ($symbols->length === 0 || $symbols->length > 500) {
      throw new \InvalidArgumentException('The sprite must contain between 1 and 500 symbols.');
    }
    $candidates = [];
    foreach ($symbols as $symbol) {
      $id = $this->iconId($symbol->getAttribute('id'));
      $view_box = $symbol->getAttribute('viewBox') ?: '0 0 24 24';
      $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . htmlspecialchars($view_box, ENT_QUOTES | ENT_XML1) . '">';
      foreach ($symbol->childNodes as $child) {
        $svg .= $document->saveXML($child);
      }
      $svg .= '</svg>';
      $candidates[] = ['id' => $id, 'label' => $symbol->getAttribute('id'), 'group' => '', 'svg' => $svg];
    }
    return $candidates;
  }

}
