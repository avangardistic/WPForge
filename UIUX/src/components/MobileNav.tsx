import { LockIcon } from 'lucide-react';
import { capabilities } from '../data/capabilities';

interface MobileNavProps {
  activeCapability: string;
  onSelectCapability: (id: string) => void;
  /** Capability grants for the connected user, or null when disconnected. */
  grants: Record<string, boolean> | null;
}

/**
 * Horizontally scrollable capability selector for viewports below `lg`, where
 * the sidebar navigation is hidden. Keeps every area reachable on a phone.
 */
export function MobileNav({ activeCapability, onSelectCapability, grants }: MobileNavProps) {
  return (
    <nav aria-label="Capabilities" className="-mx-4 lg:hidden">
      <ul className="flex gap-1.5 overflow-x-auto px-4 pb-0.5 wpforge-scroll">
        {capabilities.map((capability) => {
          const Icon = capability.icon;
          const isActive = capability.id === activeCapability;
          const allowed = grants ? (grants[capability.id] ?? false) : null;
          return (
            <li key={capability.id} className="flex-shrink-0">
              <button
                type="button"
                onClick={() => onSelectCapability(capability.id)}
                aria-current={isActive ? 'page' : undefined}
                className={`flex items-center gap-1.5 whitespace-nowrap rounded-md border px-2.5 py-1.5 text-[11.5px] transition-colors duration-150 focus:outline-none focus-visible:ring-1 focus-visible:ring-forge ${
                  isActive
                    ? 'border-forge/30 bg-forge/10 font-medium text-ink'
                    : 'border-hairline bg-raised text-muted'
                }`}>
                <Icon className="h-3 w-3 flex-shrink-0" strokeWidth={2.25} aria-hidden="true" />
                {capability.label}
                {allowed === false ? (
                  <LockIcon className="h-2.5 w-2.5 flex-shrink-0 text-muted/60" aria-hidden="true" />
                ) : null}
              </button>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
