import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import BillerLayout from '@/layouts/BillerLayout/BillerLayout';
import KoordinatorLayout from '@/layouts/KoordinatorLayout/KoordinatorLayout';
import SuperAdminLayout from '@/layouts/SuperAdminLayout/SuperAdminLayout';
import type { SharedProps } from '@/types';

/** Pilih layout peran dari auth.user.role, untuk halaman yang dipakai semua peran (Umum/Profil). */
function RoleLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage<SharedProps>().props;

    switch (auth.user?.role) {
        case 'biller':
            return <BillerLayout>{children}</BillerLayout>;
        case 'super_admin':
            return <SuperAdminLayout>{children}</SuperAdminLayout>;
        case 'admin':
            return <KoordinatorLayout>{children}</KoordinatorLayout>;
        default:
            return <>{children}</>;
    }
}

export const layoutByRole = (page: ReactNode) => <RoleLayout>{page}</RoleLayout>;
