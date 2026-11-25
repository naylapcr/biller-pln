import { formatNumber } from '@/lib/format';
import type { SkorBiller } from '@/types';

const b = (persen: number | undefined, bawaan: number) => formatNumber((persen ?? bawaan) / 100, 2).replace(/0$/, '');

/** Kotak "Transparansi cara hitung skor" (01.1) dengan angka biller sendiri dan bobot periode. */
export default function PanelTransparansi({ skor }: { skor: SkorBiller }) {
    const a = skor.aturan;
    const pasca = skor.nilai_per_modul.pascabayar;
    const pra = skor.nilai_per_modul.prabayar;
    const modul = [pasca && formatNumber(pasca.nilai, 1), pra && formatNumber(pra.nilai, 1)].filter(Boolean).join('; ');

    return (
        <div className="rounded-10 border border-sky-side bg-sky-row px-3 py-2.5">
            <p className="text-[10px] leading-[13px] font-bold text-sky-ink">TRANSPARANSI CARA HITUNG SKOR</p>
            <div className="mt-1 grid gap-0.5 font-mono text-[10px] leading-[13px] text-ink-soft">
                {pasca && (
                    <p>
                        Pasca: ({b(a?.w_realisasi, 40)}×{formatNumber(pasca.realisasi, 1)} + {b(a?.w_eviden, 30)}×{formatNumber(pasca.eviden, 1)} + {b(a?.w_kecepatan, 30)}×
                        {formatNumber(pasca.kecepatan.nilai, 1)}) × (1 − {formatNumber(pasca.baca_ulang.rasio, 4)})
                        {pasca.ap2t.rasio > 0 && ` × (1 − ${formatNumber(pasca.ap2t.rasio, 4)})`} = {formatNumber(pasca.nilai, 1)}
                    </p>
                )}
                {pra && (
                    <p>
                        Prabayar: ({formatNumber(pra.realisasi, 1)} + {formatNumber(pra.eviden, 1)}) ÷ 2 = {formatNumber(pra.nilai, 1)}
                    </p>
                )}
                <p className="font-bold text-pln-blue">
                    Akhir: Rata-rata({modul}) + Bonus Hari J (+{formatNumber(skor.bonus_hari_j.nilai, 1)}) = {formatNumber(skor.final_score, 1)}
                </p>
            </div>
        </div>
    );
}
