import { usePage } from '@inertiajs/react';
import { createContext, useContext, useMemo } from 'react';

import type { NavGroup, NavItem } from '@/lib/navigation';
import { hasRoute } from '@/lib/utils';
import type { PageProps } from '@/types';

export interface ResolvedNavItem extends NavItem {
    href: string;
    path: string;
    active: boolean;
    badge: number;
}

export interface ResolvedNavGroup extends Omit<NavGroup, 'items'> {
    items: ResolvedNavItem[];
}

/** Menu peran yang sedang aktif, diisi oleh layout peran (BillerLayout, KoordinatorLayout, SuperAdminLayout). */
export const NavigationContext = createContext<NavGroup[]>([]);

function pathOf(href: string): string {
    try {
        return new URL(href, window.location.origin).pathname;
    } catch {
        return href;
    }
}

/**
 * Menu sesuai layout peran. Item yang route-nya tidak ada disembunyikan.
 */
export function useNavigation() {
    const nav = useContext(NavigationContext);
    const page = usePage<PageProps>();
    const { navBadges } = page.props;
    const currentPath = page.url.split('?')[0];

    return useMemo(() => {
        const groups: ResolvedNavGroup[] = nav
            .map((group) => ({
                ...group,
                items: group.items
                    .filter((item) => hasRoute(item.route))
                    .map((item) => {
                        const href = route(item.route);
                        const path = item.match ?? pathOf(href);
                        return {
                            ...item,
                            href,
                            path,
                            active: currentPath === path || (!item.exact && currentPath.startsWith(`${path}/`)),
                            badge: navBadges?.[item.id] ?? 0,
                        };
                    }),
            }))
            .filter((group) => group.items.length > 0);

        const activeItem = groups.flatMap((g) => g.items).find((i) => i.active);

        return { groups, activeItem };
    }, [nav, navBadges, currentPath]);
}
