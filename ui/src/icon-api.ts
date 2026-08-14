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

export interface AddIconsReport {
  added: string[];
  replaced: string[];
  unchanged: string[];
}

export async function addIconsToLibrary(theme: string, library: string, data: FormData, csrfToken: string): Promise<AddIconsReport> {
  const response = await fetch(
    `/canvas-utilities/api/v1/icons/${encodeURIComponent(theme)}/${encodeURIComponent(library)}/icons`,
    { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken }, body: data },
  );
  if (!response.ok) {
    const result = (await response.json()) as { error?: { message?: string } };
    throw new Error(result.error?.message ?? 'Unable to add icons to this library.');
  }
  return ((await response.json()) as { meta: AddIconsReport }).meta;
}

export async function updateIconLibrary(
  theme: string,
  library: string,
  changes: { label?: string; license?: string; source?: string; status?: boolean },
  csrfToken: string,
): Promise<void> {
  const response = await fetch(`/canvas-utilities/api/v1/icons/${encodeURIComponent(theme)}/${encodeURIComponent(library)}`, {
    method: 'PATCH',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    body: JSON.stringify(changes),
  });
  if (!response.ok) {
    const result = (await response.json()) as { error?: { message?: string } };
    throw new Error(result.error?.message ?? 'Unable to update the icon library.');
  }
}

export async function deleteIconLibrary(theme: string, library: string, csrfToken: string): Promise<void> {
  await deleteItem(
    `/canvas-utilities/api/v1/icons/${encodeURIComponent(theme)}/${encodeURIComponent(library)}`,
    csrfToken,
    'Unable to delete the icon library.',
  );
}

export async function deleteIcon(theme: string, library: string, icon: string, csrfToken: string): Promise<void> {
  await deleteItem(
    `/canvas-utilities/api/v1/icons/${encodeURIComponent(theme)}/${encodeURIComponent(library)}/icons/${encodeURIComponent(icon)}`,
    csrfToken,
    'Unable to remove the icon.',
  );
}

async function deleteItem(url: string, csrfToken: string, message: string): Promise<void> {
  const response = await fetch(url, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken } });
  if (!response.ok) throw new Error(message);
}
