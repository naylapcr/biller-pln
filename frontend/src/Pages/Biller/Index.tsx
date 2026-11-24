import { router, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import PageHeader from '@/Components/shared/PageHeader';
import SearchInput from '@/Components/shared/SearchInput';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import PeriodeSelect from '@/Components/sipermon/PeriodeSelect';
import RincianSkor from '@/Components/sipermon/RincianSkor';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import { KATEGORI } from '@/lib/status';
import type { KasusDenda, Option, PageProps, PeriodeInfo, PoinModul, SkorBiller } from '@/types';

interface Baris {
    id: number;
    nama: string;
    login_acmt: string;
    kode_petugas: string;
    zona_wilayah: string | null;
    is_active: boolean;
    peringkat: number | null;
    final_score: number | null;
    poin_tugas: number | null;
    poin_hari_j: number | null;
    total_denda: number | null;
    kategori: string | null;
    pascabayar: PoinModul | null;
    prabayar: PoinModul | null;
}

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    filters: { cari?: string };
    billers: Baris[];
    detail: { skor: SkorBiller; kasus: KasusDenda[] } | null;
}>;

export default function BillerIndex() {
    const { periode, periodeOptions, filters, billers, detail } = usePage<Props>().props;
    const f = useFilters({ periode: periode?.kode ?? '', cari: filters.cari ?? '' });
    const [dipilih, setDipilih] = useState<number | null>(() => Number(new URLSearchParams(window.location.search).get('detail')) || null);
    const [memuat, setMemuat] = useState(false);

    // Drawer rincian: muat prop "detail" lewat partial reload.
    useEffect(() => {
        if (dipilih === null || detail?.skor.biller.id === dipilih) return;
        router.reload({
            only: ['detail'],
            data: { detail: dipilih },
            onStart: () => setMemuat(true),
            onFinish: () => setMemuat(false),
        });
    }, [dipilih]); // eslint-disable-line react-hooks/exhaustive-deps

    const columns: ColumnDef<Baris>[] = [
        {
            header: '#',
            meta: { className: 'w-14' },
            cell: ({ row }) => <span className="font-mono text-[13px] text-muted-ink">{row.original.peringkat ?? '–'}</span>,
        },
        {
            header: 'Biller',
            cell: ({ row: { original: b } }) => (
                <div className="min-w-0">
                    <p className="font-medium text-ink">
                        {b.nama} {!b.is_active && <span className="text-xs font-normal text-muted-ink">(nonaktif)</span>}
                    </p>
                    <p className="font-mono text-xs text-muted-ink">
                        {b.login_acmt} · {b.kode_petugas}
                    </p>
                </div>
            ),
        },
        {
            header: 'Pasca terbaca',
            meta: { className: 'text-right' },
            cell: ({ row: { original: b } }) => (
                <div className="font-mono text-[13px]">
                    <p>{formatNumber(b.pascabayar?.terbaca ?? null)}</p>
                    {!!b.pascabayar?.kasus_bu && <p className="text-xs text-error-ink">{formatNumber(b.pascabayar.kasus_bu)} kasus BU</p>}
                </div>
            ),
        },
        {
            header: 'Prabayar',
            meta: { className: 'text-right' },
            cell: ({ row: { original: b } }) => <span className="font-mono text-[13px]">{formatNumber(b.prabayar?.pemeriksaan ?? null)}</span>,
        },
        {
            header: 'Poin tugas',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono text-[13px]">{formatNumber(row.original.poin_tugas, 1)}</span>,
        },
        {
            header: 'Bonus J',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono text-[13px] text-success-ink">{formatNumber(row.original.poin_hari_j, 1)}</span>,
        },
        {
            header: 'Denda',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono text-[13px] text-error-ink">{formatNumber(row.original.total_denda, 1)}</span>,
        },
        {
            header: 'Skor akhir',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono font-semibold">{formatNumber(row.original.final_score, 1)}</span>,
        },
        {
            header: 'Kategori',
            cell: ({ row }) => {
                const k = row.original.kategori ? KATEGORI[row.original.kategori] : null;
                return k ? <StatusChip {...k} /> : <span className="text-xs text-muted-ink">Belum ada data</span>;
            },
        },
    ];

    const tutup = () => {
        setDipilih(null);
        const url = new URL(window.location.href);
        url.searchParams.delete('detail');
        window.history.replaceState(window.history.state, '', url);
    };
    const detailSiap = detail && detail.skor.biller.id === dipilih;

    return (
        <AppLayout title="Kinerja biller">
            <PageHeader
                title="Kinerja biller"
                description={periode ? `${billers.length} biller · ${periode.nama} (BLTH ${periode.kode}). Klik baris untuk melihat rincian poin dan denda.` : undefined}
            />

            <Toolbar>
                <PeriodeSelect value={periode?.kode} options={periodeOptions} params={{ cari: f.values.cari }} />
                <SearchInput className="md:w-72" value={f.values.cari} onChange={(v) => f.set('cari', v, { debounce: true })} placeholder="Cari nama, login, atau kode petugas" />
            </Toolbar>

            <DataTable
                columns={columns}
                data={billers}
                getRowId={(b) => String(b.id)}
                rowClassName={() => 'cursor-pointer'}
                onRowClick={(b) => setDipilih(b.id)}
                empty="Tidak ada biller yang cocok."
            />

            <Dialog open={dipilih !== null} onOpenChange={(v) => !v && tutup()}>
                <DialogContent className="top-0 right-0 left-auto h-dvh w-full max-w-2xl translate-x-0 translate-y-0 content-start overflow-y-auto rounded-none sm:rounded-l-14">
                    <DialogTitle>{detailSiap ? detail.skor.biller.nama : 'Rincian skor'}</DialogTitle>
                    <DialogDescription>
                        {detailSiap ? `${detail.skor.biller.login_acmt} · kode ${detail.skor.biller.kode_petugas} · BLTH ${detail.skor.periode}` : 'Memuat…'}
                    </DialogDescription>
                    {memuat || !detailSiap ? (
                        <div className="flex items-center gap-2 py-10 text-sm text-muted-ink">
                            <LoaderCircle className="size-4 animate-spin" /> Memuat rincian…
                        </div>
                    ) : (
                        <RincianSkor skor={detail.skor} kasus={detail.kasus} />
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
