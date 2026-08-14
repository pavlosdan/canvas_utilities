import { useEffect, useState } from 'react';
import { Badge, Box, Button, Callout, Card, Dialog, Flex, Heading, IconButton, Select, Text, TextField } from '@radix-ui/themes';
import { ExclamationTriangleIcon, GlobeIcon, Pencil1Icon, PlusIcon, UploadIcon } from '@radix-ui/react-icons';

import ConfirmButton from './ConfirmButton';
import { createRemoteFont, deleteFont, getFonts, updateFont, uploadFont } from '../font-api';
import type { FontFamily } from '../font-api';

export default function FontWorkspace({ theme, csrfToken }: { theme: string; csrfToken: string }) {
  const [fonts, setFonts] = useState<FontFamily[]>([]);
  const [error, setError] = useState<string | null>(null);
  const load = async (signal?: AbortSignal) => setFonts(await getFonts(theme, signal));
  useEffect(() => {
    const controller = new AbortController();
    load(controller.signal).catch((reason: unknown) => { if (reason instanceof Error && reason.name !== 'AbortError') setError(reason.message); });
    return () => controller.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [theme]);
  return (
    <section aria-labelledby="fonts-heading">
      <Flex justify="between" align="start" gap="4" wrap="wrap">
        <Box><Heading id="fonts-heading" size="7">Fonts</Heading><Text as="p" color="gray" mt="2">Upload self-hosted font files or link a remote CSS provider.</Text></Box>
        <Flex gap="2"><LocalFontDialog theme={theme} csrfToken={csrfToken} onCreated={() => load()} /><RemoteFontDialog theme={theme} csrfToken={csrfToken} onCreated={() => load()} /></Flex>
      </Flex>
      <Callout.Root color="amber" mt="5"><Callout.Icon><GlobeIcon /></Callout.Icon><Callout.Text>Remote fonts send visitor network information to their provider. Confirm privacy and CSP requirements before use.</Callout.Text></Callout.Root>
      {error && <Callout.Root color="red" role="alert" mt="4"><Callout.Icon><ExclamationTriangleIcon /></Callout.Icon><Callout.Text>{error}</Callout.Text></Callout.Root>}
      <div className="font-grid">
        {fonts.map((font) => <Card key={font.id} className="font-card"><Flex justify="between"><Heading size="5">{font.label}</Heading><Flex gap="2" align="center"><Badge color={font.provider === 'local_file' ? 'green' : 'blue'}>{font.provider === 'local_file' ? 'Self-hosted' : 'Remote CSS'}</Badge><EditFontDialog theme={theme} csrfToken={csrfToken} font={font} onSaved={() => load()} onError={setError} /><ConfirmButton size="1" color="red" variant="ghost" title={`Delete ${font.label}?`} description={font.provider === 'local_file' ? 'The font family is removed and its uploaded files are deleted. Text styled with it falls back to the next family in the stack.' : 'The font family is removed and its remote stylesheet is no longer linked. Text styled with it falls back to the next family in the stack.'} onConfirm={async () => { setError(null); try { await deleteFont(theme, font.id, csrfToken); await load(); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Delete failed.'); } }}>Delete</ConfirmButton></Flex></Flex><Text as="p" mt="5" size="7" style={{ fontFamily: `'${font.family}', ${font.fallbacks}` }}>Aa</Text><Text as="p" mt="2" color="gray">{font.family}, {font.fallbacks}</Text></Card>)}
        {fonts.length === 0 && <Card className="empty-card"><Heading size="4">No fonts yet</Heading><Text as="p" color="gray" mt="2">Add a self-hosted face or an approved remote stylesheet.</Text></Card>}
      </div>
    </section>
  );
}

function LocalFontDialog({ theme, csrfToken, onCreated }: DialogProps) {
  const [open, setOpen] = useState(false);
  const [family, setFamily] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [weight, setWeight] = useState('400');
  const [error, setError] = useState<string | null>(null);
  const submit = async () => {
    if (!file) return setError('Choose a WOFF2, WOFF, TTF, or OTF file.');
    const data = new FormData(); data.set('font', file); data.set('family', family); data.set('label', family); data.set('id', machineName(family)); data.set('weight', weight); data.set('style', 'normal'); data.set('fallbacks', 'sans-serif');
    try { await uploadFont(theme, data, csrfToken); await onCreated(); setOpen(false); setError(null); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Upload failed.'); }
  };
  return <Dialog.Root open={open} onOpenChange={setOpen}><Dialog.Trigger><Button variant="soft"><UploadIcon />Upload font</Button></Dialog.Trigger><Dialog.Content maxWidth="560px"><Dialog.Title>Upload font</Dialog.Title><Dialog.Description>Maximum 10 MB. Supported formats: WOFF2, WOFF, TTF, and OTF.</Dialog.Description><Flex direction="column" gap="4" mt="5"><Box><Text as="label" htmlFor="local-family">Family name</Text><TextField.Root id="local-family" value={family} onChange={(event) => setFamily(event.target.value)} /></Box><Box><Text as="label" htmlFor="local-font-file">Font file</Text><input className="file-input" id="local-font-file" type="file" accept=".woff2,.woff,.ttf,.otf" onChange={(event) => setFile(event.target.files?.[0] ?? null)} /></Box><Box><Text as="label" htmlFor="local-weight">Weight</Text><Select.Root value={weight} onValueChange={setWeight}><Select.Trigger id="local-weight" /><Select.Content>{['100','200','300','400','500','600','700','800','900'].map((value) => <Select.Item key={value} value={value}>{value}</Select.Item>)}</Select.Content></Select.Root></Box>{error && <Callout.Root color="red" role="alert"><Callout.Text>{error}</Callout.Text></Callout.Root>}<Flex justify="end" gap="3"><Dialog.Close><Button variant="soft" color="gray">Cancel</Button></Dialog.Close><Button onClick={submit}>Upload</Button></Flex></Flex></Dialog.Content></Dialog.Root>;
}

function RemoteFontDialog({ theme, csrfToken, onCreated }: DialogProps) {
  const [open, setOpen] = useState(false); const [family, setFamily] = useState(''); const [url, setUrl] = useState(''); const [error, setError] = useState<string | null>(null);
  const submit = async () => { try { await createRemoteFont(theme, { family, label: family, id: machineName(family), fallbacks: 'sans-serif', url }, csrfToken); await onCreated(); setOpen(false); setError(null); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Unable to add remote font.'); } };
  return <Dialog.Root open={open} onOpenChange={setOpen}><Dialog.Trigger><Button><PlusIcon />Remote CSS</Button></Dialog.Trigger><Dialog.Content maxWidth="560px"><Dialog.Title>Add remote font stylesheet</Dialog.Title><Dialog.Description>The browser will request this HTTPS URL on visitor pages. Canvas Utilities does not fetch it on the server.</Dialog.Description><Flex direction="column" gap="4" mt="5"><Box><Text as="label" htmlFor="remote-family">Family name</Text><TextField.Root id="remote-family" value={family} onChange={(event) => setFamily(event.target.value)} /></Box><Box><Text as="label" htmlFor="remote-url">Stylesheet URL</Text><TextField.Root id="remote-url" type="url" placeholder="https://fonts.example.com/family.css" value={url} onChange={(event) => setUrl(event.target.value)} /></Box>{error && <Callout.Root color="red" role="alert"><Callout.Text>{error}</Callout.Text></Callout.Root>}<Flex justify="end" gap="3"><Dialog.Close><Button variant="soft" color="gray">Cancel</Button></Dialog.Close><Button onClick={submit}>Add stylesheet</Button></Flex></Flex></Dialog.Content></Dialog.Root>;
}

/**
 * Edits a font family that already exists.
 *
 * The machine name and provider are fixed: the ID appears in the generated CSS
 * variable names, and a self-hosted family cannot become a remote one without
 * different assets entirely.
 */
function EditFontDialog({ theme, csrfToken, font, onSaved, onError }: {
  theme: string;
  csrfToken: string;
  font: FontFamily;
  onSaved: () => Promise<void> | void;
  onError: (message: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const [label, setLabel] = useState(font.label);
  const [family, setFamily] = useState(font.family);
  const [fallbacks, setFallbacks] = useState(font.fallbacks);
  const [url, setUrl] = useState(font.remoteUrl ?? '');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const isRemote = font.provider === 'remote_stylesheet';

  const submit = async () => {
    if (!label.trim() || !family.trim()) return setError('Enter a name and a family.');
    setBusy(true);
    try {
      await updateFont(theme, font.id, { label, family, fallbacks, ...(isRemote ? { url } : {}) }, csrfToken);
      await onSaved();
      setOpen(false);
      setError(null);
    } catch (reason) {
      const message = reason instanceof Error ? reason.message : 'Unable to save the font.';
      setError(message);
      onError(message);
    } finally {
      setBusy(false);
    }
  };

  return <Dialog.Root open={open} onOpenChange={(next) => {
    setOpen(next);
    if (next) { setLabel(font.label); setFamily(font.family); setFallbacks(font.fallbacks); setUrl(font.remoteUrl ?? ''); setError(null); }
  }}>
    <Dialog.Trigger><IconButton size="1" variant="ghost" aria-label={`Edit ${font.label}`}><Pencil1Icon /></IconButton></Dialog.Trigger>
    <Dialog.Content maxWidth="560px">
      <Dialog.Title>Edit {font.label}</Dialog.Title>
      <Dialog.Description>The machine name and source type are fixed once a family exists.</Dialog.Description>
      <Flex direction="column" gap="4" mt="5">
        <Box><Text as="label" htmlFor={`edit-font-label-${font.id}`}>Name</Text><TextField.Root id={`edit-font-label-${font.id}`} value={label} onChange={(event) => setLabel(event.target.value)} /></Box>
        <Box><Text as="label" htmlFor={`edit-font-family-${font.id}`}>Family name</Text><TextField.Root id={`edit-font-family-${font.id}`} value={family} onChange={(event) => setFamily(event.target.value)} /></Box>
        <Box><Text as="label" htmlFor={`edit-font-fallbacks-${font.id}`}>Fallbacks</Text><TextField.Root id={`edit-font-fallbacks-${font.id}`} placeholder="sans-serif" value={fallbacks} onChange={(event) => setFallbacks(event.target.value)} /></Box>
        {isRemote && <Box><Text as="label" htmlFor={`edit-font-url-${font.id}`}>Stylesheet URL</Text><TextField.Root id={`edit-font-url-${font.id}`} type="url" value={url} onChange={(event) => setUrl(event.target.value)} /></Box>}
        <Text as="p" size="1" color="gray">Preview: <span style={{ fontFamily: `'${family}', ${fallbacks}`, fontSize: 20 }}>Aa Bb Cc</span></Text>
        {error && <Callout.Root color="red" role="alert"><Callout.Text>{error}</Callout.Text></Callout.Root>}
        <Flex justify="end" gap="3">
          <Dialog.Close><Button variant="soft" color="gray">Cancel</Button></Dialog.Close>
          <Button onClick={submit} loading={busy}>Save</Button>
        </Flex>
      </Flex>
    </Dialog.Content>
  </Dialog.Root>;
}

interface DialogProps { theme: string; csrfToken: string; onCreated: () => Promise<void> | void }
function machineName(value: string): string { return value.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').replace(/^[^a-z]+/, ''); }
