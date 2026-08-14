<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_style_guide\StyleGuide;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Normalizes typed style-guide values to CSS-safe scalar values.
 */
final class StyleGuideValueValidator {

  /**
   * Constructs the validator.
   */
  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Validates all supplied overrides against a definition.
   *
   * @param array<string, mixed> $definition
   *   A normalized guide definition.
   * @param array<string, mixed> $values
   *   Candidate values keyed by control and context IDs.
   *
   * @return array<string, array<string, string|int|float|bool>>
   *   Normalized overrides.
   */
  public function validate(array $definition, array $values): array {
    $controls = [];
    foreach ($definition['groups'] as $group) {
      foreach ($group['controls'] as $id => $control) {
        $controls[$id] = $control;
      }
    }

    $normalized = [];
    foreach ($values as $control_id => $contexts) {
      if (!isset($controls[$control_id]) || !is_array($contexts)) {
        throw new \InvalidArgumentException(sprintf('Unknown style-guide control "%s".', $control_id));
      }
      foreach ($contexts as $context_id => $value) {
        if (!isset($controls[$control_id]['targets'][$context_id])) {
          throw new \InvalidArgumentException(sprintf('Control "%s" does not target context "%s".', $control_id, $context_id));
        }
        $normalized[$control_id][$context_id] = $this->normalizeValue($controls[$control_id], $value);
      }
    }
    return $normalized;
  }

  /**
   * Normalizes one value.
   *
   * @param array<string, mixed> $control
   *   Control definition.
   * @param mixed $value
   *   Candidate control value.
   */
  private function normalizeValue(array $control, mixed $value): string|int|float|bool {
    $type = (string) $control['type'];
    $constraints = $control['constraints'];
    return match ($type) {
      'color' => $this->normalizeColor($value),
      'dimension' => $this->normalizeDimension($value, $constraints),
      'number' => $this->normalizeNumber($value, $constraints),
      'select' => $this->normalizeSelect($value, $constraints),
      'boolean_map' => $this->normalizeBoolean($value),
      'text_token' => $this->normalizeText($value, $constraints),
      'palette_color' => $this->normalizePaletteColor($value),
      'font_family' => $this->normalizeFontFamily($value),
      default => throw new \InvalidArgumentException(sprintf('Unsupported style-guide control type "%s".', $type)),
    };
  }

  /**
   * Resolves a normalized control value to its CSS representation.
   *
   * @param array<string, mixed> $control
   *   Control definition.
   * @param string|int|float|bool $value
   *   Normalized control value.
   */
  public function toCssValue(array $control, string|int|float|bool $value): string {
    return match ($control['type']) {
      'boolean_map' => (string) ($control['constraints']['map'][$value ? 'true' : 'false'] ?? ''),
      'palette_color' => $this->resolvePaletteColor((string) $value),
      'font_family' => $this->resolveFontFamily((string) $value),
      default => (string) $value,
    };
  }

  /**
   * Normalizes a CSS color.
   *
   * @param mixed $value
   *   Candidate color value.
   */
  private function normalizeColor(mixed $value): string {
    if (!is_string($value)) {
      throw new \InvalidArgumentException('Color values must be strings.');
    }
    $value = trim($value);
    $hex = preg_match('/^#[0-9a-fA-F]{3,4}([0-9a-fA-F]{3,4})?$/', $value);
    $functional = preg_match('/^(rgb|rgba|hsl|hsla|oklch|oklab)\([0-9.%+\-\s,\/]+\)$/i', $value);
    if (!$hex && !$functional) {
      throw new \InvalidArgumentException('The color is not an allowed CSS color.');
    }
    return strtolower($value);
  }

  /**
   * Normalizes a CSS dimension.
   *
   * @param mixed $value
   *   Candidate dimension value.
   * @param array<string, mixed> $constraints
   *   Control constraints.
   */
  private function normalizeDimension(mixed $value, array $constraints): string {
    if (!is_string($value) || !preg_match('/^(-?(?:\d+|\d*\.\d+))([a-z%]+)$/i', trim($value), $matches)) {
      throw new \InvalidArgumentException('The dimension must contain a number and an allowed unit.');
    }
    $number = (float) $matches[1];
    $unit = strtolower($matches[2]);
    $units = $constraints['units'] ?? ['px', 'rem', 'em', '%'];
    if (!in_array($unit, $units, TRUE) || isset($constraints['min']) && $number < $constraints['min'] || isset($constraints['max']) && $number > $constraints['max']) {
      throw new \InvalidArgumentException('The dimension is outside its allowed range or units.');
    }
    return $matches[1] . $unit;
  }

