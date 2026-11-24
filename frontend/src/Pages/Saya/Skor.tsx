import { usePage } from '@inertiajs/react';
import { TrendingUp } from 'lucide-react';

import EmptyState from '@/Components/shared/EmptyState';
import PageHeader from '@/Components/shared/PageHeader';
import TrendBars from '@/Components/shared/TrendBars';
import Leaderboard from '@/Components/sipermon/Leaderboard';
import PeriodeSelect from '@/Components/sipermon/PeriodeSelect';
import RincianSkor from '@/Components/sipermon/RincianSkor';
import AppLayout from '@/Layouts/AppLayout';
import type { BarisLeaderboard, KasusDenda, Option, PageProps, PeriodeInfo, SkorBiller } from '@/types';

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    skor: SkorBiller | null;
    kasus: KasusDenda[];
    top: BarisLeaderboard[];
    bottom: BarisLeaderboard[];
    riwayat: { periode: string; label: string; final_score: number; peringkat: number | null; is_final: boolean }[];
}>;

/** Halaman Biller: skor, rincian poin, denda, dan posisi peringkat miliknya (hanya baca). */
export default function SayaSkor() {
    const { periode, periodeOptions, skor, kasus, top, bottom, riwayat, auth } = usePage<Props>().props;

    return (
        <AppLayout title="Skor saya">
            <PageHeader
                title={`Skor saya${periode ? ` · ${periode.nama}` : ''}`}
                description={`${auth.user.nama} · ${auth.user.biller?.login_acmt ?? ''} · kode ${auth.user.biller?.kode_petugas ?? '–'}. Data berasal dari rekap ACMT yang diunggah Koordinator Admin.`}
                actions={<PeriodeSelect value={periode?.kode} options={periodeOptions} />}
            />

            {!periode || !skor ? (
                <EmptyState icon={TrendingUp} title="Skor belum tersedia">
                    Skor muncul setelah Koordinator Admin mengunggah dan meng-commit rekap ACMT periode ini.
                </EmptyState>
            ) : (
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <RincianSkor skor={skor} kasus={kasus} />

                    <aside className="flex flex-col gap-3">
                        <Leaderboard judul={`Top ${top.length}`} baris={top} nada="atas" sorotBillerId={skor.biller.id} />
                        <Leaderboard judul={`Bottom ${bottom.length}`} baris={bottom} nada="bawah" sorotBillerId={skor.biller.id} />
                        {riwayat.length > 1 && (
                            <section className="rounded-14 border border-border p-5">
                                <h2 className="text-section-title text-ink">Skor saya per periode</h2>
                                <TrendBars
                                    tinggi={180}
                                    satuan="Skor"
                                    desimal={1}
                                    sorot={periode.kode}
                                    data={riwayat.map((r) => ({
                                        kode: r.periode,
                                        label: r.periode.slice(4) + '/' + r.periode.slice(2, 4),
                                        nilai: r.final_score,
                                        keterangan: `${r.label} · peringkat ${r.peringkat ?? '–'} · ${r.is_final ? 'final' : 'sementara'}`,
                                    }))}
                                />
                            </section>
                        )}
                    </aside>
                </div>
            )}
        </AppLayout>
    );
}
