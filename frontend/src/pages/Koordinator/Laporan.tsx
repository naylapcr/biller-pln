import { Head, usePage } from '@inertiajs/react';

import PeriodeSelect from '@/components/shared/PeriodeSelect';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import Kartu from '@/components/ui/Kartu';
import PageHeader from '@/components/ui/PageHeader';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { layoutKoordinator } from '@/layouts/KoordinatorLayout/KoordinatorLayout';
import { formatNumber, formatPercent, formatTanggalWaktu } from '@/lib/format';
import { metaKategori, warnaSkor } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { Kategori, Option, PageProps, PeriodeInfo } from '@/types';

interface BarisLaporan {
    id: number;
    peringkat: number;
    nama: string;
    nip: string | null;
    pascabayar: number | null;
    prabayar: number | null;
    bonus_hari_j: number | null;
    rasio_bu: number | null;
    rasio_ap2t: number | null;
    final_score: number | null;
    kategori: Kategori | null;
}

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    baris: BarisLaporan[];
    dihitungAt: string | null;
}>;

const rasio = (r: number | null) => (r === null ? '–' : formatPercent(r * 100, 2));

/** 02.8 Laporan & Ekspor. */
export default function Laporan() {
    const { periode, periodeOptions, baris, dihitungAt } = usePage<Props>().props;

    const columns: ColumnDef<BarisLaporan>[] = [
        { header: '#', meta: { className: 'w-12' }, cell: ({ row }) => <span className="font-bold">#{row.original.peringkat}</span> },
        { header: 'NAMA BILLER', cell: ({ row }) => <span className="font-semibold text-ink">{row.original.nama}</span> },
        { header: 'PASCA', cell: ({ row }) => formatNumber(row.original.pascabayar, 1) },
        { header: 'PRABAYAR', cell: ({ row }) => formatNumber(row.original.prabayar, 1) },
        { header: 'BONUS J', cell: ({ row }) => `+${formatNumber(row.original.bonus_hari_j ?? 0, 1)}` },
        { header: 'RASIO BU', cell: ({ row }) => rasio(row.original.rasio_bu) },
        { header: 'RASIO AP2T', cell: ({ row }) => rasio(row.original.rasio_ap2t) },
        { header: 'SKOR AKHIR', cell: ({ row }) => <span className={cn('font-bold', warnaSkor(row.original.kategori))}>{formatNumber(row.original.final_score, 1)}</span> },
        { header: 'KATEGORI', cell: ({ row }) => <StatusChip {...metaKategori(row.original.kategori)} /> },
    ];

    return (
        <>
            <Head title="Laporan & Ekspor" />
            <PageHeader
                title="Laporan & Ekspor Peringkat Biller"
                description="Ekspor rekap peringkat dan rincian skor 25 Biller ke format XLSX atau PDF"
                actions={
                    <>
                        <PeriodeSelect value={periode?.kode} options={periodeOptions} />
                        {periode && baris.length > 0 && (
                            <>
                                <Button variant="success" asChild>
                                    <a href={route('laporan.unduh', { kode: periode.kode, format: 'xlsx' })}>Ekspor XLSX</a>
                                </Button>
                                <Button asChild>
                                    <a href={route('laporan.unduh', { kode: periode.kode, format: 'pdf' })}>Ekspor PDF Resmi</a>
                                </Button>
                            </>
                        )}
                    </>
                }
            />
            <div className="grid gap-4">
                {periode && (
                    <Kartu
                        judul={`Status Periode BLTH ${periode.kode}: ${periode.terbuka ? 'Terbuka (Skor Sementara)' : 'Ditutup (Skor Final)'}`}
                        subjudul={`${
                            periode.terbuka
                                ? 'Laporan final terkunci resmi tersedia setelah Super Admin melakukan Tutup Periode.'
                                : 'Periode sudah ditutup; skor final dan datanya dikunci.'
                        }${dihitungAt ? ` Terakhir dihitung ${formatTanggalWaktu(dihitungAt)}.` : ''}`}
                        aksi={<StatusChip label={`Pratinjau ${baris.length} Biller · ${baris.length ? 'Siap Unduh' : 'Belum Ada Skor'}`} tone={baris.length ? 'info' : 'neutral'} />}
                    />
                )}
                <Kartu judul={`Pratinjau Laporan Peringkat ${baris.length || 25} Biller — BLTH ${periode?.kode ?? '–'}`}>
                    <DataTable columns={columns} data={baris} getRowId={(b) => String(b.id)} empty="Belum ada skor di periode ini." />
                </Kartu>
            </div>
        </>
    );
}

Laporan.layout = layoutKoordinator;
