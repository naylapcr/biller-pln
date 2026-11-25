import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface SegmenDonut {
    label: string;
    nilai: number;
    /** Warna garis, contoh "stroke-success". */
    warna: string;
    /** Titik legenda, contoh "bg-success". */
    titik: string;
    /** Teks pengganti angka di legenda (kartu kesiapan). */
    teks?: string;
}

interface DonutProps {
    data: { nilai: number; warna: string }[];
    ukuran?: number;
    tebal?: number;
    /** Total lingkaran penuh; bawaan jumlah semua segmen. */
    total?: number;
    tengah: string;
    keterangan?: string;
    kelasTengah?: string;
}

/** Cincin dengan ujung membulat; segmen searah jarum jam dari atas. */
export function Donut({ data, ukuran = 144, tebal = 16, total, tengah, keterangan, kelasTengah }: DonutProps) {
    const r = (ukuran - tebal) / 2;
    const keliling = 2 * Math.PI * r;
    const jumlah = total ?? data.reduce((n, d) => n + d.nilai, 0);
    let mulai = 0;
    const busur = data
        .map((d) => {
            const bagian = jumlah > 0 ? Math.max(0, Math.min(1, d.nilai / jumlah)) : 0;
            const b = { ...d, bagian, mulai };
            mulai += bagian;
            return b;
        })
        .filter((b) => b.bagian > 0);

    return (
        <div className="relative shrink-0" style={{ width: ukuran, height: ukuran }}>
            <svg viewBox={`0 0 ${ukuran} ${ukuran}`} className="size-full -rotate-90" aria-hidden>
                <circle cx={ukuran / 2} cy={ukuran / 2} r={r} className="fill-none stroke-line" strokeWidth={tebal} />
                {busur.map((b, i) => (
                    <circle
                        key={i}
                        cx={ukuran / 2}
                        cy={ukuran / 2}
                        r={r}
                        className={cn('fill-none', b.warna)}
                        strokeWidth={tebal}
                        strokeLinecap={b.bagian < 1 ? 'round' : 'butt'}
                        strokeDasharray={`${Math.max(0.01, b.bagian * keliling)} ${keliling}`}
                        strokeDashoffset={-b.mulai * keliling}
                    />
                ))}
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center gap-px">
                <span className={cn('text-[26px] leading-[33px] font-extrabold text-ink', kelasTengah)}>{tengah}</span>
                {keterangan && <span className="text-[10px] font-semibold text-muted-ink">{keterangan}</span>}
            </div>
        </div>
    );
}

interface DonutChartProps {
    data: SegmenDonut[];
    tengah: string;
    keterangan: string;
    bawah?: boolean;
    /** Nilai lingkaran penuh; bawaan jumlah semua segmen. */
    total?: number;
    /** Gabungkan semua segmen menjadi satu busur (kartu kesiapan). */
    warnaTunggal?: string;
}

/** Donat + legenda. `bawah`: legenda di bawah donat (Dashboard Sistem 03.0). */
export default function DonutChart({ data, tengah, keterangan, bawah = false, total: penuh, warnaTunggal }: DonutChartProps) {
    const jumlah = data.reduce((n, d) => n + d.nilai, 0);
    const total = penuh ?? jumlah;

    return (
        <div className={cn('flex items-center', bawah ? 'flex-col gap-3' : 'flex-wrap gap-5')}>
            <Donut total={total} data={warnaTunggal ? [{ nilai: jumlah, warna: warnaTunggal }] : data} tengah={tengah} keterangan={keterangan} ukuran={bawah ? 126 : 144} tebal={bawah ? 14 : 16} kelasTengah={bawah ? 'text-[22px] leading-7' : undefined} />
            <Legenda data={data} total={total} className={bawah ? 'w-full' : 'min-w-[180px] flex-1'} />
        </div>
    );
}

export function Legenda({ data, total, className }: { data: SegmenDonut[]; total: number; className?: string }) {
    return (
        <ul className={cn('grid gap-2', className)}>
            {data.map((d) => (
                <li key={d.label} className="flex items-center justify-between gap-3 rounded-lg bg-canvas px-2.5 py-[7px]">
                    <span className="flex min-w-0 items-center gap-2 text-xs font-semibold text-slate-ink">
                        <span aria-hidden className={cn('size-[9px] shrink-0 rounded-full', d.titik)} />
                        <span className="truncate">{d.label}</span>
                    </span>
                    <span className="flex shrink-0 items-center gap-1.5">
                        <span className="text-xs font-extrabold text-ink">{d.teks ?? formatNumber(d.nilai)}</span>
                        {d.teks === undefined && <span className="text-[11px] font-medium text-muted-ink">{total > 0 ? Math.round((d.nilai / total) * 100) : 0}%</span>}
                    </span>
                </li>
            ))}
        </ul>
    );
}
