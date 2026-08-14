import { useEffect, useState } from 'react';
import { Badge, Box, Button, Callout, Card, Dialog, Flex, Heading, IconButton, Select, Text, TextField } from '@radix-ui/themes';
import { ExclamationTriangleIcon, Pencil1Icon, PlusIcon, TrashIcon } from '@radix-ui/react-icons';

import ConfirmButton from './ConfirmButton';
import { addIconsToLibrary, deleteIcon, deleteIconLibrary, getIconLibraries, getIconUsage, importIconLibrary, updateIconLibrary } from '../icon-api';
import type { IconLibrary, IconUsageRecord } from '../icon-api';

/**
 * Renders where something is used, or says nothing found.
 *
 * The scan cannot see an icon URL hard-coded inside a component's own JS or
 * CSS, so "no usage found" is phrased as a negative result rather than a
 * guarantee that deleting is safe.
 */
function UsageDetails({ records, subject }: { records: IconUsageRecord[]; subject: string }) {
  if (records.length === 0) {
    return <Callout.Root color="gray" mt="3">
      <Callout.Text size="2">No usage found. A URL written directly into component code cannot be detected, so check there too.</Callout.Text>
    </Callout.Root>;
  }
  const shown = records.slice(0, 8);
  return <Callout.Root color="amber" mt="3">
    <Callout.Icon><ExclamationTriangleIcon /></Callout.Icon>
    <Callout.Text size="2">
      <strong>{subject} still in use in {records.length} place{records.length === 1 ? '' : 's'}.</strong> Deleting will leave {records.length === 1 ? 'it' : 'them'} without an icon.
      <ul className="usage-list">
        {shown.map((record) => <li key={record.id}>{record.label} <Text color="gray">({record.type})</Text></li>)}
        {records.length > shown.length && <li><Text color="gray">and {records.length - shown.length} more…</Text></li>}
      </ul>
    </Callout.Text>
  </Callout.Root>;
}

