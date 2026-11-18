import { Link, router, usePage } from '@inertiajs/react';
import { CircleAlert, Upload } from 'lucide-react';

import EmptyState from '@/Components/shared/EmptyState';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import StatCard from '@/Components/shared/StatCard';
import Toolbar from '@/Components/shared/Toolbar';
import TrendBars from '@/Components/shared/TrendBars';
import Leaderboard from '@/Components/sipermon/Leaderboard';
import PeriodeSelect from '@/Components/sipermon/PeriodeSelect';
import { Button } from '@/Components/ui/button';
import { NativeSelect } from '@/Components/ui/native-select';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatPercent, formatTanggal } from '@/lib/format';
import type { BarisLeaderboard, Option, PageProps, PeriodeInfo } from '@/types';

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    filters: { hari_baca?: string; jenis_meter?: string };
    jenisMeterOptions: Option[];
    ringkasan: {
        total_wo: number;
        realisasi: number;
        realisasi_persen: number | null;
        pascabayar: { target: number; terbaca: number; foto: number };
        prabayar: { target: number; normal: number } | null;
        rasio_kecepatan: number | null;
        total_denda: number;
        kasus_denda: number;
    } | null;
    top: BarisLeaderboard[];
    bottom: BarisLeaderboard[];
    tren: { tanggal: string; terbaca: number }[];
    parameterKosong: string[];
}>;

const HARI = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'J'];

export default function DashboardIndex() {
    const { periode, periodeOptions, filters, jenisMeterOptions, ringkasan, top, bottom, tren, parameterKosong } = usePage<Props>().props;

    const saring = (patch: Record<string, string>) => {
        const query = Object.fromEntries(Object.entries({ periode: periode?.kode, ...filters, ...patch }).filter(([, v]) => v));
        router.get(route('dashboard'), query, { preserveScroll: true, preserveState: true });
    };
    const bukaBiller = (id: number) => router.get(route('biller.index'), { periode: periode?.kode, detail: id });

    if (!periode) {
        return (
            <AppLayout title="Dashboard">
                <PageHeader title="Dashboard kinerja" />
                <EmptyState icon={CircleAlert} title="Belum ada periode">
                    Super Admin perlu membuka periode BLTH lebih dulu, lalu Koordinator Admin mengunggah berkas ACMT.
                </EmptyState>
            </AppLayout>
        );
    }

    const kosong = ringkasan !== null && ringkasan.total_wo === 0 && top.length === 0;

    return (
        <AppLayout title="Dashboard">
            <PageHeader
                title="Dashboard kinerja"
                description={`${periode.nama} · BLTH ${periode.kode} · ${periode.terbuka ? 'skor sementara, periode masih terbuka' : 'skor final'}`}
                actions={
                    <Button asChild>
                        <Link href={route('unggah.index', { periode: periode.kode })}>
                            <Upload />
                            Unggah data
                        </Link>
                    </Button>
                }
            />

            <Toolbar>
                <PeriodeSelect value={periode.kode} options={periodeOptions} params={filters} />
                <NativeSelect className="md:w-40" aria-label="Hari baca" value={filters.hari_baca ?? ''} onChange={(e) => saring({ hari_baca: e.target.value })}>
                    <option value="">Semua hari baca</option>
                    {HARI.map((h) => (
                        <option key={h} value={h}>
                            Hari {h}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect className="md:w-48" aria-label="Jenis meter" value={filters.jenis_meter ?? ''} onChange={(e) => saring({ jenis_meter: e.target.value })}>
                    <option value="">Semua jenis meter</option>
                    {jenisMeterOptions.map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </NativeSelect>
            </Toolbar>

            {parameterKosong.length > 0 && (
                <Notice tone="waiting" title="Parameter skor belum lengkap" className="mb-5">
                    {parameterKosong.join(', ')} belum ditetapkan. Bonus Hari J dihitung 0 dan periode belum bisa ditutup sampai Super Admin mengisinya.
                </Notice>
            )}

            {kosong ? (
                <EmptyState
                    icon={Upload}
                    title="Belum ada data di periode ini"
                    action={
                        <Button asChild>
                            <Link href={route('unggah.index', { periode: periode.kode })}>Unggah berkas ACMT</Link>
                        </Button>
                    }
                >
                    Skor muncul setelah berkas rekap ACMT diunggah, lolos validasi, dan di-commit.
                </EmptyState>
            ) : (
                ringkasan && (
                    <>
                        <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <StatCard
                                label="Total WO"
                                value={formatNumber(ringkasan.total_wo)}
                                caption={
                                    ringkasan.prabayar
                                        ? `${formatNumber(ringkasan.pascabayar.target)} pascabayar · ${formatNumber(ringkasan.prabayar.target)} prabayar`
                                        : `${formatNumber(ringkasan.pascabayar.target)} pascabayar (filter aktif)`
                                }
                            />
                            <StatCard
                                label="Realisasi"
                                value={formatPercent(ringkasan.realisasi_persen)}
                                progress={ringkasan.realisasi_persen ?? 0}
                                caption={`${formatNumber(ringkasan.realisasi)} terbaca / KDBACA normal`}
                            />
                            <StatCard
                                label="Rasio kecepatan"
                                value={formatNumber(ringkasan.rasio_kecepatan, 1)}
                                caption="Pelanggan per jam kalender. WAKTU ACMT termasuk malam hari, bukan jam kerja."
                            />
                            <StatCard
                                label="Total denda"
                                value={formatNumber(ringkasan.total_denda, 1)}
                                caption={`${formatNumber(ringkasan.kasus_denda)} kasus Baca Ulang dan AP2T`}
                            />
                        </div>

                        <div className="mb-6 grid gap-3 lg:grid-cols-2">
                            <Leaderboard judul={`Top ${top.length}`} baris={top} nada="atas" onPilih={bukaBiller} />
                            <Leaderboard judul={`Bottom ${bottom.length}`} baris={bottom} nada="bawah" onPilih={bukaBiller} />
                        </div>

                        <section className="rounded-14 border border-border p-5">
                            <h2 className="text-section-title text-ink">Pelanggan pascabayar terbaca per tanggal mulai baca</h2>
                            <p className="mb-3 text-[13px] text-muted-ink">Dari rekap pembacaan ACMT, dikelompokkan menurut TGL MULAI tiap hari baca.</p>
                            {tren.length === 0 ? (
                                <p className="text-sm text-muted-ink">Belum ada data pascabayar.</p>
                            ) : (
                                <TrendBars
                                    satuan="Terbaca"
                                    data={tren.map((t) => ({ kode: t.tanggal, label: formatTanggal(t.tanggal, 'd MMM'), nilai: t.terbaca }))}
                                />
                            )}
                        </section>
                    </>
                )
            )}
        </AppLayout>
    );
}
