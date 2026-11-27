import DonutChart from '@/components/ui/DonutChart';
import { formatNumber } from '@/lib/format';

/** Donut Distribusi Kategori (02.1 dan 03.0): Baik, Cukup, Perlu Pendampingan. */
export default function DistribusiKategori({ data, bawah = false }: { data: Record<string, number>; bawah?: boolean }) {
    const total = Object.values(data).reduce((a, b) => a + b, 0);

    return (
        <DonutChart
            tengah={formatNumber(total)}
            keterangan={bawah ? 'Biller' : 'Total Biller'}
            bawah={bawah}
            data={[
                { label: bawah ? 'Baik' : 'Baik (≥ 85)', nilai: data.Baik ?? 0, warna: 'stroke-success-line', titik: 'bg-success-line' },
                { label: 'Cukup', nilai: data.Cukup ?? 0, warna: 'stroke-sky-line', titik: 'bg-sky-line' },
                { label: bawah ? 'Pendampingan' : 'Perlu Pendampingan (< 65)', nilai: data['Perlu Pendampingan'] ?? 0, warna: 'stroke-[#f59e0b]', titik: 'bg-[#f59e0b]' },
            ]}
        />
    );
}
