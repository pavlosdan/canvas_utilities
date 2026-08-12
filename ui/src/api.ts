import type { BootstrapData } from './types';

interface ApiErrorBody {
  message?: string;
}

export async function getBootstrap(signal?: AbortSignal): Promise<BootstrapData> {
  const response = await fetch('/canvas-utilities/api/v1/bootstrap', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
    signal,
  });
  if (!response.ok) {
    let body: ApiErrorBody = {};
    try {
      body = (await response.json()) as ApiErrorBody;
    } catch {
      // Drupal may return an HTML error page before content negotiation runs.
    }
    throw new Error(body.message ?? `Unable to load the design system (${response.status}).`);
  }
  return (await response.json()) as BootstrapData;
}

