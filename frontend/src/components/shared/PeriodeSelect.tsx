import { router } from '@inertiajs/react';

import { NativeSelect } from '@/components/ui/native-select';
import type { Option } from '@/types';

/** Pilihan periode (BLTH). Mengganti ?periode= lalu memuat ulang halaman. */
export default function PeriodeSelect({
    value,
    options,
    params = {},
    className = 'w-60',
    awalan = 'Periode: ',
}: {
    value: string | undefined;
    options: Option[];
    params?: Record<string, string | undefined>;
    className?: string;
    awalan?: string;
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
                    {awalan}BLTH {o.value} ({o.label.replace(/^(\w{3})\w*/, '$1').replace(' (ditutup)', ' · ditutup')})
                </option>
            ))}
        </NativeSelect>
    );
}
