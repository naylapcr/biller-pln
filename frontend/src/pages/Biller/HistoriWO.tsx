import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';

import PeriodeSelect from '@/components/shared/PeriodeSelect';
import TabelHistoriPasca from '@/components/shared/TabelHistoriPasca';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import Kartu from '@/components/ui/Kartu';
import PageHeader from '@/components/ui/PageHeader';
import SegmentedTabs from '@/components/ui/SegmentedTabs';
import StatCard from '@/components/ui/StatCard';
import StatusChip from '@/components/ui/StatusChip';
import { layoutBiller } from '@/layouts/BillerLayout/BillerLayout';
import { formatNumber, formatPercent, formatTanggal } from '@/lib/format';
import type { StatusMeta } from '@/lib/status';
import type { HistoriPasca, HistoriPra, Option, PageProps, PeriodeInfo } from '@/types';

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    histori: {
        pascabayar: HistoriPasca[];
        prabayar: HistoriPra[];
        jadwal_prabayar: [string, string];
        bonus_hari_j: number;
        plg_per_jam: number | null;
        standar: number;
    } | null;
}>;

/** Koordinat dan jadwal ditampilkan bersama: satu chip untuk hal yang paling perlu diperhatikan. */
function statusPra(r: HistoriPra): StatusMeta {
    if (!r.koordinat_valid) return { label: 'Koord. Invalid', tone: 'waiting' };
    if (r.status === 'di_luar_jadwal') return { label: 'Di Luar Jadwal', tone: 'waiting' };
    return { label: 'Valid', tone: 'success' };
}

/** 01.3 Histori WO: pascabayar per hari baca dan RBM, prabayar per tanggal. */
export default function HistoriWO() {
    const { periode, periodeOptions, histori } = usePage<Props>().props;
    const [hari, setHari] = useState<'semua' | 'ag' | 'j'>('semua');
    const pasca = histori?.pascabayar ?? [];
    const pra = histori?.prabayar ?? [];
    const jumlah = (k: 'jml_plg' | 'terbaca' | 'foto') => pasca.reduce((n, r) => n + r[k], 0);
    const [plg, terbaca, foto] = [jumlah('jml_plg'), jumlah('terbaca'), jumlah('foto')];
    const tampil = pasca.filter((r) => hari === 'semua' || (hari === 'j') === (r.hari_baca === 'J'));

    const kolomPra: ColumnDef<HistoriPra>[] = [
        { header: 'TGL BACA', cell: ({ row }) => <span className="whitespace-nowrap">{formatTanggal(row.original.tgl_baca, 'd MMM yyyy')}</span> },
        { header: 'KODE RBM', cell: ({ row }) => <span className="font-mono">{row.original.kdrbm ?? '–'}</span> },
        { header: 'IDPEL', cell: ({ row }) => <span className="font-mono">{row.original.idpel}</span> },
        { header: 'NO. METER', cell: ({ row }) => <span className="font-mono">{row.original.no_meter ?? '–'}</span> },
        {
            header: 'KODE BACA',
            cell: ({ row: { original: r } }) =>
                r.status === 'kode_tidak_normal' ? <StatusChip label={`Tidak Normal (${r.kd_baca ?? '–'})`} tone="danger" /> : <StatusChip label="Normal" tone="success" />,
        },
        {
            header: 'KOORDINAT',
            cell: ({ row: { original: r } }) =>
                r.latitude ? (
                    <span className="font-mono whitespace-nowrap">
                        {r.latitude.toFixed(4)}, {(r.longitude ?? 0).toFixed(4)}
                    </span>
                ) : (
                    'Kosong / 0,0'
                ),
        },
        { header: 'STATUS', cell: ({ row }) => <StatusChip {...statusPra(row.original)} /> },
    ];

    return (
        <>
            <Head title="Histori WO" />
            <PageHeader
                title="Histori Work Order (WO) Saya"
                description="Data pembacaan dari berkas ACMT (Pascabayar per Hari Baca & RBM · Prabayar per Tanggal)"
                actions={<PeriodeSelect value={periode?.kode} options={periodeOptions} />}
            />
            <div className="grid gap-[18px]">
                <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Total WO Pascabayar" value={`${formatNumber(plg)} Plg`} caption={`${formatNumber(terbaca)} Terbaca · ${formatNumber(plg - terbaca)} Belum Baca`} />
                    <StatCard
                        label="Eviden Foto Valid"
                        value={`${formatNumber(foto)} Foto`}
                        valueClassName="text-success"
                        caption={`${formatPercent(terbaca > 0 ? (foto / terbaca) * 100 : null)} Rasio Eviden`}
                    />
                    <StatCard
                        label="Kecepatan Baca ACMT"
                        value={`${formatNumber(histori?.plg_per_jam, 1)} / Jam`}
                        valueClassName="text-pln-blue"
                        caption={`Standar: ${formatNumber(histori?.standar ?? 33.3, 1)} (TGL MULAI–SELESAI)`}
                    />
                    <StatCard
                        label="WO Prabayar"
                        value={`${formatNumber(pra.length)} Meter`}
                        valueClassName="text-violet"
                        caption={`${formatNumber(pra.filter((r) => r.status !== 'kode_tidak_normal').length)} Normal · ${formatNumber(pra.filter((r) => r.koordinat_valid).length)} Koordinat Valid`}
                    />
                </div>

                <Kartu
                    judul="Histori Pascabayar — per Hari Baca (A–G, J) & RBM"
                    aksi={
                        <SegmentedTabs
                            label="Hari baca"
                            value={hari}
                            onChange={setHari}
                            items={[
                                { value: 'semua', label: 'Semua Hari (A–J)' },
                                { value: 'ag', label: 'Hari A–G' },
                                { value: 'j', label: 'Khusus Hari J' },
                            ]}
                        />
                    }
                >
                    <TabelHistoriPasca data={tampil} bonusHariJ={histori?.bonus_hari_j} />
                </Kartu>

                <Kartu
                    judul={`Histori Prabayar — per Tanggal${histori ? ` (Jadwal: ${formatTanggal(histori.jadwal_prabayar[0], 'd MMM')} – ${formatTanggal(histori.jadwal_prabayar[1], 'd MMM')})` : ''}`}
                    aksi={<StatusChip label="Monitoring Fisik Meter Prabayar" tone="success" />}
                >
                    <DataTable columns={kolomPra} data={pra} getRowId={(r) => String(r.id)} empty="Belum ada data prabayar." />
                </Kartu>
            </div>
        </>
    );
}

HistoriWO.layout = layoutBiller;
