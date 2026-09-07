import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import type { ActivityEntry } from '../types/activity';

interface PinnedActivityProps {
  entry: ActivityEntry;
  live: boolean;
}

export function PinnedActivity({ entry, live }: PinnedActivityProps) {
  const reduceMotion = useReducedMotion();
  const failed = entry.status >= 400;

  return (
    <div className="flex-shrink-0 px-3.5 pb-1 pt-3">
      <AnimatePresence mode="wait" initial={false}>
        <motion.article
          key={entry.id}
          initial={reduceMotion ? { opacity: 0 } : { opacity: 0, y: -8, scale: 0.985 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          exit={reduceMotion ? { opacity: 0 } : { opacity: 0, y: 6 }}
          transition={{ duration: 0.22, ease: [0.23, 1, 0.32, 1] }}
          aria-live={live ? 'polite' : 'off'}
          className={`flex flex-col gap-1.5 rounded-md border px-3 py-2.5 ${
          failed ? 'border-bad/40 bg-bad/[0.07]' : 'border-forge/35 bg-forge/[0.07]'}`
          }>
          
          <div className="flex items-center gap-2">
            <span
              className={`rounded px-1.5 py-0.5 text-[9px] font-extrabold tracking-[0.06em] text-canvas ${
              failed ? 'bg-bad' : 'bg-forge'}`
              }>
              
              NEW
            </span>
            <span className="flex items-center gap-1.5 text-[12.5px] font-bold text-ink">
              <span
                className={`h-1.5 w-1.5 rounded-full ${failed ? 'bg-bad' : 'bg-forge'}`}
                aria-hidden="true" />
              
              {entry.agent}
            </span>
            <span className="ml-auto font-mono text-[11px] text-muted">{entry.time}</span>
          </div>

          <p className="font-mono text-xs text-ink">
            <span className={`mr-1.5 font-bold ${failed ? 'text-bad' : 'text-forge'}`}>
              {entry.method}
            </span>
            {entry.endpoint}
          </p>

          <div className="flex flex-wrap items-center gap-2.5 text-xs text-muted">
            <span>{entry.description}</span>
            <span
              className={`rounded border px-1.5 py-0.5 font-mono text-[11px] ${
              failed ?
              'border-bad/25 bg-bad/10 text-bad' :
              'border-ok/25 bg-ok/10 text-ok'}`
              }>
              
              {entry.status} {entry.statusText}
            </span>
          </div>
        </motion.article>
      </AnimatePresence>
    </div>);

}