import { useEffect, useState } from 'react';
import { Box, Button, Callout, Flex, Heading, Switch, Text } from '@radix-ui/themes';
import { CheckCircledIcon, ExclamationTriangleIcon } from '@radix-ui/react-icons';

import { getCustomCss, saveCustomCss } from '../custom-css-api';

export default function CustomCssWorkspace({ theme, csrfToken }: { theme: string; csrfToken: string }) {
  const [css, setCss] = useState('');
  const [status, setStatus] = useState(true);
  const [savedCss, setSavedCss] = useState('');
  const [savedStatus, setSavedStatus] = useState(true);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    const controller = new AbortController();
    getCustomCss(theme, controller.signal).then((data) => { setCss(data.css); setSavedCss(data.css); setStatus(data.status); setSavedStatus(data.status); setError(null); }).catch((reason: unknown) => {
      if (reason instanceof Error && reason.name !== 'AbortError') setError(reason.message);
    });
    return () => controller.abort();
  }, [theme]);
  const dirty = css !== savedCss || status !== savedStatus;
  const save = async () => {
    try { const data = await saveCustomCss(theme, css, status, csrfToken); setCss(data.css); setSavedCss(data.css); setStatus(data.status); setSavedStatus(data.status); setMessage('Custom CSS published.'); setError(null); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Unable to save custom CSS.'); setMessage(null); }
  };
  return <section aria-labelledby="custom-css-heading">
    <Flex justify="between" align="start" gap="4" wrap="wrap"><Box><Heading id="custom-css-heading" size="7">Custom CSS</Heading><Text as="p" color="gray" mt="2">Add a final theme override for cases that are not represented in the Style Guide.</Text></Box><Button disabled={!dirty} onClick={save}>Publish CSS</Button></Flex>
    <Callout.Root color="amber" mt="5"><Callout.Icon><ExclamationTriangleIcon /></Callout.Icon><Callout.Text>CSS can affect every page. This permission should be limited to trusted site builders. <code>@import</code> and script URLs are blocked.</Callout.Text></Callout.Root>
    {error && <Callout.Root color="red" role="alert" mt="4"><Callout.Text>{error}</Callout.Text></Callout.Root>}
    {message && <Callout.Root color="green" role="status" mt="4"><Callout.Icon><CheckCircledIcon /></Callout.Icon><Callout.Text>{message}</Callout.Text></Callout.Root>}
    <Box mt="5"><Flex justify="between" align="center" mb="2"><Text as="label" htmlFor="custom-css-editor" weight="medium">Theme override</Text><Text as="label" size="2"><Flex align="center" gap="2"><Switch checked={status} onCheckedChange={setStatus} />Enabled</Flex></Text></Flex><textarea id="custom-css-editor" className="code-editor" spellCheck={false} value={css} onChange={(event) => { setCss(event.target.value); setMessage(null); }} placeholder={':root {\n  --brand-accent: #3057d5;\n}'} /><Flex justify="between" mt="2"><Text size="1" color="gray">Delivered after the theme stylesheets.</Text><Text size="1" color="gray">{new Blob([css]).size.toLocaleString()} / 102,400 bytes</Text></Flex></Box>
  </section>;
}
