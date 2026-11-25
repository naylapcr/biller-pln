import { cn } from '@/lib/utils';

interface SegmentedTabsProps<V extends string> {
    value: V;
    onChange: (value: V) => void;
    items: { value: V; label: string; count?: number }[];
    label: string;
    className?: string;
}

/** Tombol pilihan berjajar: aktif biru, lainnya putih bergaris. */
export default function SegmentedTabs<V extends string>({ value, onChange, items, label, className }: SegmentedTabsProps<V>) {
    return (
        <div role="tablist" aria-label={label} className={cn('inline-flex max-w-full gap-2 overflow-x-auto', className)}>
            {items.map((item) => {
                const active = item.value === value;
                return (
                    <button
                        key={item.value}
                        type="button"
                        role="tab"
                        aria-selected={active}
                        onClick={() => onChange(item.value)}
                        className={cn(
                            'inline-flex h-9 shrink-0 items-center gap-1.5 rounded-10 border px-4 text-xs whitespace-nowrap transition-colors',
                            active
                                ? 'border-pln-blue bg-pln-blue font-bold text-white shadow-tombol'
                                : 'border-line-strong bg-white font-semibold text-ink-soft hover:bg-canvas',
                        )}
                    >
                        {item.label}
                        {item.count !== undefined && (
                            <span className={cn('text-xs', active ? 'text-white/80' : 'text-muted-ink')}>{item.count}</span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
