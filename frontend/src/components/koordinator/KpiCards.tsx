import StatCard from '@/components/ui/StatCard';
import StatusChip from '@/components/ui/StatusChip';
import { formatNumber, formatPercent } from '@/lib/format';

export interface RingkasanDashboard {
    realisasi: { jml_plg: number; terbaca: number; persen: number | null; selisih: number | null };
    rata_skor: { nilai: number | null; jumlah_biller: number };
    kecepatan_eviden: { plg_per_jam: number | null; eviden_persen: number | null; standar: number };
    mutu: { kasus_bu: number; kasus_ap2t: number; rasio_bu: number | null; rasio_ap2t: number | null };
    distribusi_kategori: Record<string, number>;
    absensi_hari_ini: { total: number; hadir: number; izin: number; sakit: number; alpa: number; belum: number };
    belum_terpetakan: number;
}

const rasio = (r: number | null) => (r === null ? '–' : formatPercent(r * 100, 2));

/** 4 kartu KPI Dashboard Monitoring (02.1). */
export default function KpiCards({ r }: { r: RingkasanDashboard }) {
    const s = r.realisasi.selisih;

    return (
        <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Realisasi Terbaca"
                value={formatPercent(r.realisasi.persen, 1)}
                chip={s !== null && <StatusChip tone={s >= 0 ? 'success' : 'danger'} label={`${s >= 0 ? '+' : '−'}${formatPercent(Math.abs(s), 1)} vs BLTH lalu`} />}
                caption={`${formatNumber(r.realisasi.terbaca)} / ${formatNumber(r.realisasi.jml_plg)} pelanggan`}
            />
            <StatCard
                label={`Rata-rata Skor ${r.rata_skor.jumlah_biller} Biller`}
                value={formatNumber(r.rata_skor.nilai, 1)}
                chip={<StatusChip tone="info" label={`${r.distribusi_kategori['Grade A'] ?? 0} Grade A`} />}
                caption="Skala 0–100 + Bonus Hari J"
            />
            <StatCard
                label="Kecepatan & Eviden"
                value={`${formatNumber(r.kecepatan_eviden.plg_per_jam, 1)} /j`}
                chip={<StatusChip tone="sky" label={`Eviden ${formatPercent(r.kecepatan_eviden.eviden_persen, 1)}`} />}
                caption={`Standar ACMT: ${formatNumber(r.kecepatan_eviden.standar, 1)} plg/jam`}
            />
            <StatCard
                label="Rasio Kesalahan Mutu"
                value={r.mutu.rasio_bu === null ? '–' : rasio((r.mutu.rasio_bu ?? 0) + (r.mutu.rasio_ap2t ?? 0))}
                chip={<StatusChip tone="danger" label={`BU ${rasio(r.mutu.rasio_bu)}`} />}
                caption={`${formatNumber(r.mutu.kasus_bu)} kasus BU · AP2T ${rasio(r.mutu.rasio_ap2t)}`}
            />
        </div>
    );
}
