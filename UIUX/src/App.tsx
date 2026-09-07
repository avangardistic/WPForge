import { useMemo, useState } from 'react';
import { GithubIcon } from 'lucide-react';
import { ActivityStream } from './components/ActivityStream';
import { ConnectionModal } from './components/ConnectionModal';
import {
  MediaPanel,
  PanelShell,
  PluginsPanel,
  PostsPanel,
} from './components/LivePanels';
import { MobileStatusStrip } from './components/MobileStatusStrip';
import { StatusRail } from './components/StatusRail';
import { TopBar } from './components/TopBar';
import { capabilities } from './data/capabilities';
import { healthSignals, site } from './data/site';
import type { HealthSignal } from './data/site';
import { useActivityStream } from './hooks/useActivityStream';
import { useWpForge } from './hooks/useWpForge';

interface AppProps {
  /** Whether the agent activity stream receives new calls on load. */
  streamLive?: boolean;
}

/** WPForge endpoints surfaced for areas that don't have a dedicated panel yet. */
const AREA_ENDPOINTS: Record<string, string[]> = {
  elementor: [
    '/wpforge/v1/elementor/status',
    '/wpforge/v1/elementor/documents',
    '/wpforge/v1/elementor/templates',
  ],
  filesystem: [
    '/wpforge/v1/files/list',
    '/wpforge/v1/files/read',
  ],
  database: [
    '/wpforge/v1/database/status',
    '/wpforge/v1/database/tables',
    '/wpforge/v1/database/query',
  ],
  backups: ['/wpforge/v1/backup'],
};

