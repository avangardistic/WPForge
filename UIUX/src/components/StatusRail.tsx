import { LockIcon } from 'lucide-react';
import { capabilities } from '../data/capabilities';
import type { HealthSignal } from '../data/site';

interface StatusRailProps {
  activeCapability: string;
  onSelectCapability: (id: string) => void;
  /** Health signals — live site data when connected, defaults otherwise. */
  signals: HealthSignal[];
  /** Capability grants for the connected user, keyed by capability id. */
  grants: Record<string, boolean> | null;
  userLabel: string | null;
  userRoles: string[] | null;
  apiMeta: { namespace: string; host: string };
  onFooterClick?: () => void;
}

function signalDotClass(tone: HealthSignal['tone']): string {
  if (tone === 'ok') return 'bg-ok';
  if (tone === 'bad') return 'bg-bad';
  return 'bg-forge';
}

export function StatusRail({
  activeCapability,
  onSelectCapability,
  signals,
  grants,
  userLabel,
  userRoles,
  apiMeta,
  onFooterClick,
}: StatusRailProps) {
  const connected = grants !== null;

  return (
    <aside className="hidden w-[228px] flex-shrink-0 flex-col gap-3 overflow-y-auto border-r border-hairline bg-panel p-3 wpforge-scroll lg:flex">
      <div className="flex flex-col gap-2">
        {signals.map((signal) => (
          <div
            key={signal.label}
            className="rounded-md border border-hairline bg-raised px-2.5 py-2">
            <p className="text-[9.5px] uppercase tracking-[0.07em] text-muted">{signal.label}</p>
            <p className="mt-1.5 flex items-center gap-1.5 text-[12.5px] font-semibold text-ink">
              <span
                className={`h-1.5 w-1.5 flex-shrink-0 rounded-full ${signalDotClass(signal.tone)}`}
                aria-hidden="true"
              />
              {signal.value}
            </p>
          </div>
        ))}
      </div>

      <nav aria-label="Capabilities" className="mt-1">
        <h2 className="px-0.5 pb-1.5 text-[9.5px] uppercase tracking-[0.08em] text-muted">
          Capabilities
        </h2>
        <ul className="flex flex-col gap-px">
          {capabilities.map((capability) => {
            const Icon = capability.icon;
            const isActive = capability.id === activeCapability;
            const allowed = connected ? (grants?.[capability.id] ?? false) : null;

            return (
              <li key={capability.id}>
                <button
                  type="button"
                  onClick={() => onSelectCapability(capability.id)}
                  aria-current={isActive ? 'page' : undefined}
                  title={allowed === false ? `Requires "${capability.cap}"` : undefined}
                  className={`flex w-full items-center gap-2 rounded-md border px-1.5 py-1.5 text-left text-[11.5px] transition-colors duration-150 ease-out focus:outline-none focus-visible:ring-1 focus-visible:ring-forge ${
                    isActive
                      ? 'border-forge/25 bg-forge/10 font-medium text-ink'
                      : 'border-transparent text-muted hover:bg-raised hover:text-ink'
                  }`}>
                  <span
                    className={`flex h-[18px] w-[18px] flex-shrink-0 items-center justify-center rounded border ${
                      isActive
                        ? 'border-forge bg-forge text-canvas'
                        : 'border-hairline bg-raised text-muted'
                    }`}
                    aria-hidden="true">
                    <Icon className="h-2.5 w-2.5" strokeWidth={2.25} />
                  </span>
                  <span className="truncate">{capability.label}</span>

                  {allowed !== null ? (
                    <span
                      className={`ml-auto h-1.5 w-1.5 flex-shrink-0 rounded-full ${
                        allowed ? 'bg-ok' : 'bg-muted/40'
                      }`}
                      aria-hidden="true"
                    />
                  ) : null}
                  {allowed === false ? (
                    <LockIcon className="h-2.5 w-2.5 flex-shrink-0 text-muted/60" aria-hidden="true" />
                  ) : null}
                </button>
              </li>
            );
          })}
        </ul>
      </nav>

      <button
        type="button"
        onClick={onFooterClick}
        className="mt-auto flex w-full flex-col gap-0.5 border-t border-hairline pt-2.5 text-left font-mono text-[10px] leading-relaxed text-muted transition-colors duration-150 hover:text-ink focus:outline-none focus-visible:ring-1 focus-visible:ring-forge">
        {connected && userLabel ? (
          <>
            <span className="text-[11px] font-semibold text-ink">
              {userLabel}
              {userRoles && userRoles.length > 0 ? <span className="ml-1.5 font-normal text-forge">{userRoles.join(', ')}</span> : null}
            </span>
            <span>{apiMeta.host}</span>
          </>
        ) : (
          <>
            <span>{apiMeta.namespace}</span>
            <span>{apiMeta.host}</span>
          </>
        )}
      </button>
    </aside>
  );
}
