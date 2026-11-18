import type { Role } from '@/types';
import {
    CalendarCheck,
    CalendarRange,
    FileChartColumn,
    History,
    LayoutDashboard,
    Link2,
    type LucideIcon,
    TrendingUp,
    Upload,
    UserRound,
    Users,
    UsersRound,
} from 'lucide-react';

export interface NavItem {
    /** Kunci unik, juga dipakai untuk navBadges. */
    id: string;
    label: string;
    /** Nama route Ziggy. Bila route belum ada, item disembunyikan. */
    route: string;
    /** Prefix URL untuk menandai item aktif (default: URL route). */
    match?: string;
    icon: LucideIcon;
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

const PROFIL: NavItem = { id: 'profil', label: 'Profil saya', route: 'profil.edit', icon: UserRound };

const PANTAU: NavItem[] = [
    { id: 'dashboard', label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
    { id: 'biller', label: 'Kinerja biller', route: 'biller.index', icon: Users },
    { id: 'laporan', label: 'Laporan', route: 'laporan.index', icon: FileChartColumn },
];

const DATA: NavItem[] = [
    { id: 'unggah', label: 'Unggah data', route: 'unggah.index', icon: Upload },
    { id: 'pemetaan', label: 'Pemetaan petugas', route: 'pemetaan.index', icon: Link2 },
    { id: 'absensi', label: 'Absensi biller', route: 'absensi.index', icon: CalendarCheck },
];

export const NAVIGATION: Record<Role, NavGroup[]> = {
    biller: [
        {
            label: 'Kinerja',
            items: [
                { id: 'skor', label: 'Skor saya', route: 'saya.skor', icon: TrendingUp },
                { id: 'absensi', label: 'Absensi', route: 'saya.absensi', icon: CalendarCheck },
            ],
        },
        { label: 'Akun', items: [PROFIL] },
    ],
    admin: [
        { label: 'Pantau', items: PANTAU },
        { label: 'Data ACMT/AP2T', items: DATA },
        { label: 'Akun', items: [PROFIL] },
    ],
    super_admin: [
        { label: 'Pantau', items: PANTAU },
        { label: 'Data ACMT/AP2T', items: DATA },
        {
            label: 'Pengaturan',
            items: [
                { id: 'periode', label: 'Periode & parameter', route: 'pengaturan.periode.index', icon: CalendarRange },
                { id: 'billers', label: 'Master biller', route: 'pengaturan.billers.index', icon: UsersRound },
                { id: 'pengguna', label: 'Pengguna', route: 'pengaturan.users.index', icon: UserRound },
                { id: 'audit', label: 'Audit trail', route: 'pengaturan.audit.index', icon: History },
            ],
        },
        { label: 'Akun', items: [PROFIL] },
    ],
};
