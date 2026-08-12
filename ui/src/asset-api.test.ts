import { afterEach, describe, expect, it, vi } from 'vitest';

import { saveCustomCss } from './custom-css-api';
import { deleteIconLibrary } from './icon-api';
import { deleteFont } from './font-api';
import { deletePalette } from './palette-api';

afterEach(() => vi.restoreAllMocks());

describe('asset mutation APIs', () => {
  it('sends custom CSS with CSRF protection', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ data: { theme: 'mercury', css: ':root{}', status: true, fingerprint: 'abc' } }), { status: 200 }));
    await saveCustomCss('mercury', ':root{}', true, 'token');
    expect(fetchMock).toHaveBeenCalledWith('/canvas-utilities/api/v1/custom-css/mercury', expect.objectContaining({ method: 'PUT', headers: expect.objectContaining({ 'X-CSRF-Token': 'token' }) }));
  });

  it.each([
    ['icon', () => deleteIconLibrary('mercury', 'mercury__ui', 'token')],
    ['font', () => deleteFont('mercury', 'mercury__inter', 'token')],
    ['palette', () => deletePalette('mercury', 'mercury__brand', 'token')],
  ])('uses DELETE for %s removal', async (_label, operation) => {
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 204 }));
    await operation();
    expect(fetchMock).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({ method: 'DELETE', headers: expect.objectContaining({ 'X-CSRF-Token': 'token' }) }));
  });
});
