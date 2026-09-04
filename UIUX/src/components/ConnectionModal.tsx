import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import {
  EyeIcon,
  EyeOffIcon,
  KeyRoundIcon,
  Loader2Icon,
  PlugIcon,
  ShieldCheckIcon,
  XIcon,
} from 'lucide-react';
import type {
  WpForgeCapabilities,
  WpForgeConnection,
} from '../lib/api';
import type { ConnectionPhase } from '../hooks/useWpForge';

interface ConnectionModalProps {
  open: boolean;
  phase: ConnectionPhase;
  error: string | null;
  connection: WpForgeConnection | null;
  user: WpForgeCapabilities | null;
  onConnect: (connection: WpForgeConnection) => Promise<boolean>;
  onDisconnect: () => void;
  onClose: () => void;
}

const DEFAULT_HOST = 'https://neginhafari.ir';

export function ConnectionModal({
  open,
  phase,
  error,
  connection,
  user,
  onConnect,
  onDisconnect,
  onClose,
}: ConnectionModalProps) {
  const [host, setHost] = useState(DEFAULT_HOST);
  const [username, setUsername] = useState('wpforge');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  useEffect(() => {
    if (!open) return;
    if (connection) {
      setHost(connection.host);
      setUsername(connection.username);
      setPassword(connection.password);
    }
  }, [open, connection]);

  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && phase !== 'connecting') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, phase, onClose]);

  if (!open) return null;

  const connecting = phase === 'connecting';

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    const trimmedHost = host.trim().replace(/\/+$/, '');
    if (!trimmedHost) return;
    const ok = await onConnect({
      host: trimmedHost,
      username: username.trim(),
      password: password.trim(),
    });
    if (ok) onClose();
  };

  const connected = phase === 'connected' && connection;

  const inputClass =
    'w-full rounded-md border border-hairline bg-console px-3 py-2 font-mono text-[12.5px] text-ink placeholder:text-muted/50 focus:outline-none focus-visible:ring-1 focus-visible:ring-forge disabled:opacity-50';

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-label="WPForge API connection">
      <div className="w-full max-w-[440px] overflow-hidden rounded-lg border border-hairline bg-panel shadow-2xl">
        <div className="flex items-center gap-2.5 border-b border-hairline px-4 py-3">
          <span className="flex h-6 w-6 items-center justify-center rounded-md bg-forge text-canvas" aria-hidden="true">
            <PlugIcon className="h-3.5 w-3.5" strokeWidth={2.5} />
          </span>
          <h2 className="text-[13.5px] font-bold text-ink">
            {connected ? 'WPForge API connection' : 'Connect to WPForge API'}
          </h2>
          <button
            type="button"
            onClick={onClose}
            disabled={connecting}
            aria-label="Close"
            className="ml-auto flex h-6 w-6 items-center justify-center rounded-md text-muted transition-colors duration-150 hover:bg-raised hover:text-ink focus:outline-none focus-visible:ring-1 focus-visible:ring-forge disabled:opacity-50">
            <XIcon className="h-3.5 w-3.5" />
          </button>
        </div>

        <div className="px-4 py-4">
          {connected && user ? (
            <div className="flex flex-col gap-3">
              <div className="flex items-center gap-2.5 rounded-md border border-ok/30 bg-ok/10 px-3 py-2.5">
                <ShieldCheckIcon className="h-4 w-4 flex-shrink-0 text-ok" />
                <div className="min-w-0">
                  <p className="truncate text-[12.5px] font-semibold text-ink">
                    Signed in as {user.user}
                  </p>
                  <p className="truncate font-mono text-[10.5px] text-muted">
                    {connection?.host} · {user.roles.join(', ')}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => {
                  onDisconnect();
                  onClose();
                }}
                className="self-start rounded-md border border-red-500/30 px-3 py-1.5 text-[11.5px] font-semibold text-red-400 transition-colors duration-150 hover:bg-red-500/10 focus:outline-none focus-visible:ring-1 focus-visible:ring-red-400">
                Disconnect
              </button>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="flex flex-col gap-3">
              <div>
                <label htmlFor="api-host" className="mb-1.5 block text-[9.5px] font-semibold uppercase tracking-[0.08em] text-muted">
                  Site URL
                </label>
                <input
                  id="api-host"
                  type="url"
                  required
                  spellCheck={false}
                  placeholder={DEFAULT_HOST}
                  value={host}
                  onChange={(event) => setHost(event.target.value)}
                  disabled={connecting}
                  className={inputClass}
                />
              </div>
              <div>
                <label htmlFor="api-user" className="mb-1.5 block text-[9.5px] font-semibold uppercase tracking-[0.08em] text-muted">
                  Username
                </label>
                <input
                  id="api-user"
                  type="text"
                  required
                  autoComplete="username"
                  value={username}
                  onChange={(event) => setUsername(event.target.value)}
                  disabled={connecting}
                  className={inputClass}
                />
              </div>
              <div>
                <label htmlFor="api-password" className="mb-1.5 block text-[9.5px] font-semibold uppercase tracking-[0.08em] text-muted">
                  Application password
                </label>
                <div className="relative">
                  <input
                    id="api-password"
                    type={showPassword ? 'text' : 'password'}
                    required
                    autoComplete="current-password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    disabled={connecting}
                    placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
                    className={`${inputClass} pr-9`}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((value) => !value)}
                    tabIndex={-1}
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                    className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-muted transition-colors duration-150 hover:text-ink">
                    {showPassword ? <EyeOffIcon className="h-3.5 w-3.5" /> : <EyeIcon className="h-3.5 w-3.5" />}
                  </button>
                </div>
              </div>

              <p className="flex items-start gap-1.5 text-[10.5px] leading-relaxed text-muted">
                <KeyRoundIcon className="mt-0.5 h-3 w-3 flex-shrink-0" />
                Credentials are sent only to your WordPress site and stored locally in this browser (WordPress → Users → Profile → Application Passwords).
              </p>

              {phase === 'error' && error ? (
                <p className="rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2 text-[11.5px] leading-relaxed text-red-400">
                  {error}
                </p>
              ) : null}

              <div className="mt-1 flex items-center justify-end gap-2">
                <button
                  type="button"
                  onClick={onClose}
                  disabled={connecting}
                  className="rounded-md border border-hairline px-3 py-1.5 text-[11.5px] font-medium text-muted transition-colors duration-150 hover:border-white/20 hover:text-ink focus:outline-none focus-visible:ring-1 focus-visible:ring-forge disabled:opacity-50">
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={connecting}
                  className="flex items-center gap-1.5 rounded-md bg-forge px-3.5 py-1.5 text-[11.5px] font-bold text-canvas transition-opacity duration-150 hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-forge/50 disabled:opacity-60">
                  {connecting ? <Loader2Icon className="h-3.5 w-3.5 animate-spin" /> : <PlugIcon className="h-3.5 w-3.5" />}
                  {connecting ? 'Connecting…' : 'Connect'}
                </button>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}
