import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { formatNumber } from '@/lib/format';

export interface TrendPoint {
    kode: string;
    label: string;
    nilai: number | null;
    keterangan?: string;
}

interface TrendBarsProps {
    data: TrendPoint[];
    /** Kode yang disorot (bar biru); lainnya biru muda. Default: titik terakhir. */
    sorot?: string;
    tinggi?: number;
    /** Nama nilai di tooltip, contoh "Pelanggan terbaca". */
    satuan: string;
    desimal?: number;
}

/**
 * Grafik batang satu seri. Satu seri → tanpa legenda; judul kartu yang menamai seri.
 * Sumbu Y mengikuti data (skor poin berskala ribuan). Hover menampilkan nilai persis.
 */
export default function TrendBars({ data, sorot, tinggi = 220, satuan, desimal = 0 }: TrendBarsProps) {
    const disorot = sorot ?? data[data.length - 1]?.kode;

    return (
        <div style={{ height: tinggi }} className="w-full">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data} margin={{ top: 20, right: 4, bottom: 0, left: 0 }} barCategoryGap="24%">
                    <CartesianGrid vertical={false} className="stroke-line-row" />
                    <XAxis dataKey="label" tickLine={false} axisLine={false} tick={{ fontSize: 12 }} className="fill-muted-ink" />
                    <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 11 }} width={56} tickFormatter={(v: number) => formatNumber(v)} />
                    <Tooltip cursor={{ className: 'fill-table-head' }} content={<Tip satuan={satuan} desimal={desimal} />} />
                    <Bar dataKey="nilai" radius={[4, 4, 0, 0]} maxBarSize={44} isAnimationActive={false}>
                        {data.map((d) => (
                            <Cell key={d.kode} className={d.kode === disorot ? 'fill-pln-blue' : 'fill-pln-blue-soft'} />
                        ))}
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

function Tip({ active, payload, satuan, desimal }: { active?: boolean; payload?: { payload: TrendPoint }[]; satuan: string; desimal: number }) {
    if (!active || !payload?.length) return null;
    const p = payload[0].payload as TrendPoint;
    return (
        <div className="rounded-10 border border-border bg-white px-3 py-2 text-[13px] shadow-lg shadow-ink/5">
            <p className="font-medium text-ink">{p.label}</p>
            <p className="text-muted-ink">
                {satuan} <span className="font-mono font-semibold text-ink">{p.nilai === null ? '–' : formatNumber(p.nilai, desimal)}</span>
            </p>
            {p.keterangan && <p className="text-xs text-muted-ink">{p.keterangan}</p>}
        </div>
    );
}
