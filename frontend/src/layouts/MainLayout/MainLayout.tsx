import { Link } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';

import { Toaster } from '@/components/ui/sonner';
import { useFlashToast } from '@/hooks/useFlashToast';
import { NavigationContext, useNavigation } from '@/hooks/useNavigation';
import type { NavGroup } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import CommandSearch from './CommandSearch';
import Sidebar from './Sidebar';

interface MainLayoutProps {
    /** Menu peran dari layout peran. */
    nav: NavGroup[];
    /** Navigasi bawah di layar < 1024 px (BillerLayout, untuk HP di lapangan). */
    bottomNav?: boolean;
    children: ReactNode;
}

/** Navigasi bawah untuk Biller di HP: ikon + label pendek. */
function BottomNav() {
    const { groups } = useNavigation();
    const items = groups.flatMap((g) => g.items);

    return (
        <nav
            aria-label="Menu bawah"
            className="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden"
        >
            <ul className="mx-auto grid max-w-lg" style={{ gridTemplateColumns: `repeat(${items.length}, minmax(0, 1fr))` }}>
                {items.map((item) => (
                    <li key={item.id}>
                        <Link
                            href={item.href}
                            aria-current={item.active ? 'page' : undefined}
                            className={cn(
                                'flex h-16 flex-col items-center justify-center gap-1 text-[10px]',
                                item.active ? 'font-bold text-pln-blue' : 'font-semibold text-muted-ink',
                            )}
                        >
                            <item.icon className="size-5" />
                            <span className="max-w-full truncate px-0.5">{item.label.replace(' Saya', '')}</span>
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}

/**
 * Kerangka semua halaman setelah login (PRD 10.1, layar desain 1280 × 832): sidebar kiri dan
 * konten abu muda di kanan. Judul, subjudul, dan aksi utama ditulis tiap halaman lewat PageHeader.
 * Ctrl K membuka pencarian menu.
 */
export default function MainLayout({ nav, bottomNav = false, children }: MainLayoutProps) {
    useFlashToast();
    const [searchOpen, setSearchOpen] = useState(false);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setSearchOpen((v) => !v);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return (
        <NavigationContext.Provider value={nav}>
            <div className={cn('min-h-dvh bg-canvas lg:flex lg:items-start', bottomNav && 'pb-16 lg:pb-0')}>
                <Sidebar />
                <main className="min-w-0 flex-1 px-4 pb-9 sm:px-7">{children}</main>
            </div>
            {bottomNav && <BottomNav />}
            <CommandSearch open={searchOpen} onOpenChange={setSearchOpen} />
            <Toaster />
        </NavigationContext.Provider>
    );
}
