import { useCallback, useEffect, useRef, useState } from 'react';
import type {
  WpForgeCapabilities,
  WpForgeConnection,
  WpForgeHealth,
  WpForgeMediaItem,
  WpForgePlugin,
  WpForgePost,
  WpForgeStatus,
} from '../lib/api';
import { loadConnection, saveConnection, WpForgeApi } from '../lib/api';
import { logToActivityEntry } from '../lib/format';
import type { ActivityEntry } from '../types/activity';

export type ConnectionPhase = 'disconnected' | 'connecting' | 'connected' | 'error';

const LOG_POLL_MS = 5000;
const SLOW_POLL_MS = 30000;
const MAX_LOG_ENTRIES = 30;
const MAX_ACTIVITY_ENTRIES = 40;

export interface UseWpForgeResult {
  phase: ConnectionPhase;
  error: string | null;
  connection: WpForgeConnection | null;
  /** Live GET /status payload (null while disconnected). */
  site: WpForgeStatus | null;
  /** Live GET /health payload. */
  health: WpForgeHealth | null;
  /** Authenticated user + capabilities from GET /capabilities. */
  user: WpForgeCapabilities | null;
  posts: WpForgePost[];
  plugins: WpForgePlugin[];
  media: WpForgeMediaItem[];
  /** Latest audit-log rows mapped to activity entries (newest first). */
  activities: ActivityEntry[];
  activitiesCount: number;
  connect: (connection: WpForgeConnection) => Promise<boolean>;
  disconnect: () => void;
  refreshLogs: () => void;
  refreshPosts: () => void;
  refreshPlugins: () => void;
  refreshMedia: () => void;
}

/**
 * Connects the dashboard to a WPForge REST API. Credentials are persisted in
 * localStorage so the connection survives reloads; the audit log is polled
 * while connected unless `pollPaused` is true.
 */
export function useWpForge(pollPaused = false): UseWpForgeResult {
  const [api, setApi] = useState<WpForgeApi | null>(null);
  const [connection, setConnection] = useState<WpForgeConnection | null>(null);
  const [phase, setPhase] = useState<ConnectionPhase>('disconnected');
  const [error, setError] = useState<string | null>(null);

  const [site, setSite] = useState<WpForgeStatus | null>(null);
  const [health, setHealth] = useState<WpForgeHealth | null>(null);
  const [user, setUser] = useState<WpForgeCapabilities | null>(null);
  const [posts, setPosts] = useState<WpForgePost[]>([]);
  const [plugins, setPlugins] = useState<WpForgePlugin[]>([]);
  const [media, setMedia] = useState<WpForgeMediaItem[]>([]);
  const [activities, setActivities] = useState<ActivityEntry[]>([]);
  const [activitiesCount, setActivitiesCount] = useState(0);

  const runIdRef = useRef(0);

  const connect = useCallback(async (next: WpForgeConnection): Promise<boolean> => {
    const runId = ++runIdRef.current;
    const client = new WpForgeApi(next);
    setApi(client);
    setConnection(next);
    setPhase('connecting');
    setError(null);

    try {
      const [status, healthData, caps] = await Promise.all([
        client.status(),
        client.health(),
        client.capabilities(),
      ]);
      if (runId !== runIdRef.current) return false;

      setSite(status);
      setHealth(healthData);
      setUser(caps);
      setPhase('connected');
      saveConnection(next);

      client.logs(MAX_LOG_ENTRIES).then((data) => {
        if (runId !== runIdRef.current) return;
        setActivities(data.logs.map(logToActivityEntry).slice(0, MAX_ACTIVITY_ENTRIES));
        setActivitiesCount(data.total);
    }).catch(() => undefined);

      client.posts(15).then(setPosts).catch(() => setPosts([]));
      client.plugins().then(setPlugins).catch(() => setPlugins([]));
      client.media(12).then(setMedia).catch(() => setMedia([]));
      return true;
    } catch (err) {
      if (runId !== runIdRef.current) return false;
      setPhase('error');
      setError(err instanceof Error ? err.message : 'Connection failed');
      return false;
    }
  }, []);

  const disconnect = useCallback(() => {
    runIdRef.current += 1;
    setApi(null);
    setConnection(null);
    setPhase('disconnected');
    setError(null);
    setSite(null);
    setHealth(null);
    setUser(null);
    setPosts([]);
    setPlugins([]);
    setMedia([]);
    setActivities([]);
    setActivitiesCount(0);
    saveConnection(null);
  }, []);

  /** Re-pull the audit log (used by the manual refresh + poller). */
  const refreshLogs = useCallback(() => {
    if (!api || phase !== 'connected') return;
    const runId = runIdRef.current;
    api.logs(MAX_LOG_ENTRIES)
      .then((data) => {
        if (runId !== runIdRef.current) return;
        setActivities(data.logs.map(logToActivityEntry).slice(0, MAX_ACTIVITY_ENTRIES));
        setActivitiesCount(data.total);
      })
      .catch(() => undefined);
  }, [api, phase]);

  const refreshPosts = useCallback(() => {
    if (!api || phase !== 'connected') return;
    const runId = runIdRef.current;
    api.posts(15).then((next) => {
      if (runId === runIdRef.current) setPosts(next);
    }).catch(() => undefined);
  }, [api, phase]);

  const refreshPlugins = useCallback(() => {
    if (!api || phase !== 'connected') return;
    const runId = runIdRef.current;
    api.plugins().then((next) => {
      if (runId === runIdRef.current) setPlugins(next);
    }).catch(() => undefined);
  }, [api, phase]);

  const refreshMedia = useCallback(() => {
    if (!api || phase !== 'connected') return;
    const runId = runIdRef.current;
    api.media(12).then((next) => {
      if (runId === runIdRef.current) setMedia(next);
    }).catch(() => undefined);
  }, [api, phase]);

  // Restore a previously saved connection on mount.
  useEffect(() => {
    const saved = loadConnection();
    if (saved) {
      void connect(saved);
    }
    return () => {
      runIdRef.current += 1;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Audit-log polling.
  useEffect(() => {
    if (!api || phase !== 'connected' || pollPaused) return;
    const timer = window.setInterval(refreshLogs, LOG_POLL_MS);
    return () => window.clearInterval(timer);
  }, [api, phase, pollPaused, refreshLogs]);

  // Slow polling of status + health so the header stays fresh.
  useEffect(() => {
    if (!api || phase !== 'connected') return;
    const runId = runIdRef.current;
    const timer = window.setInterval(() => {
      api.status().then((next) => {
        if (runId === runIdRef.current) setSite(next);
      }).catch(() => undefined);
      api.health().then((next) => {
        if (runId === runIdRef.current) setHealth(next);
      }).catch(() => undefined);
    }, SLOW_POLL_MS);
    return () => window.clearInterval(timer);
  }, [api, phase]);

  return {
    phase,
    error,
    connection,
    site,
    health,
    user,
    posts,
    plugins,
    media,
    activities,
    activitiesCount,
    connect,
    disconnect,
    refreshLogs,
    refreshPosts,
    refreshPlugins,
    refreshMedia,
  };
}
