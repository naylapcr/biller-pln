import { Head, usePage } from '@inertiajs/react';

import Leaderboard from '@/components/shared/Leaderboard';
import PeriodeSelect from '@/components/shared/PeriodeSelect';
import Notice from '@/components/ui/Notice';
import PageHeader from '@/components/ui/PageHeader';
import StatusChip from '@/components/ui/StatusChip';
import { layoutBiller } from '@/layouts/BillerLayout/BillerLayout';
import { formatNumber } from '@/lib/format';
import { metaKategori } from '@/lib/status';
import type { BarisLeaderboard, Kategori, Option, PageProps, PeriodeInfo } from '@/types';

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    posisi: { peringkat: number | null; jumlah_biller: number; final_score: number | null; kategori: Kategori | null } | null;
    top: BarisLeaderboard[];
    bottom: BarisLeaderboard[];
    billerId: number;
}>;

/** 01.4 Leaderboard (mode privasi: biller lain hanya nama, sektor, dan peringkat). */
export default function LeaderboardBiller() {
    const { auth, periode, periodeOptions, posisi, top, bottom, billerId } = usePage<Props>().props;

    return (
        <>
            <Head title="Leaderboard" />
            <PageHeader
                title={`Leaderboard Kinerja ${posisi?.jumlah_biller || 25} Biller`}
                description={periode ? `Papan peringkat Top 5 & Bottom 5 periode BLTH ${periode.kode} (${periode.nama})` : undefined}
                actions={
                    <>
                        <PeriodeSelect value={periode?.kode} options={periodeOptions} />
                        <StatusChip titik label="Mode Privasi Biller Aktif" tone="success" />
                    </>
                }
            />

            <div className="grid gap-[18px]">
                <Notice tone="info">
                    Kebijakan Privasi: Dari biller lain, sistem hanya menampilkan NAMA, SEKTOR, dan PERINGKAT. Rincian skor, modul, dan kesalahan biller lain tidak
                    ditampilkan.
                </Notice>

                {posisi?.peringkat && (
                    <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border-[1.5px] border-success-line bg-white px-[22px] py-[18px] shadow-kartu">
                        <div className="flex min-w-0 items-center gap-3.5">
                            <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-xl bg-pln-yellow-light text-lg font-extrabold text-pln-yellow-ink">
                                #{posisi.peringkat}
                            </span>
                            <div className="min-w-0">
                                <p className="truncate text-[15px] font-bold text-ink">Posisi Anda: {auth.user.nama}</p>
                                <p className="text-xs text-muted-ink">
                                    Peringkat #{posisi.peringkat} dari {posisi.jumlah_biller} Biller · Wilayah {auth.user.biller?.zona_wilayah ?? 'belum diisi'}
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2.5">
                            <StatusChip label={`Skor Saya: ${formatNumber(posisi.final_score, 1)}`} tone="success" />
                            <StatusChip {...metaKategori(posisi.kategori)} tone={posisi.kategori === 'Grade A' ? 'info' : metaKategori(posisi.kategori).tone} />
                        </div>
                    </section>
                )}

                <div className="grid gap-[18px] xl:grid-cols-2">
                    <Leaderboard
                        judul="Top 5 Biller Terbaik"
                        subjudul="Menampilkan nama & peringkat saja"
                        chip={<StatusChip label="Kinerja Unggul" tone="waiting" />}
                        baris={top}
                        nada="atas"
                        sorotBillerId={billerId}
                    />
                    <Leaderboard
                        judul="Bottom 5 Peringkat Bawah"
                        subjudul="Menampilkan nama & peringkat saja (tanpa skor/kesalahan)"
                        chip={<StatusChip label="Fokus Pembinaan" tone="danger" />}
                        baris={bottom}
                        nada="bawah"
                        sorotBillerId={billerId}
                    />
                </div>
            </div>
        </>
    );
}

LeaderboardBiller.layout = layoutBiller;
