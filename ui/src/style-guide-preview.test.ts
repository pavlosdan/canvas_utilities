import { describe, expect, it } from 'vitest';

import { compilePreviewCss } from './components/StyleGuideWorkspace';
import type { StyleGuide } from './style-guide-api';

describe('style-guide preview CSS', () => {
  it('resolves palette and font references', () => {
    const guide = {
      contexts: { default: { label: 'Default', selector: ':root' } },
      groups: { base: { label: 'Base', weight: 0, controls: {
        color: { label: 'Color', description: '', type: 'palette_color', default: {}, constraints: {}, targets: { default: { property: '--color' } } },
        font: { label: 'Font', description: '', type: 'font_family', default: {}, constraints: {}, targets: { default: { property: '--font' } } },
      } } },
    } as unknown as StyleGuide;
    const css = compilePreviewCss(
      guide,
      { color: { default: 'mercury__brand:primary' }, font: { default: 'mercury__inter' } },
      [{ id: 'mercury__brand', label: 'Brand', theme: 'mercury', description: '', prefix: 'brand', weight: 0, status: true, colors: [{ id: 'primary', label: 'Primary', value: '#123456', role: '' }] }],
      [{ id: 'mercury__inter', label: 'Inter', family: 'Inter', fallbacks: 'sans-serif', provider: 'remote_stylesheet', remoteUrl: '', faces: [], status: true }],
    );
    expect(css).toContain('--color: #123456;');
    expect(css).toContain('--font: "Inter", sans-serif;');
  });
});
