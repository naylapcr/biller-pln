import { TONE_CLASS, type StatusTone } from '@/lib/status';
import { cn } from '@/lib/utils';

interface StatusChipProps {
    label: string;
    tone: StatusTone;
    /** Titik 6 px di kiri (chip peran, status periode, status absensi). */
    titik?: boolean;
    className?: string;
}

const TITIK: Record<StatusTone, string> = {
    waiting: 'bg-amber',
    danger: 'bg-pln-red',
    success: 'bg-success',
    info: 'bg-pln-blue',
    sky: 'bg-sky-val',
    violet: 'bg-violet',
    neutral: 'bg-faint',
};

/** Pill tint 11 px semibold. */
export default function StatusChip({ label, tone, titik, className }: StatusChipProps) {
    return (
        <span
            className={cn(
                'inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] leading-[14px] font-semibold whitespace-nowrap',
                TONE_CLASS[tone],
                className,
            )}
        >
            {titik && <span aria-hidden className={cn('size-1.5 rounded-full', TITIK[tone])} />}
            {label}
        </span>
    );
}
