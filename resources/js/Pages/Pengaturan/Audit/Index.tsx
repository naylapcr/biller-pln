import { usePage } from '@inertiajs/react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import PageHeader from '@/Components/shared/PageHeader';
import Toolbar from '@/Components/shared/Toolbar';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatTanggalWaktu } from '@/lib/format';
import type { PageProps, Paginated } from '@/types';

interface Log {
    id: number;
    waktu: string | null;
    user: string | null;
    aksi: string;
    objek: string;
    sebelum: Record<string, unknown> | null;
    sesudah: Record<string, unknown> | null;
    ip: string | null;
}

type Props = PageProps<{
    logs: Paginated<Log>;
    filters: { dari?: string; sampai?: string; aksi?: string };
    aksiOptions: string[];
}>;

const ringkas = (v: Record<string, unknown> | null) =>
    v
        ? Object.entries(v)
              .map(([k, x]) => `${k}: ${typeof x === 'object' ? JSON.stringify(x) : String(x)}`)
              .join(' · ')
        : '';

export default function AuditIndex() {
    const { logs, filters, aksiOptions } = usePage<Props>().props;
    const f = useFilters({ dari: filters.dari ?? '', sampai: filters.sampai ?? '', aksi: filters.aksi ?? '' });

    const columns: ColumnDef<Log>[] = [
        { header: 'Waktu', meta: { className: 'w-44' }, cell: ({ row }) => <span className="text-[13px]">{formatTanggalWaktu(row.original.waktu)}</span> },
        {
            header: 'Pengguna',
            cell: ({ row: { original: l } }) => (
                <div className="text-[13px]">
                    <p>{l.user ?? 'Sistem'}</p>
                    {l.ip && <p className="font-mono text-xs text-muted-ink">{l.ip}</p>}
                </div>
            ),
        },
        {
            header: 'Aksi',
            cell: ({ row: { original: l } }) => (
                <div>
                    <p className="font-mono text-[13px]">{l.aksi}</p>
                    <p className="text-xs text-muted-ink">{l.objek}</p>
                </div>
            ),
        },
        {
            header: 'Perubahan',
            cell: ({ row: { original: l } }) => (
                <div className="max-w-[520px] font-mono text-[12px] break-words">
                    {l.sebelum && <p className="text-error-ink">− {ringkas(l.sebelum)}</p>}
                    {l.sesudah && <p className="text-success-ink">+ {ringkas(l.sesudah)}</p>}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Audit trail">
            <PageHeader title="Audit trail" description="Login, unggah, commit, rollback, ubah parameter, alias, tutup periode, dan perubahan akun. Catatan tidak bisa diubah atau dihapus." />
            <Toolbar>
                <Input type="date" className="md:w-44" aria-label="Dari tanggal" value={f.values.dari} onChange={(e) => f.set('dari', e.target.value)} />
                <Input type="date" className="md:w-44" aria-label="Sampai tanggal" value={f.values.sampai} onChange={(e) => f.set('sampai', e.target.value)} />
                <NativeSelect className="md:w-52" aria-label="Aksi" value={f.values.aksi} onChange={(e) => f.set('aksi', e.target.value)}>
                    <option value="">Semua aksi</option>
                    {aksiOptions.map((a) => (
                        <option key={a} value={a}>
                            {a}
                        </option>
                    ))}
                </NativeSelect>
            </Toolbar>
            <DataTable columns={columns} data={logs} getRowId={(l) => String(l.id)} empty="Belum ada catatan audit." />
        </AppLayout>
    );
}
