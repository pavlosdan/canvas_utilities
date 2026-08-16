/**
 * Helpers for editing a color's opacity alongside its hue.
 *
 * `<input type="color">` has no alpha channel: it reads and writes `#rrggbb`
 * only, and silently drops anything more. Transparency therefore needs a
 * separate control, and the two have to be recombined into one CSS value.
 */

export interface HexColor {
  /** The opaque part, always as `#rrggbb`. */
  hex: string;
  /** Opacity from 0 (invisible) to 1 (opaque). */
  alpha: number;
}

/**
 * Parses a hex color into its opaque part and its opacity.
 *
 * Accepts all four hex forms: `#rgb`, `#rgba`, `#rrggbb` and `#rrggbbaa`.
 * Returns null for anything else — `rgba()`, `oklch()`, `var()` and named
 * colors are valid CSS but cannot be represented by a hue picker plus a
 * slider, so callers fall back to editing them as text.
 */
export function parseHexColor(value: string): HexColor | null {
  const trimmed = value.trim();
  if (!/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(trimmed)) {
    return null;
  }
  const digits = trimmed.slice(1);
  // Expand shorthand, where each digit stands for a doubled pair.
  const expanded = digits.length <= 4
    ? digits.split('').map((digit) => digit + digit).join('')
    : digits;
  const hex = `#${expanded.slice(0, 6).toLowerCase()}`;
  const alpha = expanded.length === 8 ? parseInt(expanded.slice(6, 8), 16) / 255 : 1;
  return { hex, alpha };
}

/**
 * Combines an opaque color and an opacity into a single CSS value.
 *
 * A fully opaque color stays `#rrggbb` rather than gaining a redundant `ff`,
 * so turning opacity down and back up returns the original value instead of a
 * longer equivalent.
 */
export function composeHexColor(hex: string, alpha: number): string {
  const base = parseHexColor(hex)?.hex ?? '#000000';
  const clamped = Math.min(1, Math.max(0, alpha));
  if (clamped >= 1) {
    return base;
  }
  return `${base}${Math.round(clamped * 255).toString(16).padStart(2, '0')}`;
}

/**
 * Returns the opacity of a value as a whole percentage, for display.
 */
export function alphaPercent(value: string): number {
  return Math.round((parseHexColor(value)?.alpha ?? 1) * 100);
}
