import {
    ArrowRightLeft,
    Calendar,
    Clock,
    FileChartColumn,
    FileText,
    LayoutGrid,
    type LucideIcon,
    Settings,
    SquareCheckBig,
    SquarePen,
    Star,
    Trophy,
    Upload,
    User,
    UserPlus,
    Users,
} from 'lucide-react';

export interface NavItem {
    /** Kunci unik, juga dipakai untuk navBadges. */
    id: string;
    label: string;
    /** Nama route Ziggy. Bila route belum ada, item disembunyikan. */
    route: string;
    /** Prefix URL untuk menandai item aktif (default: URL route). */
    match?: string;
    /** Aktif hanya bila URL persis sama (contoh /absensi vs /absensi/saya). */
    exact?: boolean;
    icon: LucideIcon;
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

const PROFIL: NavItem = { id: 'profil', label: 'Profil Saya', route: 'profil.edit', icon: User };

/** Menu sidebar per peran, urutan sesuai desain (PRD 10.1). */
export const NAV_BILLER: NavGroup[] = [
    {
        label: '',
        items: [
            { id: 'skor', label: 'Skor Saya', route: 'saya.skor', icon: Star },
            { id: 'absensi', label: 'Absensi Saya', route: 'saya.absensi', icon: SquareCheckBig },
            { id: 'histori', label: 'Histori WO', route: 'saya.histori-wo', icon: FileText },
            { id: 'leaderboard', label: 'Leaderboard', route: 'saya.leaderboard', icon: Trophy },
            PROFIL,
        ],
    },
];

export const NAV_KOORDINATOR: NavGroup[] = [
    {
        label: '',
        items: [
            { id: 'dashboard', label: 'Dashboard', route: 'dashboard', icon: LayoutGrid },
            { id: 'biller', label: 'Direktori Biller', route: 'biller.index', icon: Users },
            { id: 'unggah', label: 'Portal Unggah Data', route: 'unggah.index', icon: Upload },
            { id: 'pemetaan', label: 'Pemetaan Petugas', route: 'pemetaan.index', icon: ArrowRightLeft },
            { id: 'absensi-saya', label: 'Absensi Saya', route: 'absensi.saya', icon: SquareCheckBig },
            { id: 'monitoring', label: 'Monitoring Absensi', route: 'absensi.index', exact: true, icon: Calendar },
            { id: 'laporan', label: 'Laporan', route: 'laporan.index', icon: FileChartColumn },
            PROFIL,
        ],
    },
];

export const NAV_SUPER_ADMIN: NavGroup[] = [
    {
        label: 'MENU SUPER ADMIN',
        items: [
            { id: 'dashboard', label: 'Dashboard Sistem', route: 'dashboard', icon: LayoutGrid },
            { id: 'akun', label: 'Manajemen Akun', route: 'pengaturan.users.index', icon: UserPlus },
            { id: 'billers', label: 'Master 25 Biller', route: 'pengaturan.billers.index', icon: Users },
            { id: 'pemetaan', label: 'Pemetaan Petugas', route: 'pengaturan.pemetaan.index', icon: ArrowRightLeft },
            { id: 'periode', label: 'Periode & Parameter', route: 'pengaturan.periode.index', icon: Settings },
            { id: 'koreksi', label: 'Koreksi Absensi', route: 'pengaturan.koreksi-absensi.index', icon: SquarePen },
            { id: 'audit', label: 'Audit Trail', route: 'pengaturan.audit.index', icon: Clock },
            PROFIL,
        ],
    },
];
