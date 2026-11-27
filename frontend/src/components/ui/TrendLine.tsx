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
 * Garis tren dengan area gradasi, garis bantu, dan titik bulat; titik terakhir disorot. Semua periode tetap
 * tampil di sumbu: periode tanpa data ditandai "–" dan titik abu-abu, jadi grafik tidak kosong walau baru ada
 * satu periode. Dipakai tren skor 4 periode, tren rata-rata skor, dan tren pembacaan harian.
 */
export default function TrendLine({ data, tinggi = 96, desimal = 1, warna = 'blue', maksLabel = 8, lebar: L = 410 }: TrendLineProps) {
    const id = useId();
    const isi = data.map((d, i) => ({ ...d, i })).filter((d): d is TitikGaris & { i: number; nilai: number } => d.nilai !== null);
    if (data.length === 0 || isi.length === 0) {
        return <p className="py-6 text-center text-xs text-muted-ink">Belum ada data untuk ditampilkan.</p>;
    }

    let min = Math.min(...isi.map((d) => d.nilai));
    let maks = Math.max(...isi.map((d) => d.nilai));
    const ruang = Math.max(1, (maks - min) * 0.15, Math.abs(maks) * 0.04);
    min -= ruang;
    maks += ruang;

    const atas = 12;
    const dasar = tinggi - 10;
    const x = (i: number) => (data.length === 1 ? L / 2 : 20 + (i * (L - 40)) / (data.length - 1));
    const y = (n: number) => atas + ((maks - n) / (maks - min)) * (dasar - atas);
    const titik = isi.map((d) => [x(d.i), y(d.nilai)] as const);
    const garis = titik.map(([a, b], i) => `${i ? 'L' : 'M'}${a} ${b}`).join('');
    const stroke = warna === 'sky' ? 'stroke-sky-val' : 'stroke-pln-blue';
    const langkah = Math.max(1, Math.ceil(data.length / maksLabel));
    const kosong = data.filter((d) => d.nilai === null);

    return (
        <div>
            <svg viewBox={`0 0 ${L} ${tinggi}`} className="w-full" role="img" aria-label={data.map((d) => `${d.label} ${d.nilai === null ? 'belum ada data' : formatNumber(d.nilai, desimal)}`).join(', ')}>
                <defs>
                    <linearGradient id={id} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stopColor={warna === 'sky' ? '#0284c7' : '#1d4ed8'} stopOpacity="0.2" />
                        <stop offset="1" stopColor={warna === 'sky' ? '#0284c7' : '#1d4ed8'} stopOpacity="0.02" />
                    </linearGradient>
                </defs>
                {[0, 0.5, 1].map((f) => (
                    <line key={f} x1={8} x2={L - 8} y1={atas + f * (dasar - atas)} y2={atas + f * (dasar - atas)} className="stroke-line" strokeWidth={1} strokeDasharray={f === 1 ? undefined : '4 4'} />
                ))}
                {titik.length > 1 && <path d={`${garis}V${dasar}H${titik[0][0]}Z`} fill={`url(#${id})`} />}
                {titik.length > 1 && <path d={garis} fill="none" className={stroke} strokeWidth={3} strokeLinecap="round" strokeLinejoin="round" />}
                {kosong.map((d) => (
                    <circle key={d.kode} cx={x(data.indexOf(d))} cy={dasar} r={4} className="fill-white stroke-line-strong" strokeWidth={2}>
                        <title>{`${d.label}: belum ada data`}</title>
                    </circle>
                ))}
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
            <div className="relative mt-1.5 h-4 text-[10px] font-bold text-muted-ink">
                {data.map((d, i) =>
                    i % langkah === 0 || i === data.length - 1 ? (
                        <span
                            key={d.kode}
                            className={cn('absolute whitespace-nowrap', data.length > 1 && i === 0 ? '' : data.length > 1 && i === data.length - 1 ? '-translate-x-full' : '-translate-x-1/2', i === isi[isi.length - 1].i && 'text-success', d.nilai === null && 'font-medium text-faint')}
                            style={{ left: data.length > 1 && i === 0 ? 0 : data.length > 1 && i === data.length - 1 ? '100%' : `${(x(i) / L) * 100}%` }}
                        >
                            {d.label}: {d.nilai === null ? '–' : formatNumber(d.nilai, desimal)}
                        </span>
                    ) : null,
                )}
            </div>
            {isi.length === 1 && data.length > 1 && (
                <p className="mt-2 text-center text-[11px] text-muted-ink">Baru ada data 1 periode. Garis tren muncul setelah periode berikutnya punya skor.</p>
            )}
        </div>
    );
}
