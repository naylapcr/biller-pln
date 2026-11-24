import { usePage } from '@inertiajs/react';
import { FileSpreadsheet, FileText } from 'lucide-react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import PageHeader from '@/Components/shared/PageHeader';
import StatusChip from '@/Components/shared/StatusChip';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import type { PageProps } from '@/types';

interface Row {
    kode: string;
    nama: string;
    final: boolean;
    jumlah_biller: number;
    rata: number;
    total_denda: number;
    dihitung_at: string | null;
}

export default function LaporanIndex() {
    const { periode } = usePage<PageProps<{ periode: Row[] }>>().props;

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
            header: 'Status skor',
            cell: ({ row }) => (row.original.final ? <StatusChip label="Final" tone="success" /> : <StatusChip label="Sementara" tone="waiting" />),
        },
        { header: 'Biller', meta: { className: 'text-right' }, cell: ({ row }) => formatNumber(row.original.jumlah_biller) },
        {
            header: 'Rata-rata skor',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono">{formatNumber(row.original.rata, 1)}</span>,
        },
        {
            header: 'Total denda',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono">{formatNumber(row.original.total_denda, 1)}</span>,
        },
        { header: 'Dihitung', cell: ({ row }) => <span className="text-[13px] text-muted-ink">{formatTanggalWaktu(row.original.dihitung_at)}</span> },
        {
            id: 'unduh',
            header: () => <span className="sr-only">Unduh</span>,
            meta: { className: 'text-right' },
            cell: ({ row: { original: p } }) => (
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" size="sm" asChild>
                        <a href={route('laporan.unduh', { kode: p.kode, format: 'xlsx' })}>
                            <FileSpreadsheet />
                            Excel
                        </a>
                    </Button>
                    <Button variant="secondary" size="sm" asChild>
                        <a href={route('laporan.unduh', { kode: p.kode, format: 'pdf' })}>
                            <FileText />
                            PDF
                        </a>
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Laporan">
            <PageHeader
                title="Laporan peringkat"
                description="Unduh peringkat biller per periode. Excel berisi angka mentah tiap komponen skor; PDF siap cetak."
            />
            <DataTable columns={columns} data={periode} getRowId={(p) => p.kode} empty="Belum ada skor yang dihitung." />
        </AppLayout>
    );
}