function hostOf(url: string): string {
  try {
    return new URL(url).hostname;
  } catch {
    return url.replace(/^https?:\/\//, '');
  }
}

export function App({ streamLive = true }: AppProps) {
  const [live, setLive] = useState(streamLive);
  const [activeCapability, setActiveCapability] = useState('audit');
  const [showConnection, setShowConnection] = useState(false);

  const forge = useWpForge(!live);
  const connected = forge.phase === 'connected';
  const connecting = forge.phase === 'connecting';

  // Simulated demo stream is only used while disconnected.
  const demoEntries = useActivityStream(live && !connected);
  const entries = connected ? forge.activities : demoEntries;

  const grants = useMemo(() => {
    if (!forge.user) return null;
    const caps = forge.user.capabilities;
    const out: Record<string, boolean> = {};
    for (const capability of capabilities) {
      out[capability.id] = Boolean(caps[capability.cap]);
    }
    return out;
  }, [forge.user]);

  const metaSegments = useMemo(() => {
    if (forge.site) {
      return [
        hostOf(forge.site.site_url),
        `WP ${forge.site.wordpress_version}`,
        `PHP ${forge.site.php_version}`,
        `plugin v${forge.site.version}`,
      ];
    }
    return [site.host, site.wordpress, site.php, site.plugin];
  }, [forge.site]);

  const signals: HealthSignal[] = useMemo(() => {
    if (!connected || !forge.site) return healthSignals;

    const healthTone: HealthSignal['tone'] =
      forge.health === null
        ? 'forge'
        : forge.health.status === 'healthy'
          ? 'ok'
          : 'bad';
    const healthValue =
      forge.health === null ? 'Checking…' : forge.health.status === 'healthy' ? 'Healthy' : forge.health.status;

    const db = forge.health?.checks?.database;
    const dbTone: HealthSignal['tone'] =
      !db ? 'forge' : db.status === 'ok' ? 'ok' : 'bad';

    return [
      { label: 'System Health', value: healthValue, tone: healthTone },
      {
        label: 'Database',
        value: db ? (db.status === 'ok' ? 'Connected' : db.details) : 'Checking…',
        tone: dbTone,
      },
      { label: 'Plugin Version', value: `v${forge.site.version}`, tone: 'forge' },
    ];
  }, [connected, forge.site, forge.health]);

  const apiMeta = useMemo(() => {
    if (connected && forge.connection) {
      return {
        namespace: 'wpforge/v1',
        host: forge.connection.host.replace(/^https?:\/\//, ''),
      };
    }
    return { namespace: site.apiNamespace, host: site.apiHost };
  }, [connected, forge.connection]);

  const tailHint = connected && forge.connection
    ? `tail -f ${forge.connection.host.replace(/^https?:\/\//, '')}/wp-json/wpforge/v1/logs`
    : site.tailCommand;

  const renderMain = () => {
    switch (activeCapability) {
      case 'content':
        return (
          <PostsPanel
            phase={forge.phase}
            posts={forge.posts}
            onRefresh={connected ? forge.refreshPosts : undefined}
          />
        );
      case 'media':
        return (
          <MediaPanel
            phase={forge.phase}
            media={forge.media}
            onRefresh={connected ? forge.refreshMedia : undefined}
          />
        );
      case 'extensions':
        return (
          <PluginsPanel
            phase={forge.phase}
            plugins={forge.plugins}
            onRefresh={connected ? forge.refreshPlugins : undefined}
          />
        );
      case 'audit':
        return (
          <ActivityStream
            entries={entries}
            live={live}
            connected={connected}
            connecting={connecting}
            activityCount={connected ? forge.activitiesCount : undefined}
            onToggleLive={() => setLive((value) => !value)}
            onRefresh={connected ? forge.refreshLogs : undefined}
            tailHint={tailHint}
          />
        );
      default: {
        const capability = capabilities.find((item) => item.id === activeCapability);
        const fallback = capabilities[0];
        const endpoints = AREA_ENDPOINTS[activeCapability] ?? [];
        const Icon = capability?.icon ?? fallback.icon;
        return (
          <PanelShell
            icon={Icon}
            title={capability?.label ?? fallback.label}
            subtitle="Available API endpoints">
            <div className="flex h-full flex-col gap-3 px-4 py-4">
              {connected ? null : (
                <p className="rounded-md border border-forge/25 bg-forge/[0.07] px-3 py-2 text-[11px] leading-relaxed text-forge">
                  Not connected — open <span className="font-semibold">Connect</span> in the top bar to query this site live.
                </p>
              )}
              <p className="text-[11px] text-muted">
                This area is served directly by the WPForge REST API:
              </p>
              <ul className="flex flex-col gap-1.5">
                {endpoints.map((endpoint) => (
                  <li
                    key={endpoint}
                    className="rounded-md border border-hairline bg-raised px-3 py-2 font-mono text-[11.5px] text-ink">
                    {endpoint}
                  </li>
                ))}
              </ul>
              {grants && capability ? (
                <p className="mt-auto border-t border-hairline pt-2.5 text-[10.5px] leading-relaxed text-muted">
                  Requires <span className="font-mono text-ink">{capability.cap}</span> —{' '}
                  {grants[capability.id]
                    ? 'your connected user has this capability.'
                    : 'your connected user does not have this capability.'}
                </p>
              ) : null}
            </div>
          </PanelShell>
        );
      }
    }
  };

  const connectionWords = connected
    ? { text: 'Connected', className: 'font-sans font-semibold text-ok' }
    : connecting
      ? { text: 'Connecting…', className: 'font-sans font-semibold text-forge' }
      : { text: 'Demo data', className: 'font-sans font-semibold text-muted' };

  return (
    <div className="flex h-full min-h-full w-full flex-col bg-canvas font-sans text-[13px] text-ink">
      <TopBar
        phase={forge.phase}
        userLabel={forge.user ? forge.user.user : null}
        metaSegments={metaSegments}
        onConnectionClick={() => setShowConnection(true)}
      />

      <div className="flex min-h-0 flex-1">
        <StatusRail
          activeCapability={activeCapability}
          onSelectCapability={setActiveCapability}
          signals={signals}
          grants={grants}
          userLabel={forge.user ? forge.user.user : null}
          userRoles={forge.user ? forge.user.roles : null}
          apiMeta={apiMeta}
          onFooterClick={() => setShowConnection(true)}
        />

        <main className="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden px-4 py-4 sm:px-6">
          <div>
            <h1 className="text-xl font-bold tracking-[-0.2px]">Site Control</h1>
            <p className="mt-1 font-mono text-xs text-muted">
              {metaSegments.map((segment, index) => (
                <span key={`${segment}-${index}`}>
                  {index > 0 ? <span className="px-1.5 text-hairline">·</span> : null}
                  {segment}
                </span>
              ))}
              <span className="px-1.5 text-hairline">·</span>
              <span className={connectionWords.className}>{connectionWords.text}</span>
            </p>
          </div>

          <MobileStatusStrip signals={signals} />

          {renderMain()}

          <footer className="flex flex-shrink-0 items-center justify-center">
            <a
              href="https://github.com/avangardistic/WPForge"
              target="_blank"
              rel="noreferrer noopener"
              className="group inline-flex items-center gap-1.5 rounded-sm text-[10px] text-muted/70 transition-colors duration-150 ease-out hover:text-ink focus:outline-none focus-visible:ring-1 focus-visible:ring-forge">
              <GithubIcon
                className="h-3 w-3 text-muted/50 transition-colors duration-150 group-hover:text-ink"
                strokeWidth={2}
                aria-hidden="true"
              />
              WPForge by Hossein Parasteh
            </a>
          </footer>
        </main>
      </div>

      <ConnectionModal
        open={showConnection}
        phase={forge.phase}
        error={forge.error}
        connection={forge.connection}
        user={forge.user}
        onConnect={forge.connect}
        onDisconnect={forge.disconnect}
        onClose={() => setShowConnection(false)}
      />
    </div>
  );
}
