import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { ArrowRightIcon, PauseIcon, PlayIcon, RotateCwIcon } from 'lucide-react';
import { ActivityRow } from './ActivityRow';
import { PinnedActivity } from './PinnedActivity';
import type { ActivityEntry } from '../types/activity';

interface ActivityStreamProps {
  entries: ActivityEntry[];
  live: boolean;
  /** True when the stream shows the real site audit log. */
  connected: boolean;
  connecting?: boolean;
  activityCount?: number;
  onToggleLive: () => void;
  onRefresh?: () => void;
  /** Footer hint — e.g. the live API endpoint being tailed. */
  tailHint: string;
}

export function ActivityStream({
  entries,
  live,
  connected,
  connecting,
  activityCount,
  onToggleLive,
  onRefresh,
  tailHint,
}: ActivityStreamProps) {
  const reduceMotion = useReducedMotion();
  const [pinned, ...rest] = entries;
  const empty = connected && entries.length === 0;

  return (
    <section className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-hairline bg-console">
      <div className="flex flex-shrink-0 items-center gap-2.5 border-b border-hairline bg-raised px-4 py-2.5">
        <span
          className={`h-[7px] w-[7px] flex-shrink-0 rounded-full ${
            live ? 'bg-forge wpforge-live' : 'bg-muted'
          }`}
          aria-hidden="true"
        />
        <span
          className={`text-[10.5px] font-bold uppercase tracking-[0.08em] ${
            live ? 'text-forge' : 'text-muted'
          }`}>
          {live ? 'Live' : 'Paused'}
        </span>
        <h2 className="text-[12.5px] font-semibold text-ink">
          {connected ? 'Agent Audit Stream' : 'Agent Activity Stream'}
        </h2>
        {connected && typeof activityCount === 'number' ? (
          <span className="rounded border border-hairline bg-console px-1.5 py-0.5 font-mono text-[10px] text-muted">
            {activityCount}
          </span>
        ) : null}

        {onRefresh ? (
          <button
            type="button"
            onClick={onRefresh}
            disabled={connecting}
            aria-label="Refresh audit log"
            className="ml-auto flex h-6 w-6 items-center justify-center rounded-md border border-hairline text-muted transition-colors duration-150 ease-out hover:border-white/20 hover:text-ink focus:outline-none focus-visible:ring-1 focus-visible:ring-forge disabled:opacity-50">
            <RotateCwIcon className={`h-3 w-3 ${connecting ? 'animate-spin' : ''}`} strokeWidth={2.25} />
          </button>
        ) : null}

        <button
          type="button"
          onClick={onToggleLive}
          className="flex items-center gap-1.5 rounded-md border border-hairline px-2 py-1 text-[11px] text-muted transition-colors duration-150 ease-out hover:border-white/20 hover:text-ink focus:outline-none focus-visible:ring-1 focus-visible:ring-forge">
          {live ? <PauseIcon className="h-3 w-3" strokeWidth={2.25} /> : <PlayIcon className="h-3 w-3" strokeWidth={2.25} />}
          {live ? 'Pause' : 'Resume'}
        </button>

        <a
          href="#audit-log"
          className="hidden items-center gap-1 text-[11px] text-muted transition-colors duration-150 ease-out hover:text-ink sm:flex">
          View full audit log
          <ArrowRightIcon className="h-3 w-3" strokeWidth={2.25} />
        </a>
      </div>

      {pinned ? <PinnedActivity entry={pinned} live={live} /> : null}

      <div className="min-h-0 flex-1 overflow-y-auto wpforge-scroll">
        {empty ? (
          <div className="flex h-full flex-col items-center justify-center gap-1.5 px-6 py-12 text-center">
            <p className="text-[12.5px] font-semibold text-muted">
              {connecting ? 'Loading audit log…' : 'No audit entries yet'}
            </p>
            <p className="max-w-[42ch] text-[11px] leading-relaxed text-muted/70">
              WPForge operations from this site will stream here in real time once the
              audit log has records. {connected ? 'Try the Content, Media, or Plugins views in the meantime.' : ''}
            </p>
          </div>
        ) : (
          <ul aria-label="Recent agent API calls">
            <AnimatePresence initial={false}>
              {rest.map((entry) => (
                <motion.li
                  key={entry.id}
                  layout={!reduceMotion}
                  initial={reduceMotion ? { opacity: 0 } : { opacity: 0, y: -6 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.2, ease: [0.23, 1, 0.32, 1] }}>
                  <ActivityRow entry={entry} />
                </motion.li>
              ))}
            </AnimatePresence>
          </ul>
        )}
      </div>

      <p className="flex flex-shrink-0 items-center gap-1.5 border-t border-hairline bg-raised px-4 py-2 font-mono text-[11px] text-muted">
        {tailHint}
        <span
          className={`inline-block h-3 w-1.5 bg-forge ${live ? 'wpforge-cursor' : 'opacity-30'}`}
          aria-hidden="true"
        />
      </p>
    </section>
  );
}
