import { Link, router, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import petir from '@/assets/images/petir.svg';
import StatusChip from '@/components/ui/StatusChip';
import UserAvatar from '@/components/ui/UserAvatar';
import { useNavigation, type ResolvedNavGroup } from '@/hooks/useNavigation';
import type { StatusTone } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { PageProps, Role } from '@/types';
import KartuStatus from './KartuStatus';

/** Warna identitas peran di sidebar: avatar dan chip. */
const PERAN: Record<Role, { label: string; tone: StatusTone; avatar: string }> = {
    biller: { label: 'Biller Lapangan', tone: 'success', avatar: 'bg-success text-white' },
    admin: { label: 'Koordinator Admin', tone: 'info', avatar: 'bg-pln-blue-dark text-white' },
    super_admin: { label: 'Super Admin', tone: 'waiting', avatar: 'bg-ink text-pln-yellow' },
};

function Brand() {
    const { auth } = usePage<PageProps>().props;

    return (
        <Link href={route('home')} className="flex min-w-0 items-center gap-3 px-1">
            <span className={cn('inline-flex size-10 shrink-0 items-center justify-center rounded-xl', auth.user.role === 'super_admin' ? 'bg-ink' : 'bg-pln-blue')}>
                <img src={petir} alt="" width={20} height={20} />
            </span>
            <span className="min-w-0">
                <span className="block truncate text-[10px] leading-[13px] font-bold text-sky-ink">ULP KOTA BARAT</span>
                <span className="block truncate text-[17px] leading-[21px] font-extrabold text-ink">Kinerja Biller</span>
            </span>
        </Link>
    );
}

/** Kartu pengguna: inisial, nama, NIP, dan chip peran. */
function UserCard() {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;
    const peran = PERAN[user.role];

    return (
        <Link href={route('profil.edit')} className="flex items-center gap-3 rounded-2xl bg-white/85 p-3.5 hover:bg-white">
            <UserAvatar name={user.nama} size={40} className={peran.avatar} />
            <span className="grid min-w-0 flex-1 justify-items-start gap-0.5">
                <span className="block max-w-full truncate text-[13px] leading-4 font-bold text-ink">{user.nama}</span>
                <span className="block max-w-full truncate font-mono text-[11px] leading-[13px] text-slate-mid">NIP: {user.biller?.nip ?? user.username_nip}</span>
                <StatusChip label={peran.label} tone={peran.tone} titik />
            </span>
        </Link>
    );
}

function NavGroups({ groups }: { groups: ResolvedNavGroup[] }) {
    return (
        <nav aria-label="Menu utama" className="flex flex-col gap-3">
            {groups.map((group, i) => (
                <div key={group.label || i}>
                    {group.label && <p className="mb-1.5 px-3 text-[10px] font-bold text-[#92400e] uppercase">{group.label}</p>}
                    <ul className="flex flex-col gap-[3px]">
                        {group.items.map((item) => (
                            <li key={item.id}>
                                <Link
                                    href={item.href}
                                    aria-current={item.active ? 'page' : undefined}
                                    className={cn(
                                        'flex h-11 items-center gap-2.5 rounded-l-[14px] pr-3 pl-4 text-[13px] transition-colors lg:h-[38px]',
                                        item.active ? 'bg-white font-bold text-pln-blue' : 'font-semibold text-ink-soft hover:bg-white/45',
                                    )}
                                >
                                    <item.icon className={cn('size-4 shrink-0', item.active ? 'text-pln-blue' : 'text-slate-ink')} />
                                    <span className="min-w-0 flex-1 truncate">{item.label}</span>
                                    {item.badge > 0 && (
                                        <span className="inline-flex h-[22px] items-center rounded-full bg-pln-yellow-light px-2.5 text-[11px] font-semibold text-pln-yellow-ink">
                                            {item.badge > 99 ? '99+' : item.badge}
                                            <span className="sr-only"> belum terpetakan</span>
                                        </span>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </nav>
    );
}

function TombolKeluar() {
    return (
        <button
            type="button"
            onClick={() => router.post(route('logout'))}
            className="h-11 w-full shrink-0 rounded-10 border border-sky-val/25 bg-white/55 text-xs font-semibold text-ink hover:bg-white/80 lg:h-9"
        >
            Keluar (Sesi 8 Jam)
        </button>
    );
}

/**
 * Sidebar biru muda 288 px setinggi layar (≥ 1024 px): logo, kartu pengguna, menu peran, kartu
 * status, tombol keluar. Menu aktif putih menyambung ke tepi kanan. Di bawah 1024 px sidebar
 * pindah ke atas: baris logo + tombol Menu.
 */
export default function Sidebar() {
    const { groups } = useNavigation();
    const [open, setOpen] = useState(false);

    useEffect(() => router.on('navigate', () => setOpen(false)), []);

    return (
        <>
            <aside className="sticky top-0 hidden h-dvh w-72 shrink-0 flex-col gap-3 bg-sky-side px-4 pt-[22px] pb-5 lg:flex">
                <Brand />
                <UserCard />
                <div className="-mr-4 min-h-0 flex-1 overflow-y-auto">
                    <NavGroups groups={groups} />
                </div>
                <KartuStatus />
                <TombolKeluar />
            </aside>

            <header className="bg-sky-side px-4 py-3 lg:hidden">
                <div className="flex items-center justify-between gap-3">
                    <Brand />
                    <button
                        type="button"
                        onClick={() => setOpen((v) => !v)}
                        aria-expanded={open}
                        aria-controls="menu-seluler"
                        className="inline-flex h-11 items-center gap-2 rounded-10 bg-white/85 px-3.5 text-[13px] font-semibold text-ink"
                    >
                        {open ? <X className="size-4" /> : <Menu className="size-4" />}
                        Menu
                    </button>
                </div>
                {open && (
                    <div id="menu-seluler" className="mt-3 flex flex-col gap-3 pb-1">
                        <UserCard />
                        <div className="-mr-4">
                            <NavGroups groups={groups} />
                        </div>
                        <KartuStatus />
                        <TombolKeluar />
                    </div>
                )}
            </header>
        </>
    );
}
