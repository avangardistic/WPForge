export interface HealthSignal {
  label: string;
  value: string;
  tone: 'ok' | 'forge' | 'bad';
}

export const site = {
  name: 'WPForge',
  host: 'forge-demo.site',
  wordpress: 'WP 6.7.1',
  php: 'PHP 8.3',
  plugin: 'plugin v1.0.0',
  connection: 'Connected',
  apiNamespace: 'wpforge/v1',
  apiHost: 'api.forge-demo.site',
  tailCommand: 'tail -f /wpforge/v1/audit'
};

export const healthSignals: HealthSignal[] = [
  { label: 'Backup Status', value: 'Last backup 4h ago', tone: 'ok' },
  { label: 'Connection', value: 'Connected', tone: 'ok' },
  { label: 'Plugin Version', value: 'v1.0.0', tone: 'forge' },
];
