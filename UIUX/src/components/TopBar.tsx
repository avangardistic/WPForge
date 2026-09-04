import { HammerIcon, Loader2Icon, PlugIcon } from 'lucide-react';
import { site } from '../data/site';
import type { ConnectionPhase } from '../hooks/useWpForge';

interface TopBarProps {
  /** Connection phase drives the pill styling and label. */
  phase: ConnectionPhase;
  /** Logged-in WordPress username, when connected. */
  userLabel: string | null;
  /** Site meta segments joined with "·" in the middle of the bar. */
  metaSegments: string[];
  onConnectionClick: () => void;
}

export function TopBar({ phase, userLabel, metaSegments, onConnectionClick }: TopBarProps) {
  const connected = phase === 'connected';
  const connecting = phase === 'connecting';
  const failed = phase === 'error';

  const pillTone = connected
    ? 'border-ok/30 bg-ok/10 text-ok'
    : failed
      ? 'border-red-500/30 bg-red-500/10 text-red-400'
      : 'border-hairline bg-raised text-muted';

  const pillLabel = connected
    ? `Connected${userLabel ? ` · ${userLabel}` : ''}`
    : connecting
      ? 'Connecting…'
      : failed
        ? 'Reconnect'
        : 'Connect API';

  const pillGlyph = connecting ? (
    <Loader2Icon className="h-3 w-3 animate-spin" aria-hidden="true" />
  ) : connected ? (
    <span className="h-[7px] w-[7px] rounded-full bg-ok wpforge-live" aria-hidden="true" />
  ) : (
    <PlugIcon className="h-3 w-3" strokeWidth={2.5} aria-hidden="true" />
  );

  return (
    <header className="flex h-14 flex-shrink-0 items-center gap-6 border-b border-hairline bg-panel px-4 sm:px-5">
      <div className="flex items-center gap-2">
        <span
          className="flex h-6 w-6 items-center justify-center rounded-md bg-forge text-canvas"
          aria-hidden="true">
          <HammerIcon className="h-3.5 w-3.5" strokeWidth={2.5} />
        </span>
        <span className="text-[15px] font-bold tracking-[0.2px] text-ink">{site.name}</span>
      </div>

      <p className="hidden font-mono text-xs text-muted lg:block">
        {metaSegments.map((segment, index) => (
          <span key={`${segment}-${index}`}>
            {index > 0 ? <span className="px-1.5 text-hairline">·</span> : null}
            {segment}
          </span>
        ))}
      </p>

      <button
        type="button"
        onClick={onConnectionClick}
        className={`ml-auto flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition-colors duration-150 ease-out focus:outline-none focus-visible:ring-1 focus-visible:ring-forge ${pillTone} ${
          connected ? 'cursor-pointer hover:brightness-110' : 'cursor-pointer hover:border-white/25'
        }`}>
        {pillGlyph}
        {pillLabel}
      </button>
    </header>
  );
}
