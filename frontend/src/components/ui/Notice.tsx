import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type Tone = 'danger' | 'success' | 'info' | 'waiting';

const TONE: Record<Tone, string> = {
    danger: 'border-error-line bg-error-row text-error-deep',
    success: 'border-success-line bg-success-row text-success-dark',
    info: 'border-pln-blue-soft bg-pln-blue-row text-pln-blue',
    waiting: 'border-pln-yellow bg-pln-yellow-row text-[#854d0e]',
};

interface NoticeProps {
    tone?: Tone;
    title?: ReactNode;
    children?: ReactNode;
    action?: ReactNode;
    icon?: LucideIcon;
    className?: string;
}

/** Banner pemberitahuan: garis dan latar tint, teks 12 px, chip atau tombol di kanan. */
export default function Notice({ tone = 'danger', title, children, action, icon, className }: NoticeProps) {
    const Icon = icon;

    return (
        <div role={tone === 'danger' ? 'alert' : 'status'} className={cn('flex flex-wrap items-center gap-3 rounded-xl border px-4 py-3', TONE[tone], className)}>
            {Icon && <Icon className="size-4 shrink-0" aria-hidden />}
            <div className="min-w-0 flex-1 basis-60 text-xs leading-[15px] font-semibold">
                {title && <span>{title}{children ? ': ' : ''}</span>}
                {children}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}
