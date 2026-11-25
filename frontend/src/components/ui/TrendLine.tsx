import { useId } from 'react';

import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface TitikGaris {
    kode: string;
    label: string;
    nilai: number | null;
}

interface TrendLineProps {
    data: TitikGaris[];
    tinggi?: number;
    /** Lebar viewBox; samakan dengan lebar tampil agar ketebalan garis tidak membesar. */
    lebar?: number;
    desimal?: number;
    warna?: 'blue' | 'sky';
    maksLabel?: number;
}

/**
 * Garis tren dengan area gradasi dan titik bulat; titik terakhir disorot. Label "Agu: 88,2" di
 * bawah tiap titik. Dipakai tren skor 4 periode dan tren pembacaan harian.
 */
export default function TrendLine({ data, tinggi = 82, desimal = 1, warna = 'blue', maksLabel = 8, lebar: L = 410 }: TrendLineProps) {
    const id = useId();
    const isi = data.filter((d) => d.nilai !== null) as (TitikGaris & { nilai: number })[];
    if (isi.length === 0) return <p className="py-6 text-center text-xs text-muted-ink">Belum ada data.</p>;

    const min = Math.min(...isi.map((d) => d.nilai));
    const maks = Math.max(...isi.map((d) => d.nilai));
    const x = (i: number) => (isi.length === 1 ? L / 2 : 18 + (i * (L - 36)) / (isi.length - 1));
    const y = (n: number) => (maks === min ? tinggi / 2 : 14 + ((maks - n) / (maks - min)) * (tinggi - 34));
    const titik = isi.map((d, i) => [x(i), y(d.nilai)] as const);
    const garis = titik.map(([a, b], i) => `${i ? 'L' : 'M'}${a} ${b}`).join('');
    const dasar = tinggi - 6;
    const stroke = warna === 'sky' ? 'stroke-sky-val' : 'stroke-pln-blue';
    const langkah = Math.max(1, Math.ceil(isi.length / maksLabel));

    return (
        <div>
            <svg viewBox={`0 0 ${L} ${tinggi}`} className="w-full" role="img" aria-label={isi.map((d) => `${d.label} ${formatNumber(d.nilai, desimal)}`).join(', ')}>
                <defs>
                    <linearGradient id={id} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stopColor={warna === 'sky' ? '#0284c7' : '#1d4ed8'} stopOpacity="0.2" />
                        <stop offset="1" stopColor={warna === 'sky' ? '#0284c7' : '#1d4ed8'} stopOpacity="0.02" />
                    </linearGradient>
                </defs>
                {isi.length > 1 && <path d={`${garis}V${dasar}H${titik[0][0]}Z`} fill={`url(#${id})`} />}
                <path d={garis} fill="none" className={stroke} strokeWidth={3} strokeLinecap="round" strokeLinejoin="round" />
                {titik.map(([a, b], i) => {
                    const akhir = i === titik.length - 1;
                    return (
                        <circle
                            key={isi[i].kode}
                            cx={a}
                            cy={b}
                            r={akhir ? 6 : isi.length > 12 ? 3 : 5}
                            className={akhir ? 'fill-success stroke-success-tint' : cn('fill-white', stroke)}
                            strokeWidth={akhir ? 3 : 2.5}
                        >
                            <title>{`${isi[i].label}: ${formatNumber(isi[i].nilai, desimal)}`}</title>
                        </circle>
                    );
                })}
            </svg>
            <div className={cn('mt-2 flex justify-between gap-2 text-[10px] font-bold text-muted-ink', isi.length === 1 && 'justify-center')}>
                {isi.map((d, i) =>
                    i % langkah === 0 || i === isi.length - 1 ? (
                        <span key={d.kode} className={i === isi.length - 1 ? 'text-success' : undefined}>
                            {d.label}: {formatNumber(d.nilai, desimal)}
                        </span>
                    ) : null,
                )}
            </div>
        </div>
    );
}
