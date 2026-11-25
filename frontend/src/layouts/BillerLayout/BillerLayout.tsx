import type { ReactNode } from 'react';

import MainLayout from '@/layouts/MainLayout/MainLayout';
import { NAV_BILLER } from '@/lib/navigation';

/** Layout Biller: 5 menu (Skor Saya, Absensi Saya, Histori WO, Leaderboard, Profil Saya), navigasi bawah di HP. */
export default function BillerLayout({ children }: { children: ReactNode }) {
    return (
        <MainLayout nav={NAV_BILLER} bottomNav>
            {children}
        </MainLayout>
    );
}

export const layoutBiller = (page: ReactNode) => <BillerLayout>{page}</BillerLayout>;
