import { describe, expect, it } from 'vitest';

import { alphaPercent, composeHexColor, parseHexColor } from './color-value';

describe('parseHexColor', () => {
  it('reads every hex form, expanding shorthand', () => {
    expect(parseHexColor('#3366ff')).toEqual({ hex: '#3366ff', alpha: 1 });
    expect(parseHexColor('#36F')).toEqual({ hex: '#3366ff', alpha: 1 });
    expect(parseHexColor('#3366FF')).toEqual({ hex: '#3366ff', alpha: 1 });
  });

  it('reads the alpha channel', () => {
    expect(parseHexColor('#3366ff00')).toEqual({ hex: '#3366ff', alpha: 0 });
    expect(parseHexColor('#3366ffff')).toEqual({ hex: '#3366ff', alpha: 1 });
    const half = parseHexColor('#3366ff80');
    expect(half?.hex).toBe('#3366ff');
    expect(half?.alpha).toBeCloseTo(0.502, 3);
    // Shorthand carries alpha too.
    expect(parseHexColor('#36f8')?.alpha).toBeCloseTo(0.533, 3);
  });

  it('returns null for values a hue picker cannot represent', () => {
    // All valid CSS, none of them expressible as a hex swatch plus a slider.
    for (const value of ['rgba(51, 102, 255, 0.5)', 'oklch(70% 0.1 200)', 'var(--brand-primary)', 'rebeccapurple', '', '#12345', 'linear-gradient(#fff, #000)']) {
      expect(parseHexColor(value)).toBeNull();
    }
  });
});

describe('composeHexColor', () => {
  it('omits the alpha pair when fully opaque', () => {
    // Round-tripping opacity must not lengthen an untouched value.
    expect(composeHexColor('#3366ff', 1)).toBe('#3366ff');
  });

  it('appends the alpha pair when translucent', () => {
    expect(composeHexColor('#3366ff', 0)).toBe('#3366ff00');
    expect(composeHexColor('#3366ff', 0.5)).toBe('#3366ff80');
  });

  it('clamps out-of-range opacity', () => {
    expect(composeHexColor('#3366ff', 2)).toBe('#3366ff');
    expect(composeHexColor('#3366ff', -1)).toBe('#3366ff00');
  });

  it('survives a value that already carries alpha', () => {
    expect(composeHexColor('#3366ff80', 1)).toBe('#3366ff');
  });
});

describe('alphaPercent', () => {
  it('reports opacity as a percentage, defaulting to opaque', () => {
    expect(alphaPercent('#3366ff')).toBe(100);
    expect(alphaPercent('#3366ff00')).toBe(0);
    expect(alphaPercent('#3366ff80')).toBe(50);
    expect(alphaPercent('rgba(0,0,0,0.2)')).toBe(100);
  });
});
