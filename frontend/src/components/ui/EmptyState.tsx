import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    children?: ReactNode;
    action?: ReactNode;
    className?: string;
}

/** Keadaan kosong 04.3: ikon biru muda bulat 68 px di kartu putih. */
export default function EmptyState({ icon: Icon, title, children, action, className }: EmptyStateProps) {
    return (
        <section className={cn('flex flex-col items-center gap-[18px] rounded-2xl border border-line bg-white px-6 py-9 text-center shadow-kartu', className)}>
            <span className="inline-flex size-[68px] items-center justify-center rounded-full bg-sky-tint text-sky-val">
                <Icon className="size-7" strokeWidth={3} aria-hidden />
            </span>
            <h2 className="text-xl font-extrabold text-ink">{title}</h2>
            {children && <div className="w-full text-[13px] text-muted-ink">{children}</div>}
            {action && <div className="flex flex-wrap justify-center gap-3">{action}</div>}
        </section>
    );
}
