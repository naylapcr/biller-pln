import type { ReactNode } from 'react';

import MainLayout from '@/layouts/MainLayout/MainLayout';
import { NAV_KOORDINATOR } from '@/lib/navigation';

/** Layout Koordinator Admin: 8 menu dengan badge Pemetaan Petugas. */
export default function KoordinatorLayout({ children }: { children: ReactNode }) {
    return <MainLayout nav={NAV_KOORDINATOR}>{children}</MainLayout>;
}

export const layoutKoordinator = (page: ReactNode) => <KoordinatorLayout>{page}</KoordinatorLayout>;
