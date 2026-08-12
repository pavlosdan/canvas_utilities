import { useEffect, useMemo, useState } from 'react';
import {
  Box,
  Button,
  Callout,
  Card,
  Flex,
  Heading,
  Select,
  Spinner,
  Text,
} from '@radix-ui/themes';
import {
  CheckCircledIcon,
  ExclamationTriangleIcon,
  GearIcon,
} from '@radix-ui/react-icons';

import { getBootstrap } from './api';
import StyleGuideWorkspace from './components/StyleGuideWorkspace';
import PaletteWorkspace from './components/PaletteWorkspace';
import FontWorkspace from './components/FontWorkspace';
import IconWorkspace from './components/IconWorkspace';
import CustomCssWorkspace from './components/CustomCssWorkspace';
import { getRouteFromHash, navigate } from './navigation';
import type { BootstrapData } from './types';

import './app.css';

export default function App() {
  const [bootstrap, setBootstrap] = useState<BootstrapData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [route, setRoute] = useState(getRouteFromHash());
  const [selectedTheme, setSelectedTheme] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    getBootstrap(controller.signal)
      .then((data) => {
        setBootstrap(data);
        setSelectedTheme(data.activeTheme);
      })
      .catch((reason: unknown) => {
        if (reason instanceof Error && reason.name !== 'AbortError') {
          setError(reason.message);
        }
      });
    return () => controller.abort();
  }, []);

  useEffect(() => {
    const onHashChange = () => setRoute(getRouteFromHash());
    window.addEventListener('hashchange', onHashChange);
    return () => window.removeEventListener('hashchange', onHashChange);
  }, []);

  const activeCapability = useMemo(
    () => bootstrap?.capabilities.find((item) => item.route === route),
    [bootstrap, route],
  );

  if (error) {
    return (
      <main className="centered-state">
        <Callout.Root color="red" role="alert">
          <Callout.Icon><ExclamationTriangleIcon /></Callout.Icon>
          <Callout.Text>{error}</Callout.Text>
        </Callout.Root>
      </main>
    );
  }

  if (!bootstrap) {
    return (
      <main className="centered-state" aria-label="Loading design system">
        <Spinner size="3" />
        <Text color="gray">Loading design system…</Text>
      </main>
    );
  }

  return (
    <div className="app-shell">
      <header className="app-header">
        <Flex align="center" gap="3">
          <span className="brand-mark" aria-hidden="true"><GearIcon /></span>
          <Box>
            <Heading size="5">{bootstrap.application.name}</Heading>
            <Text size="2" color="gray">Manage visual foundations without editing theme code.</Text>
          </Box>
        </Flex>
        <Flex align="center" gap="3">
          <Text as="label" htmlFor="theme-select" size="2" weight="medium">Theme</Text>
          <Select.Root value={selectedTheme} onValueChange={setSelectedTheme}>
            <Select.Trigger id="theme-select" aria-label="Theme" />
            <Select.Content>
              {bootstrap.themes.map((theme) => (
                <Select.Item key={theme.id} value={theme.id}>
                  {theme.label}{theme.default ? ' (default)' : ''}
                </Select.Item>
              ))}
            </Select.Content>
          </Select.Root>
        </Flex>
      </header>

      <div className="workspace">
        <nav className="section-nav" aria-label="Design system sections">
          <Button
            className="nav-button"
            variant={route === '' ? 'soft' : 'ghost'}
            color="gray"
            onClick={() => navigate('')}
          >
            Overview
          </Button>
          {bootstrap.capabilities.map((capability) => (
            <Button
              key={capability.id}
              className="nav-button"
              variant={route === capability.route ? 'soft' : 'ghost'}
              color="gray"
              onClick={() => navigate(capability.route)}
            >
              {capability.label}
            </Button>
          ))}
        </nav>

        <main className="main-content">
          {route === '' ? (
            <Overview bootstrap={bootstrap} />
          ) : activeCapability?.id === 'style_guide' ? (
            <StyleGuideWorkspace
              theme={selectedTheme}
              csrfToken={bootstrap.csrfToken}
              canPublish={bootstrap.permissions.publish}
              canAdministerDefinitions={bootstrap.permissions.administerStyleGuideDefinitions}
            />
          ) : activeCapability?.id === 'palette' ? (
            <PaletteWorkspace theme={selectedTheme} csrfToken={bootstrap.csrfToken} />
          ) : activeCapability?.id === 'fonts' ? (
            <FontWorkspace theme={selectedTheme} csrfToken={bootstrap.csrfToken} />
          ) : activeCapability?.id === 'icons' ? (
            <IconWorkspace theme={selectedTheme} csrfToken={bootstrap.csrfToken} />
          ) : activeCapability?.id === 'custom_css' ? (
            <CustomCssWorkspace theme={selectedTheme} csrfToken={bootstrap.csrfToken} />
          ) : activeCapability ? (
            <section aria-labelledby="section-heading">
              <Heading id="section-heading" size="7">{activeCapability.label}</Heading>
              <Text as="p" color="gray" mt="2">{activeCapability.description}</Text>
              <Callout.Root color="blue" mt="5">
                <Callout.Icon><CheckCircledIcon /></Callout.Icon>
                <Callout.Text>This capability is enabled. Its workspace will load here.</Callout.Text>
              </Callout.Root>
            </section>
          ) : (
            <Callout.Root color="amber" role="alert">
              <Callout.Icon><ExclamationTriangleIcon /></Callout.Icon>
              <Callout.Text>The requested design-system section is not enabled or you do not have access.</Callout.Text>
            </Callout.Root>
          )}
        </main>
      </div>
    </div>
  );
}

function Overview({ bootstrap }: { bootstrap: BootstrapData }) {
  return (
    <section aria-labelledby="overview-heading">
      <Heading id="overview-heading" size="7">Design foundations</Heading>
      <Text as="p" size="3" color="gray" mt="2" className="measure">
        Configure the shared styles and assets that give this site a consistent visual language.
      </Text>
      {!bootstrap.reviewWorkflow.supportsStaging && (
        <Callout.Root color="blue" mt="5">
          <Callout.Icon><CheckCircledIcon /></Callout.Icon>
          <Callout.Text>{bootstrap.reviewWorkflow.description}</Callout.Text>
        </Callout.Root>
      )}
      <div className="card-grid">
        {bootstrap.capabilities.length > 0 ? bootstrap.capabilities.map((capability) => (
          <Card key={capability.id} asChild>
            <button className="capability-card" onClick={() => navigate(capability.route)}>
              <Heading size="4">{capability.label}</Heading>
              <Text color="gray" size="2">{capability.description}</Text>
              <Text size="2" weight="bold" color="blue">Open section →</Text>
            </button>
          </Card>
        )) : (
          <Card className="empty-card">
            <Heading size="4">Ready for features</Heading>
            <Text as="p" color="gray" mt="2">
              Enable a Canvas Utilities feature module to add style guides, palettes, fonts, icons, or custom CSS.
            </Text>
          </Card>
        )}
      </div>
    </section>
  );
}
