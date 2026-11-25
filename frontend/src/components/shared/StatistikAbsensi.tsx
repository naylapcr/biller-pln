import DonutChart from '@/components/ui/DonutChart';
import type { StatistikAbsensi as Statistik } from '@/types';

/** Donut Statistik Absensi Hari Ini (02.1). Alpa baru tercatat 23.59, jadi digabung ke Belum Absen. */
export default function StatistikAbsensi({ data }: { data: Statistik }) {
    const sudah = data.hadir + data.izin + data.sakit;

    return (
        <DonutChart
            tengah={`${data.total > 0 ? Math.round((sudah / data.total) * 100) : 0}%`}
            keterangan="Sudah Absen"
            data={[
                { label: 'Hadir', nilai: data.hadir, warna: 'stroke-success', titik: 'bg-success' },
                { label: 'Izin', nilai: data.izin, warna: 'stroke-[#2563eb]', titik: 'bg-[#2563eb]' },
                { label: 'Sakit', nilai: data.sakit, warna: 'stroke-[#f59e0b]', titik: 'bg-[#f59e0b]' },
                { label: 'Belum Absen', nilai: data.belum + data.alpa, warna: 'stroke-[#ef4444]', titik: 'bg-[#ef4444]' },
            ]}
        />
    );
}