export default function IconWorkspace({ theme, csrfToken }: { theme: string; csrfToken: string }) {
  const [libraries, setLibraries] = useState<IconLibrary[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const load = async (signal?: AbortSignal) => setLibraries(await getIconLibraries(theme, signal));

  // Wraps every mutation so a failed request always reaches the user.
  const run = async (action: () => Promise<void>, fallback: string) => {
    setError(null);
    try {
      await action();
      await load();
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : fallback);
    }
  };

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
    {notice && <Callout.Root color="green" role="status" mt="4"><Callout.Text>{notice}</Callout.Text></Callout.Root>}
    <div className="icon-library-list">
      {libraries.map((library) => <Card key={library.id} className="icon-library-card">
        <Flex justify="between" align="center" gap="3" wrap="wrap">
          <Box>
            <Heading size="5">{library.label}</Heading>
            <Text size="2" color="gray">Prefix: {library.prefix}</Text>
          </Box>
          <Flex gap="2" align="center">
            <Badge>{library.icons.length} icons</Badge>
            <AddIconsDialog
              theme={theme}
              csrfToken={csrfToken}
              library={library}
              onAdded={async (summary) => { setNotice(summary); await load(); }}
              onError={setError}
            />
            <EditLibraryDialog
              theme={theme}
              csrfToken={csrfToken}
              library={library}
              onSaved={() => load()}
              onError={setError}
            />
            <ConfirmButton
              size="1"
              variant="ghost"
              color="red"
              title={`Delete ${library.label}?`}
              description="The library and its sanitized SVG files are removed."
              loadDetails={async () => {
                const report = await getIconUsage(theme, library.id);
                const records = Object.values(report.icons).flat();
                return <UsageDetails records={records} subject="These icons are" />;
              }}
              onConfirm={() => run(() => deleteIconLibrary(theme, library.id, csrfToken), 'Delete failed.')}
            >Delete</ConfirmButton>
          </Flex>
        </Flex>
        <div className="icon-grid">{library.icons.map((icon) => <figure key={icon.id} className="icon-item">
          <img src={icon.url} alt="" />
          <figcaption>{icon.label}</figcaption>
          <ConfirmButton
            size="1"
            variant="ghost"
            color="red"
            className="icon-item__remove"
            title={`Remove ${icon.label}?`}
            description={`The icon is removed from ${library.label} and its file is deleted.`}
            loadDetails={async () => {
              const report = await getIconUsage(theme, library.id);
              return <UsageDetails records={report.icons[icon.id] ?? []} subject="This icon is" />;
            }}
            confirmLabel="Remove"
            onConfirm={() => run(() => deleteIcon(theme, library.id, icon.id, csrfToken), 'Unable to remove the icon.')}
          ><TrashIcon /></ConfirmButton>
        </figure>)}</div>
        {library.icons.length === 0 && <Text as="p" size="2" color="gray" mt="3">This library has no icons yet. Use “Add icons” to upload some.</Text>}
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
    try { await importIconLibrary(theme, data, csrfToken); await onCreated(); setOpen(false); setError(null); setFiles([]); setLabel(''); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Import failed.'); }
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

/**
 * Uploads more icons into a library that already exists.
 */
function AddIconsDialog({ theme, csrfToken, library, onAdded, onError }: {
  theme: string;
  csrfToken: string;
  library: IconLibrary;
  onAdded: (summary: string) => Promise<void> | void;
  onError: (message: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const [provider, setProvider] = useState(library.provider);
  const [files, setFiles] = useState<File[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const accept = provider === 'svg_zip' ? '.zip' : '.svg';
  const inputId = `add-icons-${library.id}`;

  const submit = async () => {
    if (files.length === 0) return setError('Choose at least one file.');
    const data = new FormData();
    data.set('provider', provider);
    files.forEach((file) => data.append('source[]', file));
    setBusy(true);
    try {
      const report = await addIconsToLibrary(theme, library.id, data, csrfToken);
      const parts = [
        report.added.length ? `${report.added.length} added` : '',
        report.replaced.length ? `${report.replaced.length} replaced` : '',
        report.unchanged.length ? `${report.unchanged.length} already up to date` : '',
      ].filter(Boolean);
      await onAdded(`${library.label}: ${parts.join(', ') || 'no changes'}.`);
      setOpen(false);
      setError(null);
      setFiles([]);
    } catch (reason) {
      const message = reason instanceof Error ? reason.message : 'Unable to add icons.';
      setError(message);
      onError(message);
    } finally {
      setBusy(false);
    }
  };

  return <Dialog.Root open={open} onOpenChange={(next) => { setOpen(next); if (!next) { setFiles([]); setError(null); } }}>
    <Dialog.Trigger><Button size="1" variant="soft"><PlusIcon />Add icons</Button></Dialog.Trigger>
    <Dialog.Content maxWidth="620px">
      <Dialog.Title>Add icons to {library.label}</Dialog.Title>
      <Dialog.Description>Upload one or more SVGs at a time. Re-uploading an unchanged file does nothing; an icon whose name matches but whose content differs replaces the stored one.</Dialog.Description>
      <Flex direction="column" gap="4" mt="5">
        <Box>
          <Text as="label" htmlFor={`${inputId}-provider`}>Source format</Text>
          <Select.Root value={provider} onValueChange={(value) => { setProvider(value as IconLibrary['provider']); setFiles([]); }}>
            <Select.Trigger id={`${inputId}-provider`} />
            <Select.Content>
              <Select.Item value="individual_svg">Individual SVGs</Select.Item>
              <Select.Item value="svg_zip">SVG ZIP archive</Select.Item>
              <Select.Item value="svg_sprite">SVG sprite</Select.Item>
            </Select.Content>
          </Select.Root>
        </Box>
        <Box>
          <Text as="label" htmlFor={inputId}>Files</Text>
          <input key={provider} className="file-input" id={inputId} type="file" accept={accept} multiple={provider === 'individual_svg'} onChange={(event) => setFiles(Array.from(event.target.files ?? []))} />
        </Box>
        {error && <Callout.Root color="red" role="alert"><Callout.Text>{error}</Callout.Text></Callout.Root>}
        <Flex justify="end" gap="3">
          <Dialog.Close><Button variant="soft" color="gray">Cancel</Button></Dialog.Close>
          <Button onClick={submit} loading={busy}>Add icons</Button>
        </Flex>
      </Flex>
    </Dialog.Content>
  </Dialog.Root>;
}

/**
 * Edits the metadata of a library that already exists.
 */
function EditLibraryDialog({ theme, csrfToken, library, onSaved, onError }: {
  theme: string;
  csrfToken: string;
  library: IconLibrary;
  onSaved: () => Promise<void> | void;
  onError: (message: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const [label, setLabel] = useState(library.label);
  const [license, setLicense] = useState(library.license);
  const [source, setSource] = useState(library.source);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (!label.trim()) return setError('Enter a library name.');
    setBusy(true);
    try {
      await updateIconLibrary(theme, library.id, { label, license, source }, csrfToken);
      await onSaved();
      setOpen(false);
      setError(null);
    } catch (reason) {
      const message = reason instanceof Error ? reason.message : 'Unable to save the library.';
      setError(message);
      onError(message);
    } finally {
      setBusy(false);
    }
  };

  return <Dialog.Root open={open} onOpenChange={(next) => {
    setOpen(next);
    if (next) { setLabel(library.label); setLicense(library.license); setSource(library.source); setError(null); }
  }}>
    <Dialog.Trigger><IconButton size="1" variant="ghost" aria-label={`Edit ${library.label}`}><Pencil1Icon /></IconButton></Dialog.Trigger>
    <Dialog.Content maxWidth="560px">
      <Dialog.Title>Edit {library.label}</Dialog.Title>
      <Dialog.Description>The library ID and prefix are fixed once icons are stored against them.</Dialog.Description>
      <Flex direction="column" gap="4" mt="5">
        <Box><Text as="label" htmlFor={`edit-label-${library.id}`}>Library name</Text><TextField.Root id={`edit-label-${library.id}`} value={label} onChange={(event) => setLabel(event.target.value)} /></Box>
        <Flex gap="3">
          <Box style={{ flex: 1 }}><Text as="label" htmlFor={`edit-license-${library.id}`}>License</Text><TextField.Root id={`edit-license-${library.id}`} placeholder="e.g. MIT" value={license} onChange={(event) => setLicense(event.target.value)} /></Box>
          <Box style={{ flex: 1 }}><Text as="label" htmlFor={`edit-source-${library.id}`}>Attribution/source</Text><TextField.Root id={`edit-source-${library.id}`} value={source} onChange={(event) => setSource(event.target.value)} /></Box>
        </Flex>
        {error && <Callout.Root color="red" role="alert"><Callout.Text>{error}</Callout.Text></Callout.Root>}
        <Flex justify="end" gap="3">
          <Dialog.Close><Button variant="soft" color="gray">Cancel</Button></Dialog.Close>
          <Button onClick={submit} loading={busy}>Save</Button>
        </Flex>
      </Flex>
    </Dialog.Content>
  </Dialog.Root>;
}

function machineName(value: string): string { return value.toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^[-_]+|[-_]+$/g, ''); }
