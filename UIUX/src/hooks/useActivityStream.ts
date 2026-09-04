import { useEffect, useRef, useState } from 'react';
import { incomingActivity, seedActivity } from '../data/activity';
import type { ActivityEntry } from '../types/activity';

const MAX_ENTRIES = 40;
const INTERVAL_MS = 4200;

/**
 * Simulates the live `wpforge/v1` audit stream: new agent calls arrive at the
 * top of the feed and older "just now" entries age off to a relative label.
 */
export function useActivityStream(live: boolean): ActivityEntry[] {
  const [entries, setEntries] = useState<ActivityEntry[]>(seedActivity);
  const cursor = useRef(0);

  useEffect(() => {
    if (!live) return;

    const timer = window.setInterval(() => {
      setEntries((prev) => {
        const template = incomingActivity[cursor.current % incomingActivity.length];
        cursor.current += 1;

        const next: ActivityEntry = {
          ...template,
          id: `live-${cursor.current}-${Date.now()}`,
          time: 'just now'
        };

        const aged = prev.map((entry) =>
        entry.time === 'just now' ? { ...entry, time: '1m ago' } : entry
        );

        return [next, ...aged].slice(0, MAX_ENTRIES);
      });
    }, INTERVAL_MS);

    return () => window.clearInterval(timer);
  }, [live]);

  return entries;
}