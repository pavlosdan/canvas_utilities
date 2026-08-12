export interface IconItem {
  id: string;
  label: string;
  group: string;
  url: string;
  viewBox: string;
  hash: string;
}

export interface IconLibrary {
  id: string;
  label: string;
  provider: 'individual_svg' | 'svg_zip' | 'svg_sprite';
  prefix: string;
  license: string;
  source: string;
  status: boolean;
  icons: IconItem[];
}

export async function getIconLibraries(theme: string, signal?: AbortSignal): Promise<IconLibrary[]> {
  const response = await fetch(`/canvas-utilities/api/v1/icons/${encodeURIComponent(theme)}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
    signal,
  });
  if (!response.ok) throw new Error('Unable to load icon libraries.');
  return ((await response.json()) as { data: IconLibrary[] }).data;
}

export async function importIconLibrary(theme: string, data: FormData, csrfToken: string): Promise<void> {
  const response = await fetch(`/canvas-utilities/api/v1/icons/${encodeURIComponent(theme)}/import`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken },
    body: data,
  });
  if (!response.ok) {
    const result = (await response.json()) as { error?: { message?: string } };
    throw new Error(result.error?.message ?? 'The icon import failed.');
  }
}

export async function deleteIconLibrary(theme: string, library: string, csrfToken: string): Promise<void> {
  await deleteItem(`/canvas-utilities/api/v1/icons/${encodeURIComponent(theme)}/${encodeURIComponent(library)}`, csrfToken);
}

async function deleteItem(url: string, csrfToken: string): Promise<void> {
  const response = await fetch(url, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken } });
  if (!response.ok) throw new Error('Unable to delete the icon library.');
}
