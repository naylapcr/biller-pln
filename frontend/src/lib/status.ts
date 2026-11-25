/**
 * Peta status → teks + nada warna chip. Satu-satunya tempat untuk teks status.
 */
export type StatusTone = 'waiting' | 'danger' | 'success' | 'info' | 'sky' | 'violet' | 'neutral';

export interface StatusMeta {
    label: string;
    tone: StatusTone;
}

export type StatusBatch = 'diproses' | 'siap_commit' | 'gagal' | 'committed' | 'dibatalkan' | 'digantikan';

/** Label status batch sesuai desain (PRD 10.3). siap_commit dengan warning memakai labelBatch(). */
export const STATUS_BATCH: Record<StatusBatch, StatusMeta> = {
    diproses: { label: 'Diproses', tone: 'info' },
    siap_commit: { label: 'Siap Commit', tone: 'waiting' },
    gagal: { label: 'Ditolak', tone: 'danger' },
    committed: { label: 'Committed ✓', tone: 'success' },
    dibatalkan: { label: 'Dibatalkan', tone: 'neutral' },
    digantikan: { label: 'Digantikan (Superseded)', tone: 'neutral' },
};

export function labelBatch(status: StatusBatch, error = 0, warning = 0): StatusMeta {
    if (status === 'gagal') return { label: `Ditolak (${error} Error)`, tone: 'danger' };
    if (status === 'siap_commit' && warning > 0) return { label: `${warning} Warning · Review`, tone: 'waiting' };
    return STATUS_BATCH[status];
}

export type TingkatIssue = 'error' | 'warning' | 'info';

export const TINGKAT_ISSUE: Record<TingkatIssue, StatusMeta> = {
    error: { label: 'Error', tone: 'danger' },
    warning: { label: 'Warning', tone: 'waiting' },
    info: { label: 'Info', tone: 'info' },
};

export const KATEGORI: Record<string, StatusMeta> = {
    'Grade A': { label: 'Grade A', tone: 'success' },
    'Perlu Evaluasi': { label: 'Evaluasi', tone: 'waiting' },
    Kritis: { label: 'Kritis', tone: 'danger' },
};

export function metaKategori(kategori: string | null | undefined): StatusMeta {
    return (kategori && KATEGORI[kategori]) || { label: 'Belum dinilai', tone: 'neutral' };
}

export const STATUS_ABSENSI: Record<'hadir' | 'izin' | 'sakit' | 'alpa' | 'belum', StatusMeta> = {
    hadir: { label: 'Hadir', tone: 'success' },
    izin: { label: 'Izin', tone: 'info' },
    sakit: { label: 'Sakit', tone: 'waiting' },
    alpa: { label: 'Alpa', tone: 'danger' },
    belum: { label: 'Belum Absen', tone: 'danger' },
};

export const MODUL_LABEL: Record<string, string> = {
    pascabayar: 'Pascabayar',
    prabayar: 'Prabayar',
    invoice: 'Invoice',
    pdil: 'PDIL',
};

export const PERAN_LABEL: Record<string, string> = {
    super_admin: 'Super Admin',
    admin: 'Koordinator Admin',
    biller: 'Biller',
};

export const TONE_CLASS: Record<StatusTone, string> = {
    waiting: 'bg-pln-yellow-light text-pln-yellow-ink',
    danger: 'bg-error-tint text-error-ink',
    success: 'bg-success-tint text-success-ink',
    info: 'bg-pln-blue-light text-pln-blue',
    sky: 'bg-sky-tint text-sky-ink',
    violet: 'bg-violet-tint text-violet',
    neutral: 'bg-line-row text-slate-mid',
};

/** Warna angka skor menurut kategori dan peringkat (tabel Direktori dan Laporan). */
export function warnaSkor(kategori: string | null | undefined): string {
    if (kategori === 'Grade A') return 'text-success';
    if (kategori === 'Perlu Evaluasi') return 'text-pln-yellow-ink';
    if (kategori === 'Kritis') return 'text-pln-red';
    return 'text-ink';
}

/** Warna chip kode aksi audit. */
export function toneAksi(aksi: string): StatusTone {
    if (/^(CLOSE|DELETE|ROLLBACK|LOCK|CANCEL)/.test(aksi)) return 'danger';
    if (/^(UPDATE|CHANGE)/.test(aksi)) return 'waiting';
    if (/^(COMMIT|UPLOAD|RECALCULATE)/.test(aksi)) return 'info';
    if (/^(ADD|UNLOCK|OPEN|ATTEND)/.test(aksi)) return 'success';
    if (/^CORRECT/.test(aksi)) return 'violet';
    if (/^CREATE/.test(aksi)) return 'sky';
    return 'neutral';
}
