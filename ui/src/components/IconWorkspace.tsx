import { useEffect, useState } from 'react';
import { Badge, Box, Button, Callout, Card, Dialog, Flex, Heading, Select, Text, TextField } from '@radix-ui/themes';
import { ExclamationTriangleIcon, PlusIcon } from '@radix-ui/react-icons';

import { deleteIconLibrary, getIconLibraries, importIconLibrary } from '../icon-api';
import type { IconLibrary } from '../icon-api';

export default function IconWorkspace({ theme, csrfToken }: { theme: string; csrfToken: string }) {
  const [libraries, setLibraries] = useState<IconLibrary[]>([]);
  const [error, setError] = useState<string | null>(null);
  const load = async (signal?: AbortSignal) => setLibraries(await getIconLibraries(theme, signal));
  useEffect(() => {
    const controller = new AbortController();
    load(controller.signal).catch((reason: unknown) => {
      if (reason instanceof Error && reason.name !== 'AbortError') setError(reason.message);
    });
    return () => controller.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [theme]);

  return <section aria-labelledby="icons-heading">
    <Flex justify="between" align="start" gap="4" wrap="wrap">
      <Box><Heading id="icons-heading" size="7">Icons</Heading><Text as="p" color="gray" mt="2">Import sanitized SVG assets into reusable, named libraries.</Text></Box>
      <ImportDialog theme={theme} csrfToken={csrfToken} onCreated={() => load()} />
    </Flex>
    {error && <Callout.Root color="red" role="alert" mt="4"><Callout.Icon><ExclamationTriangleIcon /></Callout.Icon><Callout.Text>{error}</Callout.Text></Callout.Root>}
    <div className="icon-library-list">
      {libraries.map((library) => <Card key={library.id} className="icon-library-card">
        <Flex justify="between" align="center" gap="3"><Box><Heading size="5">{library.label}</Heading><Text size="2" color="gray">Prefix: {library.prefix}</Text></Box><Flex gap="2" align="center"><Badge>{library.icons.length} icons</Badge><Button size="1" variant="ghost" color="red" onClick={async () => { if (window.confirm(`Delete ${library.label}?`)) { try { await deleteIconLibrary(theme, library.id, csrfToken); await load(); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Delete failed.'); } } }}>Delete</Button></Flex></Flex>
        <div className="icon-grid">{library.icons.map((icon) => <figure key={icon.id} className="icon-item"><img src={icon.url} alt="" /><figcaption>{icon.label}</figcaption></figure>)}</div>
        {(library.license || library.source) && <Text as="p" size="1" color="gray" mt="3">{library.license}{library.license && library.source ? ' · ' : ''}{library.source}</Text>}
      </Card>)}
      {libraries.length === 0 && <Card className="empty-card"><Heading size="4">No icon libraries yet</Heading><Text as="p" color="gray" mt="2">Import individual SVGs, an SVG-only ZIP archive, or an SVG sprite.</Text></Card>}
    </div>
  </section>;
}

function ImportDialog({ theme, csrfToken, onCreated }: { theme: string; csrfToken: string; onCreated: () => Promise<void> | void }) {
  const [open, setOpen] = useState(false);
  const [label, setLabel] = useState('');
  const [provider, setProvider] = useState('individual_svg');
  const [files, setFiles] = useState<File[]>([]);
  const [license, setLicense] = useState('');
  const [source, setSource] = useState('');
  const [error, setError] = useState<string | null>(null);
  const accept = provider === 'svg_zip' ? '.zip' : '.svg';
  const submit = async () => {
    if (!label.trim() || files.length === 0) return setError('Enter a library name and choose a source file.');
    const data = new FormData();
    data.set('label', label); data.set('id', machineName(label)); data.set('prefix', machineName(label)); data.set('provider', provider); data.set('license', license); data.set('source', source);
    files.forEach((file) => data.append('source[]', file));
    try { await importIconLibrary(theme, data, csrfToken); await onCreated(); setOpen(false); setError(null); setFiles([]); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Import failed.'); }
  };
  return <Dialog.Root open={open} onOpenChange={setOpen}><Dialog.Trigger><Button><PlusIcon />Import library</Button></Dialog.Trigger><Dialog.Content maxWidth="620px"><Dialog.Title>Import icon library</Dialog.Title><Dialog.Description>SVG markup is sanitized and external references are removed before it is stored.</Dialog.Description><Flex direction="column" gap="4" mt="5">
    <Box><Text as="label" htmlFor="icon-library-label">Library name</Text><TextField.Root id="icon-library-label" value={label} onChange={(event) => setLabel(event.target.value)} /></Box>
    <Box><Text as="label" htmlFor="icon-provider">Source format</Text><Select.Root value={provider} onValueChange={(value) => { setProvider(value); setFiles([]); }}><Select.Trigger id="icon-provider" /><Select.Content><Select.Item value="individual_svg">Individual SVGs</Select.Item><Select.Item value="svg_zip">SVG ZIP archive</Select.Item><Select.Item value="svg_sprite">SVG sprite</Select.Item></Select.Content></Select.Root></Box>
    <Box><Text as="label" htmlFor="icon-files">Files</Text><input key={provider} className="file-input" id="icon-files" type="file" accept={accept} multiple={provider === 'individual_svg'} onChange={(event) => setFiles(Array.from(event.target.files ?? []))} /></Box>
    <Flex gap="3"><Box style={{ flex: 1 }}><Text as="label" htmlFor="icon-license">License</Text><TextField.Root id="icon-license" placeholder="e.g. MIT" value={license} onChange={(event) => setLicense(event.target.value)} /></Box><Box style={{ flex: 1 }}><Text as="label" htmlFor="icon-source">Attribution/source</Text><TextField.Root id="icon-source" value={source} onChange={(event) => setSource(event.target.value)} /></Box></Flex>
    {error && <Callout.Root color="red" role="alert"><Callout.Text>{error}</Callout.Text></Callout.Root>}
    <Flex justify="end" gap="3"><Dialog.Close><Button variant="soft" color="gray">Cancel</Button></Dialog.Close><Button onClick={submit}>Import</Button></Flex>
  </Flex></Dialog.Content></Dialog.Root>;
}

function machineName(value: string): string { return value.toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^[-_]+|[-_]+$/g, ''); }
