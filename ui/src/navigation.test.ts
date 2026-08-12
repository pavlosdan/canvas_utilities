import { beforeEach, describe, expect, it, vi } from 'vitest';

import { getRouteFromHash, navigate } from './navigation';

describe('extension navigation', () => {
  beforeEach(() => {
    window.location.hash = '';
  });

  it('normalizes the current hash route', () => {
    window.location.hash = '#/style-guide/';
    expect(getRouteFromHash()).toBe('style-guide');
  });

  it('updates the hash and notifies Canvas', () => {
    const postMessage = vi.spyOn(window.parent, 'postMessage');
    navigate('/fonts');
    expect(window.location.hash).toBe('#/fonts');
    expect(postMessage).toHaveBeenCalledWith(
      { type: 'canvas:navigate', subPath: 'fonts' },
      window.location.origin,
    );
  });
});
