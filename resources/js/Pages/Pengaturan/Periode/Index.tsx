import { Link, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import StatusChip from '@/Components/shared/StatusChip';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import type { PageProps } from '@/types';

interface Row {
    kode: string;
    nama: string;
    status: 'terbuka' | 'ditutup';
    terbuka: boolean;
    ditutup_at: string | null;
    penutup: string | null;
    batch_committed: string[];
    jumlah_skor: number;
    parameter_kosong: string[];
}

export default function PeriodeIndex() {
    const { periode } = usePage<PageProps<{ periode: Row[] }>>().props;
    const [buka, setBuka] = useState(false);

    const columns: ColumnDef<Row>[] = [
        {
            header: 'Periode',
            cell: ({ row: { original: p } }) => (
                <div>
                    <p className="font-medium">{p.nama}</p>
                    <p className="font-mono text-xs text-muted-ink">BLTH {p.kode}</p>
                </div>
            ),
        },
        {
            header: 'Status',
            cell: ({ row: { original: p } }) => (
                <div>
                    {p.terbuka ? <StatusChip label="Terbuka" tone="info" /> : <StatusChip label="Ditutup" tone="success" />}
                    {p.ditutup_at && <p className="mt-1 text-xs text-muted-ink">{formatTanggalWaktu(p.ditutup_at)} · {p.penutup}</p>}
                </div>
            ),
        },
        { header: 'Data committed', cell: ({ row }) => <span className="text-[13px]">{row.original.batch_committed.join(', ') || '–'}</span> },
        { header: 'Biller berskor', meta: { className: 'text-right' }, cell: ({ row }) => formatNumber(row.original.jumlah_skor) },
        {
            header: 'Parameter',
            cell: ({ row: { original: p } }) =>
                p.parameter_kosong.length ? (
                    <StatusChip label={`${p.parameter_kosong.length} wajib kosong`} tone="waiting" />
                ) : (
                    <StatusChip label="Lengkap" tone="success" />
                ),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => (
                <Button variant="secondary" size="sm" asChild>
                    <Link href={route('pengaturan.periode.show', row.original.kode)}>Kelola</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Periode & parameter">
            <PageHeader
                title="Periode & parameter"
                description="Periode penilaian mengikuti BLTH. Parameter skor disimpan per periode; periode baru menyalin dari periode sebelumnya."
                actions={
                    <Button onClick={() => setBuka(true)}>
                        <Plus />
                        Buka periode
                    </Button>
                }
            />
            <DataTable columns={columns} data={periode} getRowId={(p) => p.kode} empty="Belum ada periode." />
            {buka && <BukaPeriode onClose={() => setBuka(false)} />}
        </AppLayout>
    );
}

function BukaPeriode({ onClose }: { onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm({ kode: '' });

    return (
        <FormDialog
            open
            onOpenChange={(v) => !v && onClose()}
            title="Buka periode"
            description="Kode periode = BLTH di berkas ACMT, bukan bulan tanggal baca. Contoh: BLTH 202611 dibaca 23 Okt sampai 1 Nov 2026."
            submitLabel="Buka periode"
            processing={processing}
            onSubmit={() => post(route('pengaturan.periode.store'), { onSuccess: onClose })}
        >
            <FormField id="kode" label="BLTH" required error={errors.kode} hint="6 digit: tahun dan bulan, contoh 202612.">
                <Input id="kode" inputMode="numeric" maxLength={6} className="font-mono" value={data.kode} onChange={(e) => setData('kode', e.target.value.replace(/\D/g, ''))} />
            </FormField>
        </FormDialog>
    );
}
