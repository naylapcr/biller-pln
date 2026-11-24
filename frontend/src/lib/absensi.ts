import type { StatusMeta } from '@/lib/status';

/** Status satu tanggal di kalender absensi. */
export type StatusHari = 'hadir' | 'izin' | 'sakit' | 'alpa' | 'libur' | 'belum';

export const STATUS_HARI: Record<StatusHari, StatusMeta> = {
    hadir: { label: 'Hadir', tone: 'success' },
    izin: { label: 'Izin', tone: 'info' },
    sakit: { label: 'Sakit', tone: 'waiting' },
    alpa: { label: 'Alpa', tone: 'danger' },
    libur: { label: 'Libur', tone: 'neutral' },
    belum: { label: 'Belum absen', tone: 'neutral' },
};

export const KELAS_HARI: Record<StatusHari, string> = {
    hadir: 'bg-success-tint text-success-ink',
    izin: 'bg-pln-blue-light text-pln-blue-dark',
    sakit: 'bg-pln-yellow-light text-pln-yellow-ink',
    alpa: 'bg-error-tint text-error-ink',
    libur: 'bg-table-head text-muted-ink',
    belum: 'bg-white text-muted-ink ring-1 ring-border',
};
