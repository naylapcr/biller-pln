import { Head, usePage } from '@inertiajs/react';

import PeriodeSelect from '@/components/shared/PeriodeSelect';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import Kartu from '@/components/ui/Kartu';
import Notice from '@/components/ui/Notice';
import PageHeader from '@/components/ui/PageHeader';
import StatCard from '@/components/ui/StatCard';
import StatusChip from '@/components/ui/StatusChip';
import { layoutKoordinator } from '@/layouts/KoordinatorLayout/KoordinatorLayout';
import { formatNumber } from '@/lib/format';
import type { Option, PageProps, PeriodeInfo } from '@/types';

export interface LoginBelumTerpetakan {
    login: string;
    baris: number;
    per_modul: Record<string, number>;
    batch: { id: string; file_name: string; modul: string | null }[];
}

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    statistik: { terdeteksi: number; terpetakan: number; belum: number; belum_baris: number } | null;
    belum: LoginBelumTerpetakan[];
}>;

/** 02.5 Pemetaan Petugas (Koordinator, baca saja). */
export default function PemetaanPetugas() {
    const { periode, periodeOptions, statistik, belum } = usePage<Props>().props;

    const columns: ColumnDef<LoginBelumTerpetakan>[] = [
        { header: 'LOGIN ACMT', cell: ({ row }) => <span className="font-mono font-bold">{row.original.login}</span> },
        { header: 'SUMBER BATCH', cell: ({ row }) => row.original.batch.map((b) => b.file_name).join(', ') },
        { header: 'MODUL', cell: ({ row }) => Object.keys(row.original.per_modul).join(', ') },
        { header: 'JUMLAH DATA', cell: ({ row }) => `${formatNumber(row.original.baris)} baris` },
        { header: 'STATUS', cell: () => <StatusChip label="Belum Terpetakan" tone="waiting" /> },
        { header: 'TINDAK LANJUT', cell: () => 'Laporkan ke Super Admin' },
    ];

    return (
        <>
            <Head title="Pemetaan Petugas" />
            <PageHeader
                title="Pemetaan Petugas ACMT"
                description="Daftar login petugas ACMT dari berkas unggahan yang belum terhubung ke Master 25 Biller"
                actions={
                    <>
                        <PeriodeSelect value={periode?.kode} options={periodeOptions} />
                        <StatusChip titik label={belum.length ? `${belum.length} Login Belum Terpetakan` : 'Semua Terpetakan'} tone={belum.length ? 'waiting' : 'success'} />
                    </>
                }
            />
            <div className="grid gap-4">
                <Notice tone="info">
                    Koordinator dapat memantau login ACMT yang belum terpetakan. Penyimpanan alias (biller_aliases) & hitung ulang dilakukan oleh Super Admin.
                </Notice>
                {statistik && (
                    <div className="grid gap-3.5 sm:grid-cols-3">
                        <StatCard label="Total Login ACMT Terdeteksi" value={`${formatNumber(statistik.terdeteksi)} Login`} caption={`Dari batch committed BLTH ${periode?.kode}`} />
                        <StatCard label="Sudah Terpetakan" value={`${formatNumber(statistik.terpetakan)} Login`} valueClassName="text-success" caption="Cocok dengan master biller atau alias" />
                        <StatCard
                            label="Belum Terpetakan"
                            value={`${formatNumber(statistik.belum)} Login`}
                            valueClassName={statistik.belum ? 'text-amber' : 'text-success'}
                            caption={statistik.belum ? `${formatNumber(statistik.belum_baris)} baris data belum masuk skor` : 'Semua data masuk skor'}
                        />
                    </div>
                )}
                <Kartu judul={`Daftar Login ACMT Belum Terpetakan (BLTH ${periode?.kode ?? '–'})`}>
                    <DataTable columns={columns} data={belum} getRowId={(r) => r.login} empty="Semua login ACMT sudah terpetakan ✓" />
                </Kartu>
            </div>
        </>
    );
}

PemetaanPetugas.layout = layoutKoordinator;
