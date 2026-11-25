import { Head, usePage } from '@inertiajs/react';

import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import Kartu from '@/components/ui/Kartu';
import PageHeader from '@/components/ui/PageHeader';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useFilters } from '@/hooks/useFilters';
import { layoutSuperAdmin } from '@/layouts/SuperAdminLayout/SuperAdminLayout';
import { formatTanggal } from '@/lib/format';
import { toneAksi } from '@/lib/status';
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
    user_agent: string | null;
}

type Props = PageProps<{
    logs: Paginated<Log>;
    filters: { dari?: string; sampai?: string; aksi?: string; objek?: string };
    aksiOptions: string[];
    objekOptions: string[];
}>;

const ringkas = (v: Record<string, unknown> | null) =>
    v
        ? Object.entries(v)
              .map(([k, x]) => `${k}: ${typeof x === 'object' ? JSON.stringify(x) : String(x)}`)
              .join(' · ')
        : '';

/** "UploadBatch 01a2108d-…" → "UploadBatch#01a2108d"; id lengkap tampil saat disorot. */
const objek = (o: string) => (o ? o.replace(' ', '#').replace(/-[0-9a-f-]{20,}$/, '') : '–');

/** 03.6 Audit Trail. */
export default function AuditTrail() {
    const { logs, filters, aksiOptions, objekOptions } = usePage<Props>().props;
    const f = useFilters({ dari: filters.dari ?? '', sampai: filters.sampai ?? '', aksi: filters.aksi ?? '', objek: filters.objek ?? '' });
    const kueri = Object.fromEntries(Object.entries(f.values).filter(([, v]) => v));

    const columns: ColumnDef<Log>[] = [
        { header: 'WAKTU (WIB)', cell: ({ row }) => <span className="font-mono text-[11px] whitespace-nowrap">{formatTanggal(row.original.waktu, 'd MMM HH:mm:ss')}</span> },
        { header: 'PENGGUNA', cell: ({ row }) => row.original.user ?? 'Sistem' },
        { header: 'AKSI', cell: ({ row }) => <StatusChip label={row.original.aksi} tone={toneAksi(row.original.aksi)} /> },
        { header: 'OBJEK & ID', cell: ({ row }) => <span className="font-mono text-[11px]" title={row.original.objek}>{objek(row.original.objek)}</span> },
        {
            header: 'SEBELUM → SESUDAH',
            cell: ({ row: { original: l } }) => {
                const isi = [ringkas(l.sebelum), ringkas(l.sesudah)].filter(Boolean).join(' → ');
                return (
                    <span className="line-clamp-2 max-w-[340px] text-[11px] break-words" title={isi}>
                        {isi || '—'}
                    </span>
                );
            },
        },
        {
            header: 'IP',
            cell: ({ row: { original: l } }) => (
                <span className="font-mono text-[11px] text-muted-ink" title={l.user_agent ?? undefined}>
                    {l.ip ?? '–'}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title="Audit Trail" />
            <PageHeader
                title="Audit Trail Sistem (audit_logs)"
                description="Rekam jejak permanen seluruh perubahan akun, parameter skor, pemetaan alias, koreksi absensi, & penutupan periode"
                actions={
                    <>
                        <NativeSelect className="w-44" aria-label="Objek" value={f.values.objek} onChange={(e) => f.set('objek', e.target.value)}>
                            <option value="">Filter: Semua Objek</option>
                            {objekOptions.map((o) => (
                                <option key={o} value={o}>
                                    {o}
                                </option>
                            ))}
                        </NativeSelect>
                        <Button asChild>
                            <a href={route('pengaturan.audit.csv', kueri)}>Ekspor Log CSV</a>
                        </Button>
                    </>
                }
            />
            <Kartu
                judul="Log Aktivitas Terbaru (Supabase audit_logs)"
                aksi={
                    <>
                        <Input type="date" className="h-9 w-36" aria-label="Dari tanggal" value={f.values.dari} onChange={(e) => f.set('dari', e.target.value)} />
                        <Input type="date" className="h-9 w-36" aria-label="Sampai tanggal" value={f.values.sampai} onChange={(e) => f.set('sampai', e.target.value)} />
                        <NativeSelect className="w-44" aria-label="Aksi" value={f.values.aksi} onChange={(e) => f.set('aksi', e.target.value)}>
                            <option value="">Semua aksi</option>
                            {aksiOptions.map((a) => (
                                <option key={a} value={a}>
                                    {a}
                                </option>
                            ))}
                        </NativeSelect>
                    </>
                }
            >
                <DataTable columns={columns} data={logs} getRowId={(l) => String(l.id)} empty="Belum ada catatan audit." minLebar={860} />
            </Kartu>
        </>
    );
}

AuditTrail.layout = layoutSuperAdmin;
