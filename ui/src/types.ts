export interface Capability {
  id: string;
  label: string;
  description: string;
  route: string;
  weight: number;
}

export interface ThemeSummary {
  id: string;
  label: string;
  default: boolean;
}

export interface BootstrapData {
  apiVersion: string;
  application: {
    name: string;
    canvasPath: string;
  };
  activeTheme: string;
  themes: ThemeSummary[];
  capabilities: Capability[];
  permissions: {
    publish: boolean;
    administerStyleGuideDefinitions: boolean;
  };
  reviewWorkflow: {
    adapter: string;
    supportsStaging: boolean;
    description: string;
  };
  csrfToken: string;
}
