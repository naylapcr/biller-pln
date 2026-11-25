import { Head, Link, router, usePage } from '@inertiajs/react';
import { Calendar } from 'lucide-react';

import KpiCards, { type RingkasanDashboard } from '@/components/koordinator/KpiCards';
import PeriodeKosong from '@/components/koordinator/PeriodeKosong';
import DistribusiKategori from '@/components/shared/DistribusiKategori';
import Leaderboard from '@/components/shared/Leaderboard';
import PeriodeSelect from '@/components/shared/PeriodeSelect';
import StatistikAbsensi from '@/components/shared/StatistikAbsensi';
import EmptyState from '@/components/ui/EmptyState';
import Kartu from '@/components/ui/Kartu';
import Notice from '@/components/ui/Notice';
import PageHeader from '@/components/ui/PageHeader';
import StatusChip from '@/components/ui/StatusChip';
import TrendLine from '@/components/ui/TrendLine';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { layoutKoordinator } from '@/layouts/KoordinatorLayout/KoordinatorLayout';
import { formatNumber, formatTanggal } from '@/lib/format';
import type { BarisLeaderboard, Option, PageProps, PeriodeInfo } from '@/types';

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    filters: { hari_baca?: string; jenis_meter?: string };
    jenisMeterOptions: Option[];
    ringkasan: RingkasanDashboard | null;
    top: BarisLeaderboard[];
    bottom: BarisLeaderboard[];
    tren: { tanggal: string; terbaca: number }[];
}>;

const HARI = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'J'];

/** 02.1 Dashboard Monitoring (Koordinator Admin) dan 04.3 keadaan kosong. */
export default function Dashboard() {
    const { periode, periodeOptions, filters, jenisMeterOptions, ringkasan, top, bottom, tren } = usePage<Props>().props;
    const kosong = ringkasan !== null && ringkasan.rata_skor.jumlah_biller === 0;
    const puncak = tren.reduce<{ tanggal: string; terbaca: number } | null>((m, t) => (!m || t.terbaca > m.terbaca ? t : m), null);

    const filter = (patch: Record<string, string>) => {
        const q = Object.fromEntries(Object.entries({ periode: periode?.kode, ...filters, ...patch }).filter(([, v]) => v));
        router.get(route('dashboard'), q, { preserveScroll: true, preserveState: true });
    };
    const bukaDrawer = (id: number) => router.visit(route('biller.index', { periode: periode?.kode, detail: id }));

    return (
        <>
            <Head title="Dashboard" />
            <PageHeader
                title={kosong && periode ? `Dashboard Monitoring — BLTH ${periode.kode} (${periode.nama})` : `Dashboard Monitoring ${ringkasan?.rata_skor.jumlah_biller || 25} Biller`}
                description={
                    kosong ? 'Periode baru dibuka · Menunggu unggahan berkas ACMT & AP2T pertama' : 'Ringkasan KPI, Top 5 & Bottom 5, serta tren pembacaan harian · ULP Kota Barat'
                }
                actions={
                    kosong ? (
                        <>
                            <PeriodeSelect value={periode?.kode} options={periodeOptions} awalan="" />
                            <StatusChip label="Periode Baru · 0 Batch Committed" tone="sky" />
                        </>
                    ) : (
                        <Button asChild>
                            <Link href={route('unggah.index')}>+ Unggah Data ACMT</Link>
                        </Button>
                    )
                }
            />

            {!ringkasan ? (
                <EmptyState icon={Calendar} title="Belum ada periode">
                    Super Admin perlu membuka periode BLTH lebih dulu.
                </EmptyState>
            ) : kosong && periode ? (
                <PeriodeKosong kode={periode.kode} />
            ) : (
                <div className="grid gap-4">
                    <section className="flex flex-wrap items-center gap-2.5 rounded-2xl border border-line bg-white px-4 py-3">
                        <PeriodeSelect value={periode?.kode} options={periodeOptions} params={filters} className="w-full sm:w-[226px]" awalan="" />
                        <NativeSelect className="w-full sm:w-[192px]" aria-label="Hari baca" value={filters.hari_baca ?? ''} onChange={(e) => filter({ hari_baca: e.target.value })}>
                            <option value="">Hari: A–G & J</option>
                            {HARI.map((h) => (
                                <option key={h} value={h}>
                                    Hari: {h}
                                </option>
                            ))}
                        </NativeSelect>
                        <NativeSelect className="w-full sm:w-[220px]" aria-label="Jenis meter" value={filters.jenis_meter ?? ''} onChange={(e) => filter({ jenis_meter: e.target.value })}>
                            <option value="">Meter: Semua</option>
                            {jenisMeterOptions.map((m) => (
                                <option key={m.value} value={m.value}>
                                    Meter: {m.label}
                                </option>
                            ))}
                        </NativeSelect>
                        {ringkasan.belum_terpetakan > 0 && (
                            <Link href={route('pemetaan.index')} className="sm:ml-auto">
                                <StatusChip titik tone="waiting" label={`${ringkasan.belum_terpetakan} Login Belum Terpetakan`} />
                            </Link>
                        )}
                    </section>

                    {(filters.hari_baca || filters.jenis_meter) && (
                        <Notice tone="info">Filter hari baca dan jenis meter hanya berlaku untuk kartu realisasi dan eviden pascabayar.</Notice>
                    )}
                    <KpiCards r={ringkasan} />

                    <div className="grid gap-4 xl:grid-cols-2">
                        <Kartu judul={`Distribusi Kategori ${ringkasan.rata_skor.jumlah_biller} Biller`} aksi={<StatusChip label={`BLTH ${periode?.kode}`} tone="info" />}>
                            <DistribusiKategori data={ringkasan.distribusi_kategori} />
                        </Kartu>
                        <Kartu judul={`Statistik Absensi Hari Ini (${formatTanggal(new Date(), 'd MMM')})`} aksi={<StatusChip label="Tidak Pengaruhi Skor" tone="waiting" />}>
                            <StatistikAbsensi data={ringkasan.absensi_hari_ini} />
                        </Kartu>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-2">
                        <Leaderboard
                            judul="Top 5 Biller Terbaik"
                            subjudul="Klik nama untuk buka Drawer Profil Biller"
                            chip={<StatusChip label="Grade A" tone="success" />}
                            baris={top}
                            nada="atas"
                            onPilih={bukaDrawer}
                        />
                        <Leaderboard
                            judul="Bottom 5 Perlu Evaluasi & Kritis"
                            subjudul="Klik nama untuk evaluasi rincian kesalahan"
                            chip={<StatusChip label="Fokus Pembinaan" tone="danger" />}
                            baris={bottom}
                            nada="bawah"
                            onPilih={bukaDrawer}
                        />
                    </div>

                    <Kartu
                        judul={`Grafik Tren Pembacaan Pelanggan Harian (BLTH ${periode?.kode})`}
                        subjudul="Akumulasi pelanggan terbaca dari data commit ACMT (Hari Baca A–G & Hari J), menurut TGL MULAI"
                        aksi={puncak && <StatusChip tone="info" label={`Puncak Harian: ${formatNumber(puncak.terbaca)} Plg (${formatTanggal(puncak.tanggal, 'd MMM')})`} />}
                    >
                        <TrendLine data={tren.map((t) => ({ kode: t.tanggal, label: formatTanggal(t.tanggal, 'd MMM'), nilai: t.terbaca }))} lebar={880} tinggi={170} desimal={0} warna="sky" />
                    </Kartu>
                </div>
            )}
        </>
    );
}

Dashboard.layout = layoutKoordinator;
