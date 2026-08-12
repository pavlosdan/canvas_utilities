import { useEffect, useState } from 'react';
import { Badge, Box, Button, Callout, Card, Dialog, Flex, Heading, Select, Text, TextField } from '@radix-ui/themes';
import { ExclamationTriangleIcon, GlobeIcon, PlusIcon, UploadIcon } from '@radix-ui/react-icons';

import { createRemoteFont, deleteFont, getFonts, uploadFont } from '../font-api';
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
        {fonts.map((font) => <Card key={font.id} className="font-card"><Flex justify="between"><Heading size="5">{font.label}</Heading><Flex gap="2" align="center"><Badge color={font.provider === 'local_file' ? 'green' : 'blue'}>{font.provider === 'local_file' ? 'Self-hosted' : 'Remote CSS'}</Badge><Button size="1" color="red" variant="ghost" onClick={async () => { if (window.confirm(`Delete ${font.label}?`)) { try { await deleteFont(theme, font.id, csrfToken); await load(); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Delete failed.'); } } }}>Delete</Button></Flex></Flex><Text as="p" mt="5" size="7" style={{ fontFamily: `'${font.family}', ${font.fallbacks}` }}>Aa</Text><Text as="p" mt="2" color="gray">{font.family}, {font.fallbacks}</Text></Card>)}
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

interface DialogProps { theme: string; csrfToken: string; onCreated: () => Promise<void> | void }
function machineName(value: string): string { return value.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').replace(/^[^a-z]+/, ''); }
