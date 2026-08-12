import { useEffect, useMemo, useRef, useState } from 'react';
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
import { CheckCircledIcon, ExclamationTriangleIcon, ResetIcon } from '@radix-ui/react-icons';

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
import { getFonts } from '../font-api';
import type { FontFamily } from '../font-api';

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
      <section aria-labelledby="style-guide-heading">
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
    <section aria-labelledby="style-guide-heading">
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
        <div className="style-guide-layout">
          <div className="style-groups">
          {Object.entries(active.groups).map(([groupId, group]) => (
            <Card key={groupId} className="style-group">
              <Heading size="5">{group.label}</Heading>
              <Separator size="4" my="4" />
              <Flex direction="column" gap="5">
                {Object.entries(group.controls).map(([controlId, control]) => (
                  <ControlEditor
                    key={controlId}
                    controlId={controlId}
                    control={control}
                    contexts={active.contexts}
                    values={values[controlId] ?? {}}
                    onChange={setValue}
                    onReset={resetValue}
                    palettes={palettes}
                    fonts={fonts}
                  />
                ))}
              </Flex>
            </Card>
          ))}
          </div>
          <LivePreview guide={active} values={values} palettes={palettes} fonts={fonts} />
        </div>
      )}
    </section>
  );
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
  return <aside className="preview-panel" aria-label="Live theme preview"><Flex justify="between" align="center" mb="3"><Heading size="4">Live preview</Heading><Badge color="green">Draft values</Badge></Flex><iframe ref={frame} title={`${guide.label} preview`} src={guide.preview.path} sandbox="allow-same-origin" onLoad={update} /></aside>;
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

function ControlEditor({ controlId, control, contexts, values, onChange, onReset, palettes, fonts }: {
  controlId: string;
  control: StyleControl;
  contexts: StyleGuide['contexts'];
  values: Record<string, StyleValue>;
  onChange: (controlId: string, contextId: string, value: StyleValue) => void;
  onReset: (controlId: string, contextId: string) => void;
  palettes: Palette[];
  fonts: FontFamily[];
}) {
  return (
    <fieldset className="control-fieldset">
      <legend><Text weight="bold">{control.label}</Text></legend>
      {control.description && <Text as="p" size="2" color="gray" mt="1">{control.description}</Text>}
      <div className="context-grid">
        {Object.entries(control.targets).map(([contextId]) => {
          const inherited = values[contextId] === undefined;
          const value = values[contextId] ?? control.default[contextId] ?? '';
          return (
            <div key={contextId} className="context-control">
              <Flex justify="between" align="center" mb="2">
                <Text as="label" htmlFor={`${controlId}-${contextId}`} size="2" weight="medium">{contexts[contextId]?.label ?? contextId}</Text>
                <Flex gap="2" align="center">
                  <Badge color={inherited ? 'gray' : 'green'}>{inherited ? 'Default' : 'Overridden'}</Badge>
                  {!inherited && <Button aria-label={`Reset ${control.label} for ${contexts[contextId]?.label}`} size="1" variant="ghost" color="gray" onClick={() => onReset(controlId, contextId)}><ResetIcon /></Button>}
                </Flex>
              </Flex>
              <ControlInput id={`${controlId}-${contextId}`} control={control} value={value} palettes={palettes} fonts={fonts} onChange={(next) => onChange(controlId, contextId, next)} />
            </div>
          );
        })}
      </div>
    </fieldset>
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
    const options = palettes.flatMap((palette) => palette.colors.map((color) => ({ value: `${palette.id}:${color.id}`, label: `${palette.label} · ${color.label}` })));
    return <Select.Root value={String(value)} onValueChange={onChange}><Select.Trigger id={id} placeholder="Choose a palette color" /><Select.Content>{options.map((option) => <Select.Item key={option.value} value={option.value}>{option.label}</Select.Item>)}</Select.Content></Select.Root>;
  }
  if (control.type === 'font_family') {
    return <Select.Root value={String(value)} onValueChange={onChange}><Select.Trigger id={id} placeholder="Choose a font family" /><Select.Content>{fonts.map((font) => <Select.Item key={font.id} value={font.id}>{font.label}</Select.Item>)}</Select.Content></Select.Root>;
  }
  if (control.type === 'color') {
    return (
      <Flex gap="2" align="center">
        <input id={`${id}-picker`} className="color-input" type="color" value={toHexColor(String(value))} onChange={(event) => onChange(event.target.value)} aria-label={`${id} color picker`} />
        <TextField.Root id={id} value={String(value)} onChange={(event) => onChange(event.target.value)} aria-label={`${id} CSS color`} />
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

function toHexColor(value: string): string {
  return /^#[0-9a-f]{6}$/i.test(value) ? value : '#000000';
}
