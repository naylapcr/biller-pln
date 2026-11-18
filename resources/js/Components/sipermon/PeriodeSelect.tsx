import { router } from '@inertiajs/react';

import { NativeSelect } from '@/Components/ui/native-select';
import type { Option } from '@/types';

/** Pilihan periode (BLTH). Mengganti ?periode= lalu memuat ulang halaman. */
export default function PeriodeSelect({
    value,
    options,
    params = {},
    className = 'w-52',
}: {
    value: string | undefined;
    options: Option[];
    params?: Record<string, string | undefined>;
    className?: string;
}) {
    if (options.length === 0) return null;

    return (
        <NativeSelect
            className={className}
            aria-label="Periode"
            value={value ?? ''}
            onChange={(e) => {
                const query = Object.fromEntries(Object.entries({ ...params, periode: e.target.value }).filter(([, v]) => v));
                router.get(window.location.pathname, query, { preserveScroll: true });
            }}
        >
            {options.map((o) => (
                <option key={o.value} value={o.value}>
                    {o.label}
                </option>
            ))}
        </NativeSelect>
    );
}
