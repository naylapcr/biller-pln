import DonutChart from '@/components/ui/DonutChart';
import { formatNumber } from '@/lib/format';

/** Donut Distribusi Kategori (02.1 dan 03.0): Grade A, Perlu Evaluasi, Kritis. */
export default function DistribusiKategori({ data, bawah = false }: { data: Record<string, number>; bawah?: boolean }) {
    const total = Object.values(data).reduce((a, b) => a + b, 0);

    return (
        <DonutChart
            tengah={formatNumber(total)}
            keterangan={bawah ? 'Biller' : 'Total Biller'}
            bawah={bawah}
            data={[
                { label: bawah ? 'Grade A' : 'Grade A (≥ 85)', nilai: data['Grade A'] ?? 0, warna: 'stroke-success-line', titik: 'bg-success-line' },
                { label: bawah ? 'Evaluasi' : 'Perlu Evaluasi', nilai: data['Perlu Evaluasi'] ?? 0, warna: 'stroke-[#f59e0b]', titik: 'bg-[#f59e0b]' },
                { label: bawah ? 'Kritis' : 'Kritis (< 65)', nilai: data['Kritis'] ?? 0, warna: 'stroke-[#ef4444]', titik: 'bg-[#ef4444]' },
            ]}
        />
    );
}