  /**
   * Normalizes a number.
   *
   * @param mixed $value
   *   Candidate numeric value.
   * @param array<string, mixed> $constraints
   *   Control constraints.
   */
  private function normalizeNumber(mixed $value, array $constraints): int|float {
    if (!is_int($value) && !is_float($value)) {
      throw new \InvalidArgumentException('The value must be numeric.');
    }
    if (isset($constraints['min']) && $value < $constraints['min'] || isset($constraints['max']) && $value > $constraints['max']) {
      throw new \InvalidArgumentException('The number is outside its allowed range.');
    }
    return $value;
  }

  /**
   * Normalizes an enumerated value.
   *
   * @param mixed $value
   *   Candidate enumerated value.
   * @param array<string, mixed> $constraints
   *   Control constraints.
   */
  private function normalizeSelect(mixed $value, array $constraints): string|int|float|bool {
    if (!is_scalar($value) || !in_array($value, $constraints['options'] ?? [], TRUE)) {
      throw new \InvalidArgumentException('The value is not an allowed option.');
    }
    return $value;
  }

  /**
   * Normalizes a Boolean value.
   *
   * @param mixed $value
   *   Candidate Boolean value.
   */
  private function normalizeBoolean(mixed $value): bool {
    if (!is_bool($value)) {
      throw new \InvalidArgumentException('The value must be true or false.');
    }
    return $value;
  }

  /**
   * Normalizes a restricted text token.
   *
   * @param mixed $value
   *   Candidate text value.
   * @param array<string, mixed> $constraints
   *   Control constraints.
   */
  private function normalizeText(mixed $value, array $constraints): string {
    if (!is_string($value) || strlen($value) > ($constraints['max_length'] ?? 100) || preg_match('/[{};<>]/', $value)) {
      throw new \InvalidArgumentException('The text token contains unsupported characters or is too long.');
    }
    return trim($value);
  }

  /**
   * Validates a palette and color reference.
   */
  private function normalizePaletteColor(mixed $value): string {
    if (!is_string($value) || !$this->entityTypeManager->hasDefinition('canvas_utilities_palette')) {
      throw new \InvalidArgumentException('The referenced palette provider is not enabled.');
    }
    [$palette_id, $color_id] = array_pad(explode(':', $value, 2), 2, '');
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_palette')->load($palette_id);
    $colors = $entity instanceof ConfigEntityInterface ? $entity->get('colors') : NULL;
    if (!$entity instanceof ConfigEntityInterface || !$entity->status() || !$this->findColor($colors, $color_id)) {
      throw new \InvalidArgumentException('The referenced palette color does not exist.');
    }
    return $palette_id . ':' . $color_id;
  }

  /**
   * Validates a font-family reference.
   */
  private function normalizeFontFamily(mixed $value): string {
    if (!is_string($value) || !$this->entityTypeManager->hasDefinition('canvas_utilities_font')) {
      throw new \InvalidArgumentException('The referenced font provider is not enabled.');
    }
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_font')->load($value);
    if (!$entity instanceof ConfigEntityInterface || !$entity->status()) {
      throw new \InvalidArgumentException('The referenced font family does not exist.');
    }
    return $value;
  }

  /**
   * Resolves a validated palette reference.
   */
  private function resolvePaletteColor(string $reference): string {
    [$palette_id, $color_id] = array_pad(explode(':', $reference, 2), 2, '');
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_palette')->load($palette_id);
    $colors = $entity instanceof ConfigEntityInterface ? $entity->get('colors') : NULL;
    $color = $this->findColor($colors, $color_id);
    if (!is_array($color)) {
      return '';
    }
    // A palette_color control targets a CSS property expecting a <color>. A
    // gradient is an <image>, so emitting one would produce a declaration the
    // browser discards, silently losing the style rather than failing loudly.
    if (($color['type'] ?? 'color') === 'gradient') {
      return '';
    }
    return (string) $color['value'];
  }

  /**
   * Resolves a validated font reference.
   */
  private function resolveFontFamily(string $reference): string {
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_font')->load($reference);
    if (!$entity instanceof ConfigEntityInterface) {
      return '';
    }
    return sprintf('"%s", %s', str_replace('"', '\\"', (string) $entity->get('family')), (string) $entity->get('fallbacks'));
  }

  /**
   * Finds a palette color in config's sequence representation.
   *
   * @return array<string, string>|null
   *   Color data, or NULL when the reference cannot be resolved.
   */
  private function findColor(mixed $colors, string $color_id): ?array {
    if (!is_array($colors)) {
      return NULL;
    }
    foreach ($colors as $color) {
      if (is_array($color) && ($color['id'] ?? NULL) === $color_id) {
        return $color;
      }
    }
    return NULL;
  }

}
