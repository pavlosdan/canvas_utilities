export interface PaletteColor {
  id: string;
  label: string;
  value: string;
  role: string;
}

export interface Palette {
  id: string;
  label: string;
  theme: string;
  description: string;
  prefix: string;
  weight: number;
  status: boolean;
  colors: PaletteColor[];
}

export async function getPalettes(theme: string, signal?: AbortSignal): Promise<Palette[]> {
  const response = await fetch(`/canvas-utilities/api/v1/palettes/${encodeURIComponent(theme)}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
    signal,
  });
  if (!response.ok) throw new Error('Unable to load color palettes.');
  return ((await response.json()) as { data: Palette[] }).data;
}

export async function createPalette(theme: string, data: Omit<Palette, 'theme' | 'status' | 'weight'>, csrfToken: string): Promise<void> {
  const response = await fetch(`/canvas-utilities/api/v1/palettes/${encodeURIComponent(theme)}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    body: JSON.stringify(data),
  });
  if (!response.ok) {
    const result = (await response.json()) as { error?: { message?: string } };
    throw new Error(result.error?.message ?? 'Unable to create the palette.');
  }
}

export async function deletePalette(theme: string, palette: string, csrfToken: string): Promise<void> {
  const response = await fetch(`/canvas-utilities/api/v1/palettes/${encodeURIComponent(theme)}/${encodeURIComponent(palette)}`, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken } });
  if (!response.ok) throw new Error('Unable to delete the palette.');
}
