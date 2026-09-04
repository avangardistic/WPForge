import type { ActivityEntry, HttpMethod } from '../types/activity';
import type { WpForgeLogEntry } from './api';

/** Parse a 'YYYY-MM-DD HH:MM:SS' MySQL timestamp (stored as UTC) to a Date. */
function parseMysqlTime(timestamp: string): Date {
  return new Date(`${timestamp.replace(' ', 'T')}Z`);
}

export function relativeTime(timestamp: string): string {
  const date = parseMysqlTime(timestamp);
  if (Number.isNaN(date.getTime())) return timestamp;

  const seconds = Math.round((Date.now() - date.getTime()) / 1000);
  if (seconds < 0) return 'just now'; // clock skew between client and site
  if (seconds < 45) return 'just now';
  const minutes = Math.round(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.round(hours / 24);
  if (days < 7) return `${days}d ago`;
  return timestamp.slice(0, 10);
}

const STATUS_TEXT: Record<number, string> = {
  200: 'OK',
  201: 'Created',
  204: 'No Content',
  301: 'Moved',
  400: 'Bad Request',
  401: 'Unauthorized',
  403: 'Forbidden',
  404: 'Not Found',
  409: 'Conflict',
  422: 'Unprocessable',
  429: 'Too Many',
  500: 'Server Error',
  503: 'Unavailable',
};

export function statusTextFor(code: number): string {
  return STATUS_TEXT[code] ?? '';
}

/** Guess an HTTP-style verb from an audit operation name. */
export function methodForOperation(operation: string): HttpMethod {
  const op = operation.toLowerCase();
  if (/(create|add|insert|generate|new)/.test(op)) return 'POST';
  if (/(update|edit|patch|modify|save|activate|deactivate|restore)/.test(op)) return 'PUT';
  if (/(delete|remove|clear|revoke|drop|flush)/.test(op)) return 'DELETE';
  return 'GET';
}

const LAST_VERB: Record<string, string> = {
  create: 'created',
  add: 'added',
  insert: 'inserted',
  init: 'initialized',
  update: 'updated',
  edit: 'edited',
  patch: 'patched',
  save: 'saved',
  delete: 'deleted',
  remove: 'removed',
  activate: 'activated',
  deactivate: 'deactivated',
  revoke: 'revoked',
  restore: 'restored',
  clear: 'cleared',
  flush: 'flushed',
  read: 'read',
  list: 'listed',
  query: 'queried',
  check: 'checked',
};

/** Humanize an operation id like plugin_init -> "Plugin initialized". */
export function describeOperation(operation: string): string {
  const parts = operation.split(/[._\s]+/).filter(Boolean);
  if (parts.length === 0) return operation;

  const last = parts[parts.length - 1].toLowerCase();
  const stem = parts
    .slice(0, -1)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
  const verb = LAST_VERB[last];
  if (verb) {
    return stem ? `${stem} ${verb}` : verb.charAt(0).toUpperCase() + verb.slice(1);
  }
  const label = parts.map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
  return label;
}

/** Map a WPForge audit-log row to the dashboard's activity-entry shape. */
export function logToActivityEntry(log: WpForgeLogEntry): ActivityEntry {
  const failed = !log.success || log.http_status >= 400;
  return {
    id: `log-${log.id}-${log.request_id}`,
    agent: log.username && log.username !== '' ? log.username : 'anonymous',
    method: methodForOperation(log.operation),
    endpoint: log.target,
    description: describeOperation(log.operation),
    status: failed ? log.http_status : 200,
    statusText: statusTextFor(failed ? log.http_status : 200),
    time: relativeTime(log.timestamp),
  };
}
