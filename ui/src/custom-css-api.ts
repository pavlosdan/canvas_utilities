export interface CustomCssData {
  theme: string;
  css: string;
  status: boolean;
  fingerprint: string | null;
}

export async function getCustomCss(theme: string, signal?: AbortSignal): Promise<CustomCssData> {
  const response = await fetch(`/canvas-utilities/api/v1/custom-css/${encodeURIComponent(theme)}`, {
    credentials: 'same-origin', headers: { Accept: 'application/json' }, signal,
  });
  if (!response.ok) throw new Error('Unable to load custom CSS.');
  return ((await response.json()) as { data: CustomCssData }).data;
}

export async function saveCustomCss(theme: string, css: string, status: boolean, csrfToken: string): Promise<CustomCssData> {
  const response = await fetch(`/canvas-utilities/api/v1/custom-css/${encodeURIComponent(theme)}`, {
    method: 'PUT', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    body: JSON.stringify({ css, status }),
  });
  const result = (await response.json()) as { data?: CustomCssData; error?: { message?: string } };
  if (!response.ok || !result.data) throw new Error(result.error?.message ?? 'Unable to save custom CSS.');
  return result.data;
}
