import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

import DrawerProfilBiller, { type DetailBiller } from '@/components/koordinator/DrawerProfilBiller';
import PeriodeSelect from '@/components/shared/PeriodeSelect';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import PageHeader from '@/components/ui/PageHeader';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/hooks/useFilters';
import { layoutKoordinator } from '@/layouts/KoordinatorLayout/KoordinatorLayout';
import { formatNumber } from '@/lib/format';
import { metaKategori, warnaSkor } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { Kategori, Option, PageProps, PeriodeInfo } from '@/types';

interface BarisBiller {
    id: number;
    nama: string;
    nip: string | null;
    login_acmt: string;
    kode_petugas: string;
    zona_wilayah: string | null;
    is_active: boolean;
    peringkat: number | null;
    pascabayar: number | null;
    prabayar: number | null;
    bonus_hari_j: number | null;
    final_score: number | null;
    kategori: Kategori | null;
}

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    filters: { cari?: string };
    billers: BarisBiller[];
    detail: DetailBiller | null;
}>;

/** 02.2 Direktori Biller + drawer profil. */
export default function DirektoriBiller() {
    const { periode, periodeOptions, filters, billers, detail } = usePage<Props>().props;
    const cari = useFilters({ periode: periode?.kode, cari: filters.cari ?? '' });
    const awal = new URLSearchParams(window.location.search).get('detail');
    const [dipilih, setDipilih] = useState<number | null>(awal ? Number(awal) : null);
    const tutup = useCallback(() => setDipilih(null), []);

    useEffect(() => {
        if (dipilih !== null && (!detail || detail.skor.biller.id !== dipilih)) {
            router.reload({ only: ['detail'], data: { detail: dipilih } });
        }
    }, [dipilih]); // eslint-disable-line react-hooks/exhaustive-deps

    const columns: ColumnDef<BarisBiller>[] = [
        {
            header: '#',
            meta: { className: 'w-12' },
            cell: ({ row }) => <span className={cn('font-bold', row.original.id === dipilih ? 'text-pln-blue' : 'text-ink')}>{row.original.peringkat ? `#${row.original.peringkat}` : '–'}</span>,
        },
        {
            header: 'NAMA BILLER & NIP',
            cell: ({ row: { original: b } }) => (
                <div>
                    <p className={cn('text-ink', b.id === dipilih ? 'font-bold' : 'font-medium')}>{b.nama}</p>
                    <p className="font-mono text-[10px] text-muted-ink">{b.nip ?? b.login_acmt}</p>
                </div>
            ),
        },
        { header: 'PASCA', cell: ({ row }) => formatNumber(row.original.pascabayar, 1) },
        { header: 'PRABAYAR', cell: ({ row }) => formatNumber(row.original.prabayar, 1) },
        { header: 'BONUS J', cell: ({ row }) => (row.original.bonus_hari_j === null ? '–' : `+${formatNumber(row.original.bonus_hari_j, 1)}`) },
        { header: 'SKOR', cell: ({ row }) => <span className={cn('font-bold', warnaSkor(row.original.kategori))}>{formatNumber(row.original.final_score, 1)}</span> },
        { header: 'KATEGORI', cell: ({ row }) => <StatusChip {...metaKategori(row.original.kategori)} /> },
    ];

    return (
        <>
            <Head title="Direktori Biller" />
            <PageHeader
                title={`Direktori ${billers.length || 25} Biller & Drawer Profil`}
                description="Daftar peringkat seluruh biller · Klik baris biller untuk meninjau rincian modul & kesalahan"
                actions={
                    <>
                        <PeriodeSelect value={periode?.kode} options={periodeOptions} params={{ cari: filters.cari }} />
                        <Button asChild>
                            <Link href={route('laporan.index', { periode: periode?.kode })}>Ekspor Laporan XLSX/PDF</Link>
                        </Button>
                    </>
                }
            />
            <div className={cn('grid items-start gap-[18px]', dipilih !== null && 'xl:grid-cols-[1fr_360px]')}>
                <div className="grid gap-3">
                    <label className="flex h-11 items-center gap-3 rounded-xl border border-line-strong bg-white px-3.5 focus-within:border-pln-blue focus-within:ring-3 focus-within:ring-pln-blue/20">
                        <input
                            type="search"
                            aria-label="Cari biller"
                            className="min-w-0 flex-1 bg-transparent text-xs font-medium text-ink outline-none placeholder:text-faint"
                            placeholder="Cari nama biller, NIP, atau kode RBM..."
                            value={cari.values.cari ?? ''}
                            onChange={(e) => cari.set('cari', e.target.value, { debounce: true })}
                        />
                        <StatusChip label={`${billers.length} Biller`} tone="info" />
                    </label>
                    <DataTable
                        columns={columns}
                        data={billers}
                        getRowId={(b) => String(b.id)}
                        onRowClick={(b) => setDipilih(b.id)}
                        rowClassName={(b) => cn('cursor-pointer', b.id === dipilih && 'bg-pln-blue-row hover:bg-pln-blue-row')}
                        empty="Tidak ada biller yang cocok."
                        minLebar={540}
                    />
                </div>
                {dipilih !== null && <DrawerProfilBiller onClose={tutup} detail={detail && detail.skor.biller.id === dipilih ? detail : null} />}
            </div>
        </>
    );
}

DirektoriBiller.layout = layoutKoordinator;
