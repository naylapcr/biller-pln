import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface StatCardProps {
    label: string;
    value: ReactNode;
    /** Chip di kanan angka. */
    chip?: ReactNode;
    caption?: ReactNode;
    valueClassName?: string;
    className?: string;
}

/** Kartu angka: label kapital 10 px, nilai 24 px, keterangan 11 px. */
export default function StatCard({ label, value, chip, caption, valueClassName, className }: StatCardProps) {
    return (
        <div className={cn('flex flex-col gap-2 rounded-2xl border border-line bg-white p-4 shadow-kartu', className)}>
            <p className="text-[10px] font-bold text-muted-ink uppercase">{label}</p>
            <div className="flex flex-wrap items-center gap-2">
                <span className={cn('text-2xl leading-[30px] font-extrabold text-ink', valueClassName)}>{value}</span>
                {chip}
            </div>
            {caption && <p className="text-[11px] font-medium text-muted-ink">{caption}</p>}
        </div>
    );
}
