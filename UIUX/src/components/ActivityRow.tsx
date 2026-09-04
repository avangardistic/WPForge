import type { ActivityEntry } from '../types/activity';

interface ActivityRowProps {
  entry: ActivityEntry;
}

export function ActivityRow({ entry }: ActivityRowProps) {
  const failed = entry.status >= 400;

  return (
    <div className="grid grid-cols-[56px_52px_1fr] items-center gap-3 border-b border-hairline px-4 py-[7px] font-mono text-[11.5px] sm:grid-cols-[56px_52px_minmax(150px,190px)_1fr_44px]">
      <span className="text-muted">{entry.time.replace(' ago', '')}</span>
      <span className={`font-bold ${failed ? 'text-red-400' : 'text-forge'}`}>{entry.method}</span>
      <span className="truncate text-ink">{entry.endpoint}</span>
      <span className="hidden truncate font-sans text-[11.5px] text-muted sm:block">
        {entry.description}
      </span>
      <span className={`hidden text-right sm:block ${failed ? 'text-red-400' : 'text-ok'}`}>
        {entry.status}
      </span>
    </div>);

}