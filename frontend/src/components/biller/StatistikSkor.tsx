import { Donut } from '@/components/ui/DonutChart';
import Kartu from '@/components/ui/Kartu';
import RingNilai from '@/components/ui/RingNilai';
import { formatNumber } from '@/lib/format';
import type { SkorBiller } from '@/types';

/** Statistik Skor & Komponen Modul (01.1): gauge rata-rata + bonus dan tiga cincin R · E · K. */
export default function StatistikSkor({ skor }: { skor: SkorBiller }) {
    const pasca = skor.nilai_per_modul.pascabayar;
    const rata = skor.rata_rata_modul ?? 0;
    const bonus = skor.bonus_hari_j.nilai;

    return (
        <Kartu judul="Statistik Skor & Komponen Modul" className="self-start">
            <div className="flex flex-wrap items-center gap-5">
                <Donut
                    data={[
                        { nilai: rata, warna: 'stroke-pln-blue' },
                        { nilai: bonus, warna: 'stroke-sky-line' },
                    ]}
                    total={Math.max(100, rata + bonus)}
                    ukuran={138}
                    tebal={13}
                    tengah={formatNumber(skor.final_score, 1)}
                    keterangan="Skor Akhir"
                />
                <div className="min-w-0 flex-1 basis-56">
                    <div className="flex flex-wrap gap-3.5">
                        <RingNilai label="Realisasi" nilai={pasca?.realisasi ?? null} warna="stroke-[#2563eb]" />
                        <RingNilai label="Eviden" nilai={pasca?.eviden ?? null} warna="stroke-success" />
                        <RingNilai label="Kecepatan" nilai={pasca?.kecepatan.nilai ?? null} warna="stroke-sky-val" />
                    </div>
                    <p className="mt-3 text-[10px] leading-[13px] text-muted-ink">
                        Biru tua: Rata-rata modul ({formatNumber(rata, 1)}) · Biru muda: Bonus Hari J (+{formatNumber(bonus, 1)})
                    </p>
                </div>
            </div>
        </Kartu>
    );
}
