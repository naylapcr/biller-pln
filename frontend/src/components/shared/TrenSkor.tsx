import StatusChip from '@/components/ui/StatusChip';
import TrendLine from '@/components/ui/TrendLine';
import { formatNumber } from '@/lib/format';
import type { TitikTren } from '@/types';

const bulan = (label: string) => label.split(' ')[0];

/** Tren Skor 4 Periode (01.1 dan drawer 02.2). */
export default function TrenSkor({ data }: { data: TitikTren[] }) {
    return <TrendLine data={data.map((t) => ({ kode: t.periode, label: bulan(t.label), nilai: t.final_score }))} />;
}

/** Chip selisih terhadap periode sebelumnya, contoh "+2,7 vs Okt". */
export function ChipSelisih({ data }: { data: { label: string; nilai: number | null }[] }) {
    const [lama, baru] = data.filter((d) => d.nilai !== null).slice(-2);
    if (!baru || lama.nilai === null || baru.nilai === null) return null;
    const s = baru.nilai - lama.nilai;

    return <StatusChip tone={s >= 0 ? 'success' : 'danger'} label={`${s >= 0 ? '+' : '−'}${formatNumber(Math.abs(s), 1)} vs ${bulan(lama.label)}`} />;
}
