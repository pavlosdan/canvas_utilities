import { useEffect, useState } from 'react';
import { Badge, Box, Button, Callout, Card, Dialog, Flex, Heading, IconButton, Select, Text, TextField } from '@radix-ui/themes';
import { ExclamationTriangleIcon, Pencil1Icon, PlusIcon } from '@radix-ui/react-icons';

import ConfirmButton from './ConfirmButton';
import { createPalette, deletePalette, getPalettes, updatePalette } from '../palette-api';
import type { Palette, PaletteColor, PaletteEntryType } from '../palette-api';

const DEFAULT_GRADIENT = 'linear-gradient(90deg, #3366ff 0%, #ff33aa 100%)';

export default function PaletteWorkspace({ theme, csrfToken }: { theme: string; csrfToken: string }) {
  const [palettes, setPalettes] = useState<Palette[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = async (signal?: AbortSignal) => {
    setPalettes(await getPalettes(theme, signal));
    setLoading(false);
  };

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    load(controller.signal).catch((reason: unknown) => {
      if (reason instanceof Error && reason.name !== 'AbortError') setError(reason.message);
      setLoading(false);
    });
    return () => controller.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [theme]);

  return (
    <section aria-labelledby="palettes-heading">
      <Flex justify="between" align="start" gap="4">
        <Box><Heading id="palettes-heading" size="7">Colors</Heading><Text as="p" color="gray" mt="2">Build approved palettes and expose their colors and gradients as reusable CSS variables.</Text></Box>
        <PaletteDialog theme={theme} csrfToken={csrfToken} onSaved={() => load()} />
      </Flex>
      {error && <Callout.Root mt="5" color="red" role="alert"><Callout.Icon><ExclamationTriangleIcon /></Callout.Icon><Callout.Text>{error}</Callout.Text></Callout.Root>}
      {!loading && palettes.length === 0 && <Card className="empty-card" mt="6"><Heading size="4">No palettes yet</Heading><Text as="p" color="gray" mt="2">Create an approved set of brand or semantic colors.</Text></Card>}
      <div className="palette-grid">
        {palettes.map((palette) => (
          <Card key={palette.id} className="palette-card">
            <Flex justify="between" align="center" gap="3">
              <Heading size="4">{palette.label}</Heading>
              <Flex gap="2" align="center">
                <PaletteDialog theme={theme} csrfToken={csrfToken} palette={palette} onSaved={() => load()} />
                <ConfirmButton
                  size="1"
                  variant="ghost"
                  color="red"
                  title={`Delete ${palette.label}?`}
                  description={`The palette is removed along with the --${palette.prefix}-… custom properties it publishes. Anything styled with those variables loses them.`}
                  onConfirm={async () => {
                    setError(null);
                    try {
                      await deletePalette(theme, palette.id, csrfToken);
                      await load();
                    } catch (reason) {
                      setError(reason instanceof Error ? reason.message : 'Delete failed.');
                    }
                  }}
                >Delete</ConfirmButton>
              </Flex>
            </Flex>
            <Text as="p" size="2" color="gray">{palette.description || `CSS prefix: --${palette.prefix}-…`}</Text>
            <div className="swatch-row">
              {palette.colors.map((color) => (
                <div
                  key={color.id}
                  className={`swatch${color.type === 'gradient' ? ' swatch--gradient' : ''}`}
                  title={`${color.label}: ${color.value}`}
                  style={color.type === 'gradient' ? { backgroundImage: color.value } : { backgroundColor: color.value }}
                ><span>{color.label}</span></div>
              ))}
            </div>
          </Card>
        ))}
      </div>
    </section>
  );
}

/**
 * Creates a palette, or edits an existing one.
 *
 * The ID and CSS prefix are only offered while creating. Both appear in the
 * `var(--prefix-id)` references already stored on components and in theme CSS,
 * so changing them later would silently break every use.
 */
