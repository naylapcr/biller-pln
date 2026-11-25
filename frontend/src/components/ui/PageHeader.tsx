import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface PageHeaderProps {
    title: string;
    description?: ReactNode;
    /** Tombol, pemilih periode, dan chip status di kanan. */
    actions?: ReactNode;
    /** Judul merah untuk batch ditolak (04.1). */
    bahaya?: boolean;
}

/** Header putih selebar konten, menempel di atas saat digulir. */
export default function PageHeader({ title, description, actions, bahaya }: PageHeaderProps) {
    return (
        <header className="-mx-4 mb-[22px] flex min-h-20 flex-wrap items-center justify-between gap-x-5 gap-y-3 border-b border-line bg-white px-4 py-[18px] sm:-mx-7 sm:px-7 lg:sticky lg:top-0 lg:z-20">
            <div className="min-w-0 flex-1 basis-64">
                <h1 className={cn('text-xl leading-[25px] font-extrabold', bahaya ? 'text-error-deep' : 'text-ink')}>{title}</h1>
                {description && <p className="mt-[3px] text-xs text-muted-ink">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2.5">{actions}</div>}
        </header>
    );
}
