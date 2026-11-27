import { Head, router, usePage } from '@inertiajs/react';

import TabelPantauanHarian, { type BarisHarian } from '@/components/shared/TabelPantauanHarian';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import Kartu from '@/components/ui/Kartu';
import Notice from '@/components/ui/Notice';
import PageHeader from '@/components/ui/PageHeader';
import StatCard from '@/components/ui/StatCard';
import StatusChip from '@/components/ui/StatusChip';
import { Input } from '@/components/ui/input';
import { layoutKoordinator } from '@/layouts/KoordinatorLayout/KoordinatorLayout';
import { formatNumber, formatTanggal } from '@/lib/format';
import type { OrangAbsensi, PageProps, RingkasanAbsensi } from '@/types';

type BarisRekap = OrangAbsensi & RingkasanAbsensi;

type Props = PageProps<{
    tanggal: string;
    hariIni: string;
    bulan: string;
    harian: BarisHarian[];
    rekap: BarisRekap[];
}>;

/** 02.7 Monitoring Absensi (Koordinator, baca saja). */
export default function MonitoringAbsensi() {
    const { tanggal, hariIni, bulan, harian, rekap } = usePage<Props>().props;
    const muat = (q: Record<string, string>) => router.get(route('absensi.index'), { tanggal, bulan, ...q }, { preserveScroll: true, preserveState: true });
    const n = (...s: BarisHarian['status'][]) => harian.filter((b) => s.includes(b.status)).length;
    const persen = (x: number) => (harian.length ? `${Math.round((x / harian.length) * 100)}% dari ${harian.length} Biller` : undefined);

    const kolomRekap: ColumnDef<BarisRekap>[] = [
        { header: 'NAMA BILLER', cell: ({ row }) => row.original.nama },
        { header: 'NIP', cell: ({ row }) => <span className="font-mono">{row.original.nip}</span> },
        { header: 'HADIR', cell: ({ row }) => <span className="font-bold text-success">{formatNumber(row.original.hadir)}</span> },
        { header: 'TERLAMBAT', cell: ({ row }) => <span className={row.original.terlambat > 0 ? 'font-bold text-pln-yellow-ink' : undefined}>{formatNumber(row.original.terlambat)}</span> },
        { header: 'PULANG CEPAT', cell: ({ row }) => formatNumber(row.original.pulang_cepat) },
        { header: 'IZIN', cell: ({ row }) => formatNumber(row.original.izin) },
        { header: 'SAKIT', cell: ({ row }) => formatNumber(row.original.sakit) },
        { header: 'ALPA (TIDAK ABSEN)', cell: ({ row }) => <span className={row.original.alpa > 0 ? 'font-bold text-pln-red' : undefined}>{formatNumber(row.original.alpa)}</span> },
        { header: 'KOREKSI SUPER ADMIN', cell: ({ row }) => (row.original.koreksi > 0 ? `${row.original.koreksi} koreksi` : '—') },
    ];

    return (
        <>
            <Head title="Monitoring Absensi" />
            <PageHeader
                title={`Monitoring Absensi ${harian.length || 25} Biller`}
                description="Pantauan harian & rekap bulanan kehadiran seluruh biller · Kehadiran ikut skor kinerja biller"
                actions={
                    <>
                        <StatusChip titik label="Mode Baca Saja" tone="sky" />
                        <Input type="date" aria-label="Tanggal" className="w-40 bg-white font-semibold" value={tanggal} max={hariIni} onChange={(e) => e.target.value && muat({ tanggal: e.target.value })} />
                    </>
                }
            />
            <div className="grid gap-4">
                <Notice tone="waiting">Mode Baca Saja: Jika ada catatan keliru, laporkan ke Super Admin untuk koreksi dengan alasan wajib. Jika biller belum absen, hubungi di luar sistem (otomatis Alpa 23:59 WIB).</Notice>

                <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label={tanggal === hariIni ? 'Hadir Hari Ini' : 'Hadir'} value={`${n('hadir')} Biller`} valueClassName="text-success" caption={persen(n('hadir'))} />
                    <StatCard label="Izin" value={`${n('izin')} Biller`} valueClassName="text-pln-blue" caption={persen(n('izin'))} />
                    <StatCard label="Sakit" value={`${n('sakit')} Biller`} valueClassName="text-amber" caption={persen(n('sakit'))} />
                    <StatCard label="Belum Absen / Alpa" value={`${n('belum', 'alpa')} Biller`} valueClassName="text-pln-red" caption="Otomatis Alpa pukul 23:59 WIB" />
                </div>

                <Kartu judul={`Pantauan Harian — ${formatTanggal(tanggal, 'EEEE, d MMMM yyyy')}`}>
                    <TabelPantauanHarian data={harian} />
                </Kartu>

                <Kartu
                    judul={`Rekap Bulanan per Biller — ${formatTanggal(`${bulan}-01`, 'MMMM yyyy')}`}
                    aksi={<Input type="month" aria-label="Bulan" className="w-40" value={bulan} onChange={(e) => e.target.value && muat({ bulan: e.target.value })} />}
                >
                    <DataTable columns={kolomRekap} data={rekap} getRowId={(r) => String(r.user_id)} />
                </Kartu>
            </div>
        </>
    );
}

MonitoringAbsensi.layout = layoutKoordinator;
