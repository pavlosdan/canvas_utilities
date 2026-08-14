export interface FontFamily {
  id: string;
  label: string;
  family: string;
  fallbacks: string;
  provider: 'local_file' | 'remote_stylesheet';
  remoteUrl: string;
  faces: Array<{ format: string; weight: string; style: string }>;
  status: boolean;
}

export async function getFonts(theme: string, signal?: AbortSignal): Promise<FontFamily[]> {
  const response = await fetch(`/canvas-utilities/api/v1/fonts/${encodeURIComponent(theme)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal });
  if (!response.ok) throw new Error('Unable to load font families.');
  return ((await response.json()) as { data: FontFamily[] }).data;
}

export async function createRemoteFont(theme: string, data: Record<string, string>, csrfToken: string): Promise<void> {
  const response = await fetch(`/canvas-utilities/api/v1/fonts/${encodeURIComponent(theme)}/remote`, {
    method: 'POST', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    body: JSON.stringify(data),
  });
  await expectSuccess(response);
}

export async function uploadFont(theme: string, data: FormData, csrfToken: string): Promise<void> {
  const response = await fetch(`/canvas-utilities/api/v1/fonts/${encodeURIComponent(theme)}/upload`, {
    method: 'POST', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken },
    body: data,
  });
  await expectSuccess(response);
}

export async function updateFont(
  theme: string,
  font: string,
  changes: { label?: string; family?: string; fallbacks?: string; url?: string; status?: boolean },
  csrfToken: string,
): Promise<void> {
  const response = await fetch(`/canvas-utilities/api/v1/fonts/${encodeURIComponent(theme)}/${encodeURIComponent(font)}`, {
    method: 'PATCH', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    body: JSON.stringify(changes),
  });
  await expectSuccess(response);
}

export async function deleteFont(theme: string, font: string, csrfToken: string): Promise<void> {
  const response = await fetch(`/canvas-utilities/api/v1/fonts/${encodeURIComponent(theme)}/${encodeURIComponent(font)}`, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken } });
  await expectSuccess(response);
}

async function expectSuccess(response: Response): Promise<void> {
  if (!response.ok) {
    const result = (await response.json()) as { error?: { message?: string } };
    throw new Error(result.error?.message ?? 'The font operation failed.');
  }
}
