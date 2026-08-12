import { useEffect, useState } from 'react';
import { Box, Button, Callout, Card, Dialog, Flex, Heading, Text, TextField } from '@radix-ui/themes';
import { ExclamationTriangleIcon, PlusIcon } from '@radix-ui/react-icons';

import { createPalette, deletePalette, getPalettes } from '../palette-api';
import type { Palette, PaletteColor } from '../palette-api';

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
        <Box><Heading id="palettes-heading" size="7">Colors</Heading><Text as="p" color="gray" mt="2">Build approved palettes and expose their colors as reusable CSS variables.</Text></Box>
        <PaletteBuilder theme={theme} csrfToken={csrfToken} onCreated={() => load()} />
      </Flex>
      {error && <Callout.Root mt="5" color="red" role="alert"><Callout.Icon><ExclamationTriangleIcon /></Callout.Icon><Callout.Text>{error}</Callout.Text></Callout.Root>}
      {!loading && palettes.length === 0 && <Card className="empty-card" mt="6"><Heading size="4">No palettes yet</Heading><Text as="p" color="gray" mt="2">Create an approved set of brand or semantic colors.</Text></Card>}
      <div className="palette-grid">
        {palettes.map((palette) => (
          <Card key={palette.id} className="palette-card">
            <Flex justify="between" align="center" gap="3"><Heading size="4">{palette.label}</Heading><Button size="1" variant="ghost" color="red" onClick={async () => { if (window.confirm(`Delete ${palette.label}?`)) { try { await deletePalette(theme, palette.id, csrfToken); await load(); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Delete failed.'); } } }}>Delete</Button></Flex>
            <Text as="p" size="2" color="gray">{palette.description || `CSS prefix: --${palette.prefix}-…`}</Text>
            <div className="swatch-row">
              {palette.colors.map((color) => <div key={color.id} className="swatch" title={`${color.label}: ${color.value}`} style={{ backgroundColor: color.value }}><span>{color.label}</span></div>)}
            </div>
          </Card>
        ))}
      </div>
    </section>
  );
}

function PaletteBuilder({ theme, csrfToken, onCreated }: { theme: string; csrfToken: string; onCreated: () => Promise<void> | void }) {
  const [open, setOpen] = useState(false);
  const [label, setLabel] = useState('');
  const [id, setId] = useState('');
  const [prefix, setPrefix] = useState('color');
  const [colors, setColors] = useState<PaletteColor[]>([{ id: 'primary', label: 'Primary', value: '#3366ff', role: '' }]);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    try {
      await createPalette(theme, { id, label, description: '', prefix, colors }, csrfToken);
      await onCreated();
      setOpen(false);
      setError(null);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Unable to create the palette.');
    }
  };

  return (
    <Dialog.Root open={open} onOpenChange={setOpen}>
      <Dialog.Trigger><Button><PlusIcon />New palette</Button></Dialog.Trigger>
      <Dialog.Content maxWidth="680px">
        <Dialog.Title>New color palette</Dialog.Title><Dialog.Description>Create an approved set of colors for this theme.</Dialog.Description>
        <Flex direction="column" gap="4" mt="5">
          <div className="builder-grid">
            <Box><Text as="label" htmlFor="palette-name" size="2">Name</Text><TextField.Root id="palette-name" value={label} onChange={(event) => { setLabel(event.target.value); if (!id) setId(machineName(event.target.value)); }} /></Box>
            <Box><Text as="label" htmlFor="palette-id" size="2">Machine name</Text><TextField.Root id="palette-id" value={id} onChange={(event) => setId(machineName(event.target.value))} /></Box>
            <Box><Text as="label" htmlFor="palette-prefix" size="2">CSS prefix</Text><TextField.Root id="palette-prefix" value={prefix} onChange={(event) => setPrefix(machineName(event.target.value))} /></Box>
          </div>
          <Flex justify="between"><Heading size="4">Colors</Heading><Button variant="soft" size="1" onClick={() => setColors((current) => [...current, { id: '', label: '', value: '#000000', role: '' }])}>Add color</Button></Flex>
          {colors.map((color, index) => (
            <div className="palette-color-row" key={index}>
              <input type="color" aria-label={`Color ${index + 1}`} value={color.value} onChange={(event) => setColors(updateAt(colors, index, { value: event.target.value }))} />
              <TextField.Root aria-label={`Color ${index + 1} label`} placeholder="Label" value={color.label} onChange={(event) => setColors(updateAt(colors, index, { label: event.target.value, id: color.id || machineName(event.target.value) }))} />
              <TextField.Root aria-label={`Color ${index + 1} ID`} placeholder="Machine name" value={color.id} onChange={(event) => setColors(updateAt(colors, index, { id: machineName(event.target.value) }))} />
              <TextField.Root aria-label={`Color ${index + 1} value`} value={color.value} onChange={(event) => setColors(updateAt(colors, index, { value: event.target.value }))} />
              {colors.length > 1 && <Button size="1" variant="ghost" color="red" onClick={() => setColors(colors.filter((_, itemIndex) => itemIndex !== index))}>Remove</Button>}
            </div>
          ))}
          {error && <Callout.Root color="red" role="alert"><Callout.Text>{error}</Callout.Text></Callout.Root>}
          <Flex justify="end" gap="3"><Dialog.Close><Button color="gray" variant="soft">Cancel</Button></Dialog.Close><Button onClick={submit}>Create palette</Button></Flex>
        </Flex>
      </Dialog.Content>
    </Dialog.Root>
  );
}

function machineName(value: string): string {
  return value.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').replace(/^[^a-z]+/, '');
}

function updateAt(colors: PaletteColor[], index: number, patch: Partial<PaletteColor>): PaletteColor[] {
  return colors.map((color, currentIndex) => currentIndex === index ? { ...color, ...patch } : color);
}
