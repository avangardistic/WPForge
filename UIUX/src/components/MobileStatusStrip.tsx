import type { HealthSignal } from '../data/site';

interface MobileStatusStripProps {
  signals: HealthSignal[];
}

function signalDotClass(tone: HealthSignal['tone']): string {
  if (tone === 'ok') return 'bg-ok';
  if (tone === 'bad') return 'bg-red-400';
  return 'bg-forge';
}

export function MobileStatusStrip({ signals }: MobileStatusStripProps) {
  return (
    <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 wpforge-scroll lg:hidden">
      {signals.map((signal) => (
        <div
          key={signal.label}
          className="flex-shrink-0 rounded-md border border-hairline bg-raised px-2.5 py-2">
          <p className="text-[9.5px] uppercase tracking-[0.07em] text-muted">{signal.label}</p>
          <p className="mt-1 flex items-center gap-1.5 whitespace-nowrap text-xs font-semibold text-ink">
            <span
              className={`h-1.5 w-1.5 rounded-full ${signalDotClass(signal.tone)}`}
              aria-hidden="true"
            />
            {signal.value}
          </p>
        </div>
      ))}
    </div>
  );
}