function PaletteDialog({ theme, csrfToken, palette, onSaved }: {
  theme: string;
  csrfToken: string;
  palette?: Palette;
  onSaved: () => Promise<void> | void;
}) {
  const editing = palette !== undefined;
  const [open, setOpen] = useState(false);
  const [label, setLabel] = useState('');
  const [id, setId] = useState('');
  const [description, setDescription] = useState('');
  const [prefix, setPrefix] = useState('color');
  const [colors, setColors] = useState<PaletteColor[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const reset = () => {
    setLabel(palette?.label ?? '');
    setId(palette?.id ?? '');
    setDescription(palette?.description ?? '');
    setPrefix(palette?.prefix ?? 'color');
    setColors(palette ? palette.colors.map((color) => ({ ...color })) : [newEntry('color', 'Primary', 'primary')]);
    setError(null);
  };

  const submit = async () => {
    if (!label.trim()) return setError('Enter a palette name.');
    if (colors.length === 0) return setError('Add at least one color or gradient.');
    setBusy(true);
    try {
      if (editing) {
        await updatePalette(theme, palette.id, { label, description, colors }, csrfToken);
      } else {
        await createPalette(theme, { id, label, description, prefix, colors }, csrfToken);
      }
      await onSaved();
      setOpen(false);
      setError(null);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Unable to save the palette.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog.Root open={open} onOpenChange={(next) => { setOpen(next); if (next) reset(); }}>
      <Dialog.Trigger>
        {editing
          ? <IconButton size="1" variant="ghost" aria-label={`Edit ${palette.label}`}><Pencil1Icon /></IconButton>
          : <Button><PlusIcon />New palette</Button>}
      </Dialog.Trigger>
      <Dialog.Content maxWidth="760px">
        <Dialog.Title>{editing ? `Edit ${palette.label}` : 'New color palette'}</Dialog.Title>
        <Dialog.Description>
          {editing
            ? 'The machine name and CSS prefix are fixed, because existing components reference them.'
            : 'Create an approved set of colors and gradients for this theme.'}
        </Dialog.Description>
        <Flex direction="column" gap="4" mt="5">
          <div className="builder-grid">
            <Box>
              <Text as="label" htmlFor="palette-name" size="2">Name</Text>
              <TextField.Root id="palette-name" value={label} onChange={(event) => { setLabel(event.target.value); if (!editing && !id) setId(machineName(event.target.value)); }} />
            </Box>
            <Box>
              <Text as="label" htmlFor="palette-id" size="2">Machine name</Text>
              <TextField.Root id="palette-id" value={id} disabled={editing} onChange={(event) => setId(machineName(event.target.value))} />
            </Box>
            <Box>
              <Text as="label" htmlFor="palette-prefix" size="2">CSS prefix</Text>
              <TextField.Root id="palette-prefix" value={prefix} disabled={editing} onChange={(event) => setPrefix(machineName(event.target.value))} />
            </Box>
          </div>
          <Box>
            <Text as="label" htmlFor="palette-description" size="2">Description</Text>
            <TextField.Root id="palette-description" value={description} onChange={(event) => setDescription(event.target.value)} />
          </Box>

          <Flex justify="between" align="center">
            <Heading size="4">Colors and gradients</Heading>
            <Flex gap="2">
              <Button variant="soft" size="1" onClick={() => setColors((current) => [...current, newEntry('color')])}>Add color</Button>
              <Button variant="soft" size="1" onClick={() => setColors((current) => [...current, newEntry('gradient')])}>Add gradient</Button>
            </Flex>
          </Flex>

          {colors.map((color, index) => (
            <div className="palette-color-row" key={index}>
              <Select.Root
                value={color.type}
                onValueChange={(value) => setColors(updateAt(colors, index, {
                  type: value as PaletteEntryType,
                  value: value === 'gradient' ? DEFAULT_GRADIENT : '#000000',
                }))}
              >
                <Select.Trigger aria-label={`Entry ${index + 1} type`} />
                <Select.Content>
                  <Select.Item value="color">Color</Select.Item>
                  <Select.Item value="gradient">Gradient</Select.Item>
                </Select.Content>
              </Select.Root>

              {color.type === 'gradient'
                ? <span className="palette-gradient-preview" style={{ backgroundImage: color.value }} aria-hidden="true" />
                : <input type="color" aria-label={`Color ${index + 1}`} value={color.value} onChange={(event) => setColors(updateAt(colors, index, { value: event.target.value }))} />}

              <TextField.Root aria-label={`Entry ${index + 1} label`} placeholder="Label" value={color.label} onChange={(event) => setColors(updateAt(colors, index, { label: event.target.value, id: color.id || machineName(event.target.value) }))} />
              <TextField.Root aria-label={`Entry ${index + 1} ID`} placeholder="Machine name" value={color.id} onChange={(event) => setColors(updateAt(colors, index, { id: machineName(event.target.value) }))} />
              <TextField.Root
                aria-label={`Entry ${index + 1} value`}
                placeholder={color.type === 'gradient' ? 'linear-gradient(…)' : '#000000'}
                value={color.value}
                onChange={(event) => setColors(updateAt(colors, index, { value: event.target.value }))}
              />
              {colors.length > 1 && <Button size="1" variant="ghost" color="red" onClick={() => setColors(colors.filter((_, itemIndex) => itemIndex !== index))}>Remove</Button>}
            </div>
          ))}

          {colors.some((color) => color.type === 'gradient') && (
            <Callout.Root color="gray">
              <Callout.Text size="2">
                Gradients are published as CSS variables for use in theme or custom CSS. They are not offered to component color props, because a gradient is a background image and is invalid wherever a color is expected.
              </Callout.Text>
            </Callout.Root>
          )}
          {error && <Callout.Root color="red" role="alert"><Callout.Text>{error}</Callout.Text></Callout.Root>}
          <Flex justify="end" gap="3">
            <Dialog.Close><Button color="gray" variant="soft">Cancel</Button></Dialog.Close>
            <Button onClick={submit} loading={busy}>{editing ? 'Save palette' : 'Create palette'}</Button>
          </Flex>
        </Flex>
      </Dialog.Content>
    </Dialog.Root>
  );
}

function newEntry(type: PaletteEntryType, label = '', id = ''): PaletteColor {
  return {
    id,
    label,
    value: type === 'gradient' ? DEFAULT_GRADIENT : '#3366ff',
    role: '',
    type,
  };
}

function machineName(value: string): string {
  return value.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').replace(/^[^a-z]+/, '');
}

function updateAt(colors: PaletteColor[], index: number, patch: Partial<PaletteColor>): PaletteColor[] {
  return colors.map((color, currentIndex) => currentIndex === index ? { ...color, ...patch } : color);
}
