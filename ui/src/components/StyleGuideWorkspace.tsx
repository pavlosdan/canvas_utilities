import { useEffect, useMemo, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import {
  Badge,
  Box,
  Button,
  Callout,
  Card,
  Dialog,
  Flex,
  Heading,
  Select,
  Separator,
  Spinner,
  Switch,
  Text,
  TextField,
} from '@radix-ui/themes';
import { CheckCircledIcon, ExclamationTriangleIcon, MagnifyingGlassIcon, ResetIcon } from '@radix-ui/react-icons';

import {
  createStyleGuideDefinition,
  getStyleGuides,
  publishStyleGuide,
  saveStyleGuideDraft,
} from '../style-guide-api';
import type {
  StyleControl,
  StyleGuide,
  StyleValue,
  StyleValues,
  VisualDefinitionInput,
} from '../style-guide-api';
import { getPalettes } from '../palette-api';
import type { Palette } from '../palette-api';
import { alphaPercent, composeHexColor, parseHexColor } from '../color-value';
import { getFonts } from '../font-api';
import type { FontFamily } from '../font-api';

/** One group of controls, as declared by a style guide definition. */
type StyleGuideGroup = StyleGuide['groups'][string];

const PREVIEW_DEFAULT_WIDTH = 680;
const PREVIEW_MIN_WIDTH = 320;
const PREVIEW_MAX_WIDTH = 1100;
const EDITOR_MIN_WIDTH = 240;

interface Props {
  theme: string;
  csrfToken: string;
  canPublish: boolean;
  canAdministerDefinitions: boolean;
}

export default function StyleGuideWorkspace({ theme, csrfToken, canPublish, canAdministerDefinitions }: Props) {
  const [guides, setGuides] = useState<StyleGuide[] | null>(null);
  const [activeId, setActiveId] = useState('');
  const [values, setValues] = useState<StyleValues>({});
  const [message, setMessage] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [palettes, setPalettes] = useState<Palette[]>([]);
  const [fonts, setFonts] = useState<FontFamily[]>([]);

  const load = async (signal?: AbortSignal) => {
    const loaded = await getStyleGuides(theme, signal);
    setGuides(loaded);
    const active = loaded.find((guide) => guide.id === activeId) ?? loaded[0];
    setActiveId(active?.id ?? '');
    setValues(active?.draft?.values ?? active?.liveValues ?? {});
  };

  useEffect(() => {
    const controller = new AbortController();
    setGuides(null);
    setMessage(null);
    load(controller.signal).catch((reason: unknown) => {
      if (reason instanceof Error && reason.name !== 'AbortError') {
        setMessage({ kind: 'error', text: reason.message });
        setGuides([]);
      }
    });
    Promise.allSettled([getPalettes(theme, controller.signal), getFonts(theme, controller.signal)]).then(([paletteResult, fontResult]) => {
      if (paletteResult.status === 'fulfilled') setPalettes(paletteResult.value);
      if (fontResult.status === 'fulfilled') setFonts(fontResult.value);
    });
    return () => controller.abort();
    // The active ID is deliberately retained when switching themes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [theme]);

  const active = useMemo(() => guides?.find((guide) => guide.id === activeId), [guides, activeId]);
  const dirty = active ? JSON.stringify(values) !== JSON.stringify(active.liveValues) : false;

  const selectGuide = (id: string) => {
    const selected = guides?.find((guide) => guide.id === id);
    setActiveId(id);
    setValues(selected?.draft?.values ?? selected?.liveValues ?? {});
    setMessage(null);
  };

  const setValue = (controlId: string, contextId: string, value: StyleValue) => {
    setValues((current) => ({
      ...current,
      [controlId]: { ...current[controlId], [contextId]: value },
    }));
    setMessage(null);
  };

  const resetValue = (controlId: string, contextId: string) => {
    setValues((current) => {
      const next = structuredClone(current);
      delete next[controlId]?.[contextId];
      if (next[controlId] && Object.keys(next[controlId]).length === 0) delete next[controlId];
      return next;
    });
  };

  const save = async () => {
    if (!active) return;
    setBusy(true);
    try {
      await saveStyleGuideDraft(theme, active.id, values, active.baseHash, csrfToken);
      setMessage({ kind: 'success', text: 'Draft saved. Live pages are unchanged.' });
      await load();
    } catch (reason) {
      setMessage({ kind: 'error', text: reason instanceof Error ? reason.message : 'Unable to save the draft.' });
    } finally {
      setBusy(false);
    }
  };

  const publish = async () => {
    if (!active) return;
    setBusy(true);
    try {
      if (dirty) await saveStyleGuideDraft(theme, active.id, values, active.baseHash, csrfToken);
      await publishStyleGuide(theme, active.id, csrfToken);
      setMessage({ kind: 'success', text: 'Style guide published.' });
      await load();
    } catch (reason) {
      setMessage({ kind: 'error', text: reason instanceof Error ? reason.message : 'Unable to publish the style guide.' });
    } finally {
      setBusy(false);
    }
  };

  if (guides === null) {
    return <Flex align="center" gap="3"><Spinner /><Text color="gray">Loading style guides…</Text></Flex>;
  }

  if (guides.length === 0) {
    return (
      <section className="style-guide-workspace" aria-labelledby="style-guide-heading">
        <Heading id="style-guide-heading" size="7">Style guide</Heading>
        {message?.kind === 'error' && <Message message={message} />}
        <Card className="empty-card" mt="5">
          <Heading size="4">No style guides yet</Heading>
          <Text as="p" color="gray" mt="2">
            Add a version 1 Canvas Utilities manifest to this theme, or create a visual definition when definition authoring is enabled.
          </Text>
          {canAdministerDefinitions && <DefinitionBuilder theme={theme} csrfToken={csrfToken} onCreated={() => load()} />}
        </Card>
      </section>
    );
  }

  return (
    <section className="style-guide-workspace" aria-labelledby="style-guide-heading">
      <Flex justify="between" align="start" gap="5" wrap="wrap">
        <Box>
          <Heading id="style-guide-heading" size="7">Style guide</Heading>
          <Text as="p" color="gray" mt="2">Adjust trusted theme controls. Save a private draft, then publish when it is ready.</Text>
        </Box>
        <Flex gap="3">
          {canAdministerDefinitions && <DefinitionBuilder theme={theme} csrfToken={csrfToken} onCreated={() => load()} />}
          <Button variant="soft" disabled={!dirty || busy} onClick={save}>Save draft</Button>
          {canPublish && <Button disabled={busy || (!dirty && !active?.draft)} onClick={publish}>Publish</Button>}
        </Flex>
      </Flex>

      {message && <Message message={message} />}

      <Flex mt="6" gap="3" align="center" wrap="wrap">
        <Text as="label" htmlFor="guide-select" size="2" weight="medium">Guide</Text>
        <Select.Root value={activeId} onValueChange={selectGuide}>
          <Select.Trigger id="guide-select" />
          <Select.Content>{guides.map((guide) => <Select.Item key={guide.id} value={guide.id}>{guide.label}</Select.Item>)}</Select.Content>
        </Select.Root>
        {active && <Badge color={active.source.type === 'theme' ? 'blue' : 'violet'}>{active.source.type === 'theme' ? 'Theme definition' : 'Visual definition'} · {active.source.label}</Badge>}
        {active?.draft && <Badge color="amber">Draft saved</Badge>}
      </Flex>

      {active && (
        <StyleGuideEditor
          key={active.id}
          guide={active}
          values={values}
          onChange={setValue}
          onReset={resetValue}
          palettes={palettes}
          fonts={fonts}
        />
      )}
    </section>
  );
}

function StyleGuideEditor({ guide, values, onChange, onReset, palettes, fonts }: {
  guide: StyleGuide;
  values: StyleValues;
  onChange: (controlId: string, contextId: string, value: StyleValue) => void;
  onReset: (controlId: string, contextId: string) => void;
  palettes: Palette[];
  fonts: FontFamily[];
}) {
  const groups = Object.entries(guide.groups);
  const [selectedGroupId, setSelectedGroupId] = useState(groups[0]?.[0] ?? '');
  const [selectedContextId, setSelectedContextId] = useState('');
  const [query, setQuery] = useState('');
  const [changedOnly, setChangedOnly] = useState(false);
  const [previewWidth, setPreviewWidth] = useState(PREVIEW_DEFAULT_WIDTH);
  const [resizingPreview, setResizingPreview] = useState(false);
  const resizeStart = useRef({ pointerX: 0, width: PREVIEW_DEFAULT_WIDTH, maxWidth: PREVIEW_MAX_WIDTH });
  const selectedGroup = guide.groups[selectedGroupId] ?? groups[0]?.[1];
  const selectedGroupKey = guide.groups[selectedGroupId] ? selectedGroupId : (groups[0]?.[0] ?? '');
  const contextEntries = selectedGroup ? contextsForGroup(guide, selectedGroup) : [];
  const activeContextId = contextEntries.some(([id]) => id === selectedContextId)
    ? selectedContextId
    : (contextEntries[0]?.[0] ?? '');
  const normalizedQuery = query.trim().toLocaleLowerCase();
  const visibleControls = selectedGroup
    ? Object.entries(selectedGroup.controls).filter(([controlId, control]) => {
        if (!control.targets[activeContextId]) return false;
        const matchesQuery = normalizedQuery === '' || `${control.label} ${control.description} ${controlId}`.toLocaleLowerCase().includes(normalizedQuery);
        return matchesQuery && (!changedOnly || valueChanged(controlId, activeContextId, values, guide.liveValues));
      })
    : [];

  const selectGroup = (groupId: string) => {
    const nextGroup = guide.groups[groupId];
    setSelectedGroupId(groupId);
    setSelectedContextId(nextGroup ? contextsForGroup(guide, nextGroup)[0]?.[0] ?? '' : '');
    setQuery('');
    setChangedOnly(false);
  };

  const resizePreview = (width: number) => setPreviewWidth(Math.min(PREVIEW_MAX_WIDTH, Math.max(PREVIEW_MIN_WIDTH, Math.round(width))));

  useEffect(() => {
    if (!resizingPreview) return;
    const move = (event: PointerEvent) => {
      const requestedWidth = resizeStart.current.width + resizeStart.current.pointerX - event.clientX;
      resizePreview(Math.min(resizeStart.current.maxWidth, requestedWidth));
    };
    const stop = () => setResizingPreview(false);
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', stop, { once: true });
    window.addEventListener('pointercancel', stop, { once: true });
    return () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', stop);
      window.removeEventListener('pointercancel', stop);
    };
  }, [resizingPreview]);

  return (
    <div
      className="style-guide-layout"
      style={{ '--style-guide-preview-width': `${previewWidth}px` } as CSSProperties}
    >
      <nav className="style-guide-nav" aria-label="Style guide groups">
        <Text className="style-guide-nav__eyebrow" size="1" weight="bold" color="gray">PROPERTY GROUPS</Text>
        <div className="style-guide-nav__list">
          {groups.map(([groupId, group]) => {
            const changedCount = countChanged(group, values, guide.liveValues);
            return (
              <button
                key={groupId}
                type="button"
                className={`style-guide-nav__item${selectedGroupKey === groupId ? ' is-active' : ''}`}
                aria-current={selectedGroupKey === groupId ? 'page' : undefined}
                onClick={() => selectGroup(groupId)}
              >
                <span>{group.label}</span>
                <small>{Object.keys(group.controls).length} properties</small>
                {changedCount > 0 && <Badge color="amber" size="1">{changedCount} changed</Badge>}
              </button>
            );
          })}
        </div>
        <div className="style-guide-nav__tip">
          <Text size="2" weight="medium">Keep the canvas focused</Text>
          <Text as="p" size="1" color="gray" mt="1">Choose one style and work through its properties. Your place is kept as you move between groups.</Text>
        </div>
      </nav>

      <Card className="style-editor-panel">
        {selectedGroup && activeContextId && (
          <>
            <Flex justify="between" align="start" gap="4" wrap="wrap" className="style-editor-panel__heading">
              <Box>
                <Text size="1" weight="bold" color="gray">{selectedGroup.label.toLocaleUpperCase()}</Text>
                <Heading size="6" mt="1">{guide.contexts[activeContextId]?.label ?? activeContextId}</Heading>
                <Text as="p" size="2" color="gray" mt="1">Edit one style at a time. Changes appear instantly in the preview.</Text>
              </Box>
              <Badge color="gray">{visibleControls.length} {visibleControls.length === 1 ? 'property' : 'properties'}</Badge>
            </Flex>

            {contextEntries.length > 1 && (
              <div className="context-tabs" role="group" aria-label={`${selectedGroup.label} styles`}>
                {contextEntries.map(([contextId, context]) => {
                  const targetCount = countContextControls(selectedGroup, contextId);
                  const changedCount = countContextChanges(selectedGroup, contextId, values, guide.liveValues);
                  return (
                    <button
                      key={contextId}
                      type="button"
                      aria-pressed={activeContextId === contextId}
                      className={`context-tab${activeContextId === contextId ? ' is-active' : ''}`}
                      onClick={() => setSelectedContextId(contextId)}
                    >
                      <span>{context.label}</span>
                      <small>{changedCount > 0 ? `${changedCount} changed` : `${targetCount} properties`}</small>
                    </button>
                  );
                })}
              </div>
            )}

            <div className="style-editor-toolbar">
              <TextField.Root
                aria-label="Find a property"
                placeholder="Find a property…"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
              >
                <TextField.Slot><MagnifyingGlassIcon /></TextField.Slot>
              </TextField.Root>
              <Text as="label" size="2" className="changed-filter">
                <Switch size="1" checked={changedOnly} onCheckedChange={setChangedOnly} />
                Changed only
              </Text>
            </div>

            <div className="property-list">
              {visibleControls.map(([controlId, control]) => (
                <PropertyEditor
                  key={`${controlId}:${activeContextId}`}
                  controlId={controlId}
                  contextId={activeContextId}
                  contextLabel={guide.contexts[activeContextId]?.label ?? activeContextId}
                  control={control}
                  value={values[controlId]?.[activeContextId] ?? control.default[activeContextId] ?? ''}
                  overridden={values[controlId]?.[activeContextId] !== undefined}
                  changed={valueChanged(controlId, activeContextId, values, guide.liveValues)}
                  onChange={(next) => onChange(controlId, activeContextId, next)}
                  onReset={() => onReset(controlId, activeContextId)}
                  palettes={palettes}
                  fonts={fonts}
                />
              ))}
              {visibleControls.length === 0 && (
                <div className="property-list__empty">
                  <Heading size="3">No matching properties</Heading>
                  <Text as="p" size="2" color="gray" mt="1">Try another search or show all properties.</Text>
                  <Button mt="3" size="1" variant="soft" onClick={() => { setQuery(''); setChangedOnly(false); }}>Show all</Button>
                </div>
              )}
            </div>
          </>
        )}
      </Card>

      <div
        className="preview-resizer"
        role="separator"
        aria-label="Resize live preview"
        aria-orientation="vertical"
        aria-valuemin={PREVIEW_MIN_WIDTH}
        aria-valuemax={resizeStart.current.maxWidth}
        aria-valuenow={previewWidth}
        tabIndex={0}
        title="Drag to resize. Double-click to reset."
        onPointerDown={(event) => {
          event.preventDefault();
          const layout = event.currentTarget.parentElement;
          const editor = event.currentTarget.previousElementSibling;
          const preview = event.currentTarget.nextElementSibling;
          const editorWidth = editor?.getBoundingClientRect().width ?? EDITOR_MIN_WIDTH;
          const renderedPreviewWidth = preview?.getBoundingClientRect().width ?? previewWidth;
          resizeStart.current = {
            pointerX: event.clientX,
            width: renderedPreviewWidth,
            maxWidth: Math.min(PREVIEW_MAX_WIDTH, renderedPreviewWidth + Math.max(0, editorWidth - EDITOR_MIN_WIDTH), layout?.getBoundingClientRect().width ?? PREVIEW_MAX_WIDTH),
          };
          setPreviewWidth(Math.round(renderedPreviewWidth));
          setResizingPreview(true);
        }}
        onDoubleClick={() => resizePreview(PREVIEW_DEFAULT_WIDTH)}
        onKeyDown={(event) => {
          if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' && event.key !== 'Home') return;
          event.preventDefault();
          if (event.key === 'Home') resizePreview(PREVIEW_DEFAULT_WIDTH);
          else {
            const editorWidth = event.currentTarget.previousElementSibling?.getBoundingClientRect().width ?? EDITOR_MIN_WIDTH;
            const renderedPreviewWidth = event.currentTarget.nextElementSibling?.getBoundingClientRect().width ?? previewWidth;
            const availableMaximum = Math.min(PREVIEW_MAX_WIDTH, renderedPreviewWidth + Math.max(0, editorWidth - EDITOR_MIN_WIDTH));
            const nextWidth = previewWidth + (event.key === 'ArrowLeft' ? 24 : -24);
            resizePreview(Math.min(availableMaximum, nextWidth));
          }
        }}
      >
        <span aria-hidden="true" />
      </div>

      {resizingPreview && <div className="preview-resize-overlay" aria-hidden="true" />}

      <LivePreview guide={guide} values={values} palettes={palettes} fonts={fonts} />
    </div>
  );
}

function contextsForGroup(guide: StyleGuide, group: StyleGuideGroup) {
  return Object.entries(guide.contexts).filter(([contextId]) =>
    Object.values(group.controls).some((control) => Boolean(control.targets[contextId])),
  );
}

function countContextControls(group: StyleGuideGroup, contextId: string): number {
  return Object.values(group.controls).filter((control) => Boolean(control.targets[contextId])).length;
}

function countContextChanges(group: StyleGuideGroup, contextId: string, values: StyleValues, liveValues: StyleValues): number {
  return Object.keys(group.controls).filter((controlId) =>
    group.controls[controlId].targets[contextId] && valueChanged(controlId, contextId, values, liveValues),
  ).length;
}

function valueChanged(controlId: string, contextId: string, values: StyleValues, liveValues: StyleValues): boolean {
  return JSON.stringify(values[controlId]?.[contextId]) !== JSON.stringify(liveValues[controlId]?.[contextId]);
}

/**
 * Counts the controls in a group whose value differs from what is live.
 *
 * @see the `dirty` flag, which asks the same question for the whole guide.
 */
function countChanged(group: StyleGuideGroup, values: StyleValues, liveValues: StyleValues): number {
  return Object.keys(group.controls).filter(
    (controlId) => JSON.stringify(values[controlId] ?? {}) !== JSON.stringify(liveValues[controlId] ?? {}),
  ).length;
}

function LivePreview({ guide, values, palettes, fonts }: { guide: StyleGuide; values: StyleValues; palettes: Palette[]; fonts: FontFamily[] }) {
  const frame = useRef<HTMLIFrameElement>(null);
  const css = useMemo(() => compilePreviewCss(guide, values, palettes, fonts), [guide, values, palettes, fonts]);
  const update = () => {
    const document = frame.current?.contentDocument;
    if (!document) return;
    let style = document.getElementById('canvas-utilities-live-preview') as HTMLStyleElement | null;
    if (!style) {
      style = document.createElement('style');
      style.id = 'canvas-utilities-live-preview';
      document.head.append(style);
    }
    style.textContent = css;
  };
  useEffect(update, [css]);
  return (
    <aside className="preview-panel" aria-label="Live theme preview">
      <Flex justify="between" align="center" mb="3"><Heading size="4">Live preview</Heading><Badge color="green">Draft values</Badge></Flex>
      <iframe
        ref={frame}
        title={`${guide.label} preview`}
        src={guide.preview.path}
        sandbox="allow-same-origin"
        onLoad={update}
      />
    </aside>
  );
}

export function compilePreviewCss(guide: StyleGuide, values: StyleValues, palettes: Palette[], fonts: FontFamily[]): string {
  return Object.entries(guide.contexts).map(([contextId, context]) => {
    const declarations: string[] = [];
    Object.values(guide.groups).forEach((group) => Object.entries(group.controls).forEach(([controlId, control]) => {
      const target = control.targets[contextId];
      if (!target) return;
      let value = values[controlId]?.[contextId] ?? control.default[contextId];
      if (value === undefined) return;
      if (control.type === 'boolean_map') value = control.constraints.map?.[value ? 'true' : 'false'] ?? '';
      if (control.type === 'palette_color') {
        const [paletteId, colorId] = String(value).split(':');
        value = palettes.find((palette) => palette.id === paletteId)?.colors.find((color) => color.id === colorId)?.value ?? '';
      }
      if (control.type === 'font_family') {
        const font = fonts.find((item) => item.id === value);
        value = font ? `"${font.family.replace(/"/g, '\\"')}", ${font.fallbacks}` : '';
      }
      declarations.push(`${target.property}: ${String(value)};`);
    }));
    return declarations.length ? `${context.selector} { ${declarations.join(' ')} }` : '';
  }).filter(Boolean).join('\n');
}

interface BuilderControl {
  key: string;
  id: string;
  label: string;
  type: StyleControl['type'];
  property: string;
  defaultValue: string;
}

function DefinitionBuilder({ theme, csrfToken, onCreated }: { theme: string; csrfToken: string; onCreated: () => Promise<void> | void }) {
  const [open, setOpen] = useState(false);
  const [id, setId] = useState('');
  const [label, setLabel] = useState('');
  const [description, setDescription] = useState('');
  const [controls, setControls] = useState<BuilderControl[]>([newBuilderControl()]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const updateControl = (key: string, patch: Partial<BuilderControl>) => {
    setControls((current) => current.map((control) => control.key === key ? { ...control, ...patch } : control));
  };

  const submit = async () => {
    setError(null);
    const normalizedId = machineName(id || label);
    if (!normalizedId || !label.trim()) {
      setError('Add a guide name and machine name.');
      return;
    }
    const validControls = controls.filter((control) => control.id && control.label && control.property);
    if (validControls.length === 0) {
      setError('Add at least one complete control.');
      return;
    }
    const controlDefinitions: Record<string, StyleControl> = {};
    for (const control of validControls) {
      const controlId = machineName(control.id);
      controlDefinitions[controlId] = {
        label: control.label,
        description: '',
        type: control.type,
        default: { default: parseBuilderValue(control) },
        constraints: defaultConstraints(control.type),
        targets: { default: { property: normalizeProperty(control.property) } },
      };
    }
    const definition: VisualDefinitionInput = {
      id: normalizedId,
      label: label.trim(),
      description: description.trim(),
      status: true,
      weight: 0,
      preview: { path: '/' },
      contexts: { default: { label: 'Default', selector: ':root' } },
      groups: { foundations: { label: 'Foundations', weight: 0, controls: controlDefinitions } },
    };
    setBusy(true);
    try {
      await createStyleGuideDefinition(theme, definition, csrfToken);
      await onCreated();
      setOpen(false);
      setId('');
      setLabel('');
      setDescription('');
      setControls([newBuilderControl()]);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Unable to create the definition.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog.Root open={open} onOpenChange={setOpen}>
      <Dialog.Trigger><Button variant="soft">Create definition</Button></Dialog.Trigger>
      <Dialog.Content maxWidth="760px" aria-describedby="definition-description">
        <Dialog.Title>New visual style guide</Dialog.Title>
        <Dialog.Description id="definition-description">Define trusted theme controls. Values can be changed later by style-guide editors.</Dialog.Description>
        <Flex direction="column" gap="4" mt="5">
          <Flex gap="4" wrap="wrap">
            <Box flexGrow="1"><Text as="label" htmlFor="definition-label" size="2" weight="medium">Name</Text><TextField.Root id="definition-label" mt="1" value={label} onChange={(event) => { setLabel(event.target.value); if (!id) setId(machineName(event.target.value)); }} /></Box>
            <Box flexGrow="1"><Text as="label" htmlFor="definition-id" size="2" weight="medium">Machine name</Text><TextField.Root id="definition-id" mt="1" value={id} onChange={(event) => setId(machineName(event.target.value))} /></Box>
          </Flex>
          <Box><Text as="label" htmlFor="definition-description-input" size="2" weight="medium">Description</Text><TextField.Root id="definition-description-input" mt="1" value={description} onChange={(event) => setDescription(event.target.value)} /></Box>
          <Separator size="4" />
          <Flex justify="between" align="center"><Heading size="4">Controls</Heading><Button size="1" variant="soft" onClick={() => setControls((current) => [...current, newBuilderControl()])}>Add control</Button></Flex>
          {controls.map((control, index) => (
            <Card key={control.key} className="builder-control">
              <Flex justify="between" align="center" mb="3"><Text weight="bold">Control {index + 1}</Text>{controls.length > 1 && <Button size="1" color="red" variant="ghost" onClick={() => setControls((current) => current.filter((item) => item.key !== control.key))}>Remove</Button>}</Flex>
              <div className="builder-grid">
                <Box><Text as="label" htmlFor={`${control.key}-label`} size="2">Label</Text><TextField.Root id={`${control.key}-label`} value={control.label} onChange={(event) => updateControl(control.key, { label: event.target.value, id: control.id || machineName(event.target.value) })} /></Box>
                <Box><Text as="label" htmlFor={`${control.key}-id`} size="2">Machine name</Text><TextField.Root id={`${control.key}-id`} value={control.id} onChange={(event) => updateControl(control.key, { id: machineName(event.target.value) })} /></Box>
                <Box><Text as="label" htmlFor={`${control.key}-type`} size="2">Control type</Text><Select.Root value={control.type} onValueChange={(type) => updateControl(control.key, { type: type as StyleControl['type'] })}><Select.Trigger id={`${control.key}-type`} /><Select.Content><Select.Item value="color">Color</Select.Item><Select.Item value="dimension">Dimension</Select.Item><Select.Item value="number">Number</Select.Item><Select.Item value="text_token">Text token</Select.Item></Select.Content></Select.Root></Box>
                <Box><Text as="label" htmlFor={`${control.key}-property`} size="2">CSS variable</Text><TextField.Root id={`${control.key}-property`} placeholder="--primary" value={control.property} onChange={(event) => updateControl(control.key, { property: event.target.value })} /></Box>
                <Box className="builder-wide"><Text as="label" htmlFor={`${control.key}-default`} size="2">Default value</Text><TextField.Root id={`${control.key}-default`} value={control.defaultValue} onChange={(event) => updateControl(control.key, { defaultValue: event.target.value })} /></Box>
              </div>
            </Card>
          ))}
          {error && <Callout.Root color="red" role="alert"><Callout.Icon><ExclamationTriangleIcon /></Callout.Icon><Callout.Text>{error}</Callout.Text></Callout.Root>}
          <Flex justify="end" gap="3"><Dialog.Close><Button variant="soft" color="gray">Cancel</Button></Dialog.Close><Button disabled={busy} onClick={submit}>Create style guide</Button></Flex>
        </Flex>
      </Dialog.Content>
    </Dialog.Root>
  );
}

function newBuilderControl(): BuilderControl {
  return { key: crypto.randomUUID(), id: '', label: '', type: 'color', property: '', defaultValue: '#3366ff' };
}

function machineName(value: string): string {
  return value.toLowerCase().trim().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').replace(/^[^a-z]+/, '');
}

function normalizeProperty(value: string): string {
  const name = value.trim().replace(/^--/, '').replace(/[^a-zA-Z0-9_-]+/g, '-');
  return `--${name}`;
}

function parseBuilderValue(control: BuilderControl): StyleValue {
  return control.type === 'number' ? Number(control.defaultValue) : control.defaultValue;
}

function defaultConstraints(type: StyleControl['type']): StyleControl['constraints'] {
  if (type === 'dimension') return { units: ['px', 'rem', 'em', '%'] };
  return {};
}

function PropertyEditor({ controlId, contextId, contextLabel, control, value, overridden, changed, onChange, onReset, palettes, fonts }: {
  controlId: string;
  contextId: string;
  contextLabel: string;
  control: StyleControl;
  value: StyleValue;
  overridden: boolean;
  changed: boolean;
  onChange: (value: StyleValue) => void;
  onReset: () => void;
  palettes: Palette[];
  fonts: FontFamily[];
}) {
  const inputId = `${controlId}-${contextId}`;
  return (
    <div className={`property-row${changed ? ' is-changed' : ''}`}>
      <div className="property-row__label">
        <Flex gap="2" align="center" wrap="wrap">
          <Text as="label" htmlFor={inputId} size="2" weight="bold">{control.label}</Text>
          <Badge size="1" color={overridden ? 'green' : 'gray'}>{overridden ? 'Override' : 'Theme default'}</Badge>
          {changed && <Badge size="1" color="amber">Changed</Badge>}
        </Flex>
        {control.description && <Text as="p" size="1" color="gray" mt="1">{control.description}</Text>}
      </div>
      <div className="property-row__control">
        <ControlInput id={inputId} control={control} value={value} palettes={palettes} fonts={fonts} onChange={onChange} />
      </div>
      <Button
        className="property-row__reset"
        aria-label={`Use theme default for ${control.label} on ${contextLabel}`}
        title="Use theme default"
        size="1"
        variant="ghost"
        color="gray"
        disabled={!overridden}
        onClick={onReset}
      >
        <ResetIcon />
      </Button>
    </div>
  );
}

function ControlInput({ id, control, value, palettes, fonts, onChange }: { id: string; control: StyleControl; value: StyleValue; palettes: Palette[]; fonts: FontFamily[]; onChange: (value: StyleValue) => void }) {
  if (control.type === 'boolean_map') {
    return <Switch id={id} checked={Boolean(value)} onCheckedChange={onChange} />;
  }
  if (control.type === 'select') {
    return (
      <Select.Root value={String(value)} onValueChange={onChange}>
        <Select.Trigger id={id} />
        <Select.Content>{(control.constraints.options ?? []).map((option) => <Select.Item key={String(option)} value={String(option)}>{String(option)}</Select.Item>)}</Select.Content>
      </Select.Root>
    );
  }
  if (control.type === 'palette_color') {
    // Gradients are excluded: a palette_color control feeds a CSS property
    // expecting a <color>, and a gradient is an <image>.
    const options = palettes.flatMap((palette) => palette.colors
      .filter((color) => color.type !== 'gradient')
      .map((color) => ({ value: `${palette.id}:${color.id}`, label: `${palette.label} · ${color.label}` })));
    return <Select.Root value={String(value)} onValueChange={onChange}><Select.Trigger id={id} placeholder="Choose a palette color" /><Select.Content>{options.map((option) => <Select.Item key={option.value} value={option.value}>{option.label}</Select.Item>)}</Select.Content></Select.Root>;
  }
  if (control.type === 'font_family') {
    return <Select.Root value={String(value)} onValueChange={onChange}><Select.Trigger id={id} placeholder="Choose a font family" /><Select.Content>{fonts.map((font) => <Select.Item key={font.id} value={font.id}>{font.label}</Select.Item>)}</Select.Content></Select.Root>;
  }
  if (control.type === 'color') {
    const current = String(value);
    const parsed = parseHexColor(current);
    return (
      <Flex gap="2" align="center">
        {parsed
          ? <input
              id={`${id}-picker`}
              className="color-input"
              type="color"
              value={parsed.hex}
              // Preserve the opacity already set; only the hue changes here.
              onChange={(event) => onChange(composeHexColor(event.target.value, parsed.alpha))}
              aria-label={`${id} color picker`}
            />
          // A value such as rgba(…) or var(…) cannot be shown by a hue picker,
          // so it gets a preview and stays editable as text.
          : <span className="color-input color-preview" style={{ '--canvas-utilities-swatch-color': current } as CSSProperties} title={current} />}
        {parsed && (
          <Flex align="center" gap="1" className="palette-alpha">
            <input
              type="range"
              min={0}
              max={100}
              value={alphaPercent(current)}
              aria-label={`${id} opacity`}
              title={`Opacity ${alphaPercent(current)}%`}
              onChange={(event) => onChange(composeHexColor(parsed.hex, Number(event.target.value) / 100))}
            />
            <Text size="1" color="gray" className="palette-alpha__value">{alphaPercent(current)}%</Text>
          </Flex>
        )}
        <TextField.Root id={id} value={current} onChange={(event) => onChange(event.target.value)} aria-label={`${id} CSS color`} />
      </Flex>
    );
  }
  if (control.type === 'number') {
    return <TextField.Root id={id} type="number" value={String(value)} min={control.constraints.min} max={control.constraints.max} step={control.constraints.step ?? 'any'} onChange={(event) => onChange(Number(event.target.value))} />;
  }
  return <TextField.Root id={id} value={String(value)} onChange={(event) => onChange(event.target.value)} />;
}

function Message({ message }: { message: { kind: 'success' | 'error'; text: string } }) {
  return (
    <Callout.Root color={message.kind === 'success' ? 'green' : 'red'} mt="5" role={message.kind === 'error' ? 'alert' : 'status'}>
      <Callout.Icon>{message.kind === 'success' ? <CheckCircledIcon /> : <ExclamationTriangleIcon />}</Callout.Icon>
      <Callout.Text>{message.text}</Callout.Text>
    </Callout.Root>
  );
}
