export type StyleValue = string | number | boolean;

export interface StyleControl {
  label: string;
  description: string;
  type: 'color' | 'dimension' | 'number' | 'select' | 'boolean_map' | 'text_token' | 'palette_color' | 'font_family';
  default: Record<string, StyleValue>;
  constraints: {
    min?: number;
    max?: number;
    step?: number;
    units?: string[];
    options?: StyleValue[];
    map?: Record<string, string>;
  };
  targets: Record<string, { property: string }>;
}

export interface StyleGuide {
  id: string;
  label: string;
  description: string;
  definitionHash: string;
  source: { type: 'theme' | 'config'; id: string; label: string };
  preview: { path: string };
  contexts: Record<string, { label: string; selector: string }>;
  groups: Record<string, {
    label: string;
    weight: number;
    controls: Record<string, StyleControl>;
  }>;
  liveValues: StyleValues;
  draft: { baseHash: string; values: StyleValues; updated: number } | null;
  baseHash: string;
}

export type StyleValues = Record<string, Record<string, StyleValue>>;

interface GuideResponse {
  data: StyleGuide[];
}

interface MutationResponse {
  data?: unknown;
  error?: { message?: string };
}

export async function getStyleGuides(theme: string, signal?: AbortSignal): Promise<StyleGuide[]> {
  const response = await fetch(`/canvas-utilities/api/v1/style-guides/${encodeURIComponent(theme)}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
    signal,
  });
  if (!response.ok) {
    const body = (await response.json()) as MutationResponse;
    throw new Error(body.error?.message ?? 'Unable to load style guides.');
  }
  return ((await response.json()) as GuideResponse).data;
}

export async function saveStyleGuideDraft(
  theme: string,
  guide: string,
  values: StyleValues,
  baseHash: string,
  csrfToken: string,
): Promise<void> {
  await mutate(`/canvas-utilities/api/v1/drafts/style-guide/${encodeURIComponent(theme)}/${encodeURIComponent(guide)}`, 'PUT', csrfToken, { values, baseHash });
}

export async function publishStyleGuide(theme: string, guide: string, csrfToken: string): Promise<void> {
  await mutate(`/canvas-utilities/api/v1/publish/style-guide/${encodeURIComponent(theme)}/${encodeURIComponent(guide)}`, 'POST', csrfToken);
}

export interface VisualDefinitionInput {
  id: string;
  label: string;
  description: string;
  status: boolean;
  weight: number;
  preview: { path: string };
  contexts: Record<string, { label: string; selector: string }>;
  groups: StyleGuide['groups'];
}

export async function createStyleGuideDefinition(theme: string, definition: VisualDefinitionInput, csrfToken: string): Promise<void> {
  await mutate(`/canvas-utilities/api/v1/style-guide-definitions/${encodeURIComponent(theme)}`, 'POST', csrfToken, definition);
}

async function mutate(url: string, method: string, csrfToken: string, body?: unknown): Promise<void> {
  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  if (!response.ok) {
    const result = (await response.json()) as MutationResponse;
    throw new Error(result.error?.message ?? `The operation failed (${response.status}).`);
  }
}
