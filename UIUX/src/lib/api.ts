/**
 * Typed client for the WPForge REST API (https://<host>/wp-json/wpforge/v1).
 *
 * Authentication uses WordPress Application Passwords sent as an HTTP Basic
 * `Authorization` header, which the WP REST API accepts cross-origin.
 */

export interface WpForgeConnection {
  /** Site origin, e.g. https://example.com */
  host: string;
  /** WordPress user login. */
  username: string;
  /** WordPress Application Password (spaces are optional). */
  password: string;
}

export interface WpForgeStatus {
  status: string;
  version: string;
  wordpress_version: string;
  php_version: string;
  time: string;
  site_url: string;
  home_url: string;
}

export interface WpForgeHealthCheck {
  status: 'ok' | 'warning' | 'error';
  details: string;
}

export interface WpForgeHealth {
  status: 'healthy' | 'degraded';
  checks: Record<string, WpForgeHealthCheck>;
  timestamp: string;
}

export interface WpForgeCapabilities {
  user: string;
  user_id: number;
  roles: string[];
  capabilities: Record<string, boolean>;
}

export interface WpForgeLogEntry {
  id: number;
  request_id: string;
  user_id: number | null;
  username: string;
  operation: string;
  target: string;
  success: 0 | 1;
  http_status: number;
  error_code: string | null;
  ip_address: string | null;
  timestamp: string;
}

export interface WpForgeLogsData {
  logs: WpForgeLogEntry[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

export interface WpForgePost {
  id: number;
  title: string;
  status: string;
  type: string;
  date: string;
  modified: string;
  author: string;
  author_id: number;
  excerpt?: string;
  content?: string;
}

export interface WpForgePlugin {
  name: string;
  slug: string;
  version: string;
  author: string;
  is_active: boolean;
  requires_wp?: string;
  requires_php?: string;
}

export interface WpForgeMediaItem {
  id: number;
  title: string;
  filename: string;
  url: string;
  type: string;
  width?: number;
  height?: number;
  file_size?: number;
  date: string;
}

export class WpForgeApiError extends Error {
  readonly code: string;
  readonly status: number;

  constructor(code: string, message: string, status: number) {
    super(message);
    this.name = 'WpForgeApiError';
    this.code = code;
    this.status = status;
  }
}

const STORAGE_KEY = 'wpforge.connection.v1';

export function loadConnection(): WpForgeConnection | null {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as WpForgeConnection;
    if (parsed && typeof parsed.host === 'string' && parsed.host.length > 0) {
      return parsed;
    }
  } catch {
    /* corrupted storage — ignore */
  }
  return null;
}

export function saveConnection(connection: WpForgeConnection | null): void {
  try {
    if (connection) {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(connection));
    } else {
      window.localStorage.removeItem(STORAGE_KEY);
    }
  } catch {
    /* storage unavailable — connection simply won't persist */
  }
}

/**
 * Turn an HTTP status (and any message the server sent) into something a person
 * can act on. The server's own message is preferred when it is specific;
 * otherwise a status-appropriate explanation is used.
 */
function friendlyMessage(status: number, serverMessage: string): string {
  const generic = !serverMessage || /^request failed/i.test(serverMessage);
  switch (status) {
    case 401:
      return 'Authentication failed. Check the username and Application Password, and that the site is served over HTTPS.';
    case 403:
      return serverMessage && !generic
        ? serverMessage
        : 'Your account does not have permission for this operation.';
    case 404:
      return 'The WPForge API was not found at this site. Confirm the plugin is installed and active, and that permalinks are enabled.';
    case 429:
      return 'Too many requests. Wait a moment and try again.';
    default:
      if (status >= 500) {
        return 'The site returned a server error. Check the WordPress error log for details.';
      }
      return generic ? `Request failed (HTTP ${status}).` : serverMessage;
  }
}

export class WpForgeApi {
  private readonly connection: WpForgeConnection;
  private readonly headers: Record<string, string>;

  constructor(connection: WpForgeConnection) {
    this.connection = connection;

    const basic = window.btoa(
      `${connection.username}:${connection.password.replace(/\s+/g, '')}`
    );
    this.headers = {
      Authorization: `Basic ${basic}`,
      'Content-Type': 'application/json',
    };
  }

  get baseUrl(): string {
    const host = this.connection.host.replace(/\/+$/, '');
    return `${host}/wp-json/wpforge/v1`;
  }

  get host(): string {
    return this.connection.host;
  }

  get username(): string {
    return this.connection.username;
  }

  private async request<T>(path: string, init?: RequestInit): Promise<T> {
    let response: Response;
    try {
      response = await fetch(`${this.baseUrl}${path}`, {
        ...init,
        headers: { ...this.headers, ...(init?.headers ?? {}) },
      });
    } catch {
      // fetch rejects on DNS failure, TLS error, CORS block, or offline —
      // never with a useful message, so translate it into a clear one.
      throw new WpForgeApiError(
        'NETWORK',
        'Could not reach the site. Check the URL is correct and served over HTTPS, that the site is online, and that the WPForge plugin is active.',
        0
      );
    }

    let payload: unknown = null;
    try {
      payload = await response.json();
    } catch {
      payload = null;
    }

    if (!response.ok) {
      const code =
        payload && typeof payload === 'object' && 'code' in payload
          ? String((payload as { code: unknown }).code)
          : 'HTTP_ERROR';
      const serverMessage =
        payload && typeof payload === 'object' && 'message' in payload
          ? String((payload as { message: unknown }).message)
          : '';
      throw new WpForgeApiError(code, friendlyMessage(response.status, serverMessage), response.status);
    }

    // Unwrap the WPForge envelope: { success, request_id, data }.
    if (payload && typeof payload === 'object' && 'data' in payload) {
      const data = (payload as { data: unknown }).data;
      if (data !== undefined && data !== null) {
        return data as T;
      }
    }
    return payload as T;
  }

  status(): Promise<WpForgeStatus> {
    return this.request<WpForgeStatus>('/status');
  }

  health(): Promise<WpForgeHealth> {
    return this.request<WpForgeHealth>('/health');
  }

  capabilities(): Promise<WpForgeCapabilities> {
    return this.request<WpForgeCapabilities>('/capabilities');
  }

  logs(limit = 30): Promise<WpForgeLogsData> {
    return this.request<WpForgeLogsData>(`/logs?limit=${limit}`);
  }

  posts(perPage = 15): Promise<WpForgePost[]> {
    return this.request<WpForgePost[]>(`/posts?per_page=${perPage}`);
  }

  plugins(): Promise<WpForgePlugin[]> {
    return this.request<WpForgePlugin[]>('/plugins');
  }

  media(perPage = 12): Promise<WpForgeMediaItem[]> {
    return this.request<WpForgeMediaItem[]>(`/media?per_page=${perPage}`);
  }
}
