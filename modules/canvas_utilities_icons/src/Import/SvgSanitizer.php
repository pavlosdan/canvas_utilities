<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Import;

use enshrined\svgSanitize\Sanitizer;

/**
 * Sanitizes and normalizes imported SVG markup.
 */
final class SvgSanitizer {

  /**
   * Returns safe SVG markup and its viewBox.
   *
   * @return array{svg: string, viewBox: string}
   *   Sanitized SVG data.
   */
  public function sanitize(string $raw, string $namespace): array {
    if (strlen($raw) > 1024 * 1024 || stripos($raw, '<!DOCTYPE') !== FALSE || stripos($raw, '<foreignObject') !== FALSE) {
      throw new \InvalidArgumentException('The SVG contains forbidden or excessive content.');
    }
    $sanitizer = new Sanitizer();
    $sanitizer->removeRemoteReferences(TRUE);
    $clean = $sanitizer->sanitize($raw);
    if (!is_string($clean) || $clean === '' || preg_match('/(?:href|src)\s*=\s*["\'](?!#)/i', $clean) || preg_match('/url\(\s*["\']?(?!#)/i', $clean)) {
      throw new \InvalidArgumentException('The SVG could not be sanitized or contains an external reference.');
    }
    $document = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    $loaded = $document->loadXML($clean, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded || $document->documentElement?->localName !== 'svg') {
      throw new \InvalidArgumentException('The sanitized document is not an SVG.');
    }
    $this->namespaceIds($document, $namespace);
    $view_box = $document->documentElement->getAttribute('viewBox') ?: '0 0 24 24';
    if (!preg_match('/^-?[0-9.]+\s+-?[0-9.]+\s+[0-9.]+\s+[0-9.]+$/', trim($view_box))) {
      throw new \InvalidArgumentException('The SVG has an invalid viewBox.');
    }
    $svg = $document->saveXML($document->documentElement);
    if (!is_string($svg)) {
      throw new \InvalidArgumentException('The sanitized SVG could not be serialized.');
    }
    return ['svg' => $svg, 'viewBox' => trim($view_box)];
  }

  /**
   * Makes internal IDs unique when multiple icons are rendered together.
   */
  private function namespaceIds(\DOMDocument $document, string $namespace): void {
    $ids = [];
    foreach ($document->getElementsByTagName('*') as $element) {
      if ($element->hasAttribute('id')) {
        $original = $element->getAttribute('id');
        $normalized = trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $original), '-');
        if ($normalized === '') {
          throw new \InvalidArgumentException('The SVG contains an invalid internal ID.');
        }
        $ids[$original] = 'cu-' . $namespace . '-' . $normalized;
        $element->setAttribute('id', $ids[$original]);
      }
    }
    if ($ids === []) {
      return;
    }
    foreach ($document->getElementsByTagName('*') as $element) {
      foreach (iterator_to_array($element->attributes) as $attribute) {
        $value = $attribute->value;
        foreach ($ids as $original => $replacement) {
          if ($value === '#' . $original) {
            $value = '#' . $replacement;
          }
          $value = str_replace('url(#' . $original . ')', 'url(#' . $replacement . ')', $value);
        }
        $attribute->value = $value;
      }
    }
  }

}
