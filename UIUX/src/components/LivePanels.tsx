import type { ReactNode } from 'react';
import {
  ExternalLinkIcon,
  FileTextIcon,
  FolderOpenIcon,
  ImageIcon,
  Loader2Icon,
  PlugIcon,
  RotateCwIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type {
  WpForgeMediaItem,
  WpForgePlugin,
  WpForgePost,
} from '../lib/api';
import { relativeTime } from '../lib/format';
import type { ConnectionPhase } from '../hooks/useWpForge';

interface PanelShellProps {
  icon: LucideIcon;
  title: string;
  subtitle?: string;
  count?: number;
  connecting?: boolean;
  onRefresh?: () => void;
  children: ReactNode;
}

export function PanelShell({
  icon: Icon,
  title,
  subtitle,
  count,
  connecting,
  onRefresh,
  children,
}: PanelShellProps) {
  return (
    <section className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-hairline bg-console">
      <div className="flex flex-shrink-0 items-center gap-2.5 border-b border-hairline bg-raised px-4 py-2.5">
        <span
          className="flex h-[18px] w-[18px] items-center justify-center rounded border border-forge/40 bg-forge/10 text-forge"
          aria-hidden="true">
          <Icon className="h-2.5 w-2.5" strokeWidth={2.25} />
        </span>
        <h2 className="text-[12.5px] font-semibold text-ink">{title}</h2>
        {subtitle ? (
          <span className="hidden text-[11px] text-muted sm:block">· {subtitle}</span>
        ) : null}
        {typeof count === 'number' && count >= 0 ? (
          <span className="rounded border border-hairline bg-console px-1.5 py-0.5 font-mono text-[10px] text-muted">
            {count}
          </span>
        ) : null}

        {onRefresh ? (
          <button
            type="button"
            onClick={onRefresh}
            disabled={connecting}
            aria-label="Refresh"
            className="ml-auto flex h-6 w-6 items-center justify-center rounded-md border border-hairline text-muted transition-colors duration-150 ease-out hover:border-white/20 hover:text-ink focus:outline-none focus-visible:ring-1 focus-visible:ring-forge disabled:opacity-50">
            {connecting ? (
              <Loader2Icon className="h-3 w-3 animate-spin" />
            ) : (
              <RotateCwIcon className="h-3 w-3" strokeWidth={2.25} />
            )}
          </button>
        ) : null}
      </div>

      <div className="min-h-0 flex-1 overflow-y-auto wpforge-scroll">{children}</div>
    </section>
  );
}

export function PanelEmpty({
  icon: Icon = FolderOpenIcon,
  title,
  detail,
}: {
  icon?: LucideIcon;
  title: string;
  detail?: string;
}) {
  return (
    <div className="flex h-full flex-col items-center justify-center gap-1.5 px-6 py-12 text-center">
      <Icon className="h-5 w-5 text-muted/50" strokeWidth={1.75} aria-hidden="true" />
      <p className="text-[12.5px] font-semibold text-muted">{title}</p>
      {detail ? <p className="max-w-[36ch] text-[11px] leading-relaxed text-muted/70">{detail}</p> : null}
    </div>
  );
}

function PostStatusBadge({ status }: { status: string }) {
  const failed = status === 'trash';
  const pending = status !== 'publish' && !failed;
  return (
    <span
      className={`rounded border px-1.5 py-0.5 font-mono text-[9.5px] font-semibold uppercase tracking-[0.04em] ${
        failed
          ? 'border-red-500/25 bg-red-500/10 text-red-400'
          : pending
            ? 'border-forge/25 bg-forge/10 text-forge'
            : 'border-ok/25 bg-ok/10 text-ok'
      }`}>
      {status}
    </span>
  );
}

export function PostsPanel({
  phase,
  posts,
  onRefresh,
}: {
  phase: ConnectionPhase;
  posts: WpForgePost[];
  onRefresh?: () => void;
}) {
  const connecting = phase === 'connecting';

  return (
    <PanelShell
      icon={FileTextIcon}
      title="Content"
      subtitle="Recent posts"
      count={posts.length}
      connecting={connecting}
      onRefresh={onRefresh}>
      {phase === 'disconnected' ? (
        <PanelEmpty
          title="Not connected"
          detail="Open Connect to load the latest posts from your WordPress site."
        />
      ) : posts.length === 0 ? (
        <PanelEmpty
          icon={FileTextIcon}
          title={connecting ? 'Loading posts…' : 'No posts yet'}
          detail={connecting ? undefined : 'Published or draft posts will appear here.'}
        />
      ) : (
        <ul aria-label="Recent posts">
          {posts.map((post) => (
            <li
              key={post.id}
              className="flex flex-col gap-1 border-b border-hairline px-4 py-[7px] transition-colors duration-150 hover:bg-white/[0.02] sm:flex-row sm:items-center sm:gap-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-[12.5px] font-medium text-ink">
                  {post.title || `(untitled · id ${post.id})`}
                </p>
                <p className="truncate font-mono text-[10.5px] text-muted">
                  {post.type} · by {post.author}
                </p>
              </div>
              <div className="flex items-center gap-2.5">
                <span className="shrink-0 font-mono text-[10.5px] text-muted">
                  {relativeTime(post.date)}
                </span>
                <PostStatusBadge status={post.status} />
              </div>
            </li>
          ))}
        </ul>
      )}
    </PanelShell>
  );
}

function PluginRow({ plugin }: { plugin: WpForgePlugin }) {
  return (
    <li className="flex items-center gap-3 border-b border-hairline px-4 py-[7px] transition-colors duration-150 hover:bg-white/[0.02]">
      <span
        className={`flex h-[18px] w-[18px] flex-shrink-0 items-center justify-center rounded border ${
          plugin.is_active
            ? 'border-ok/40 bg-ok/10 text-ok'
            : 'border-hairline bg-raised text-muted/60'
        }`}
        aria-hidden="true">
        <PlugIcon className="h-2.5 w-2.5" strokeWidth={2.25} />
      </span>
      <div className="min-w-0 flex-1">
        <p className="truncate text-[12px] font-medium text-ink">{plugin.name}</p>
        <p className="truncate font-mono text-[10.5px] text-muted">{plugin.slug} · v{plugin.version}</p>
      </div>
      <span
        className={`rounded border px-1.5 py-0.5 font-mono text-[9.5px] font-semibold uppercase tracking-[0.04em] ${
          plugin.is_active
            ? 'border-ok/25 bg-ok/10 text-ok'
            : 'border-hairline bg-raised text-muted'
        }`}>
        {plugin.is_active ? 'Active' : 'Inactive'}
      </span>
    </li>
  );
}

export function PluginsPanel({
  phase,
  plugins,
  onRefresh,
}: {
  phase: ConnectionPhase;
  plugins: WpForgePlugin[];
  onRefresh?: () => void;
}) {
  const connecting = phase === 'connecting';
  const active = plugins.filter((plugin) => plugin.is_active).length;

  return (
    <PanelShell
      icon={PlugIcon}
      title="Plugins & Themes"
      subtitle={`${active} of ${plugins.length} plugins active`}
      count={plugins.length}
      connecting={connecting}
      onRefresh={onRefresh}>
      {phase === 'disconnected' ? (
        <PanelEmpty
          title="Not connected"
          detail="Open Connect to inspect plugins on your WordPress site."
        />
      ) : plugins.length === 0 ? (
        <PanelEmpty
          icon={PlugIcon}
          title={connecting ? 'Loading plugins…' : 'No plugins found'}
        />
      ) : (
        <ul aria-label="Installed plugins">
          {plugins.map((plugin) => (
            <PluginRow key={plugin.slug} plugin={plugin} />
          ))}
        </ul>
      )}
    </PanelShell>
  );
}

export function MediaPanel({
  phase,
  media,
  onRefresh,
}: {
  phase: ConnectionPhase;
  media: WpForgeMediaItem[];
  onRefresh?: () => void;
}) {
  const connecting = phase === 'connecting';

  return (
    <PanelShell
      icon={ImageIcon}
      title="Media Library"
      subtitle="Latest uploads"
      count={media.length}
      connecting={connecting}
      onRefresh={onRefresh}>
      {phase === 'disconnected' ? (
        <PanelEmpty
          title="Not connected"
          detail="Open Connect to browse media on your WordPress site."
        />
      ) : media.length === 0 ? (
        <PanelEmpty
          icon={ImageIcon}
          title={connecting ? 'Loading media…' : 'No media items'}
        />
      ) : (
        <ul aria-label="Recent media">
          {media.map((item) => (
            <li key={item.id} className="border-b border-hairline last:border-b-0">
              <a
                href={item.url}
                target="_blank"
                rel="noreferrer noopener"
                className="flex items-center gap-3 px-4 py-[7px] transition-colors duration-150 hover:bg-white/[0.02]">
                <span
                  className="flex h-8 w-8 flex-shrink-0 items-center justify-center overflow-hidden rounded border border-hairline bg-raised text-muted"
                  aria-hidden="true">
                  <ImageIcon className="h-3.5 w-3.5" strokeWidth={1.75} />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-[12px] font-medium text-ink">
                    {item.title || item.filename}
                  </p>
                  <p className="truncate font-mono text-[10.5px] text-muted">
                    {item.filename} · {item.type}
                    {typeof item.file_size === 'number' ? ` · ${(item.file_size / 1024).toFixed(1)} KB` : ''}
                  </p>
                </div>
                <span className="shrink-0 font-mono text-[10.5px] text-muted">
                  {relativeTime(item.date)}
                </span>
                <ExternalLinkIcon className="h-3 w-3 flex-shrink-0 text-muted/60" strokeWidth={2.25} />
              </a>
            </li>
          ))}
        </ul>
      )}
    </PanelShell>
  );
}
