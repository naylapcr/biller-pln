import type { ReactNode } from 'react';

import MainLayout from '@/layouts/MainLayout/MainLayout';
import { NAV_SUPER_ADMIN } from '@/lib/navigation';

/** Layout Super Admin: 8 menu dalam grup "MENU SUPER ADMIN". */
export default function SuperAdminLayout({ children }: { children: ReactNode }) {
    return <MainLayout nav={NAV_SUPER_ADMIN}>{children}</MainLayout>;
}

export const layoutSuperAdmin = (page: ReactNode) => <SuperAdminLayout>{page}</SuperAdminLayout>;
