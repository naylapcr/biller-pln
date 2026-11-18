/**
 * Peta status → teks kalimat utuh + nada warna chip.
 * Satu-satunya tempat untuk teks status (DESIGN: StatusChip).
 */
export type StatusTone = 'waiting' | 'danger' | 'success' | 'info' | 'neutral';

export interface StatusMeta {
    label: string;
    tone: StatusTone;
}

export type StatusBatch = 'diproses' | 'siap_commit' | 'gagal' | 'committed' | 'dibatalkan' | 'digantikan';

export const STATUS_BATCH: Record<StatusBatch, StatusMeta> = {
    diproses: { label: 'Sedang diproses', tone: 'info' },
    siap_commit: { label: 'Siap commit', tone: 'waiting' },
    gagal: { label: 'Gagal validasi', tone: 'danger' },
    committed: { label: 'Committed', tone: 'success' },
    dibatalkan: { label: 'Dibatalkan', tone: 'neutral' },
    digantikan: { label: 'Digantikan batch baru', tone: 'neutral' },
};

export type TingkatIssue = 'error' | 'warning' | 'info';

export const TINGKAT_ISSUE: Record<TingkatIssue, StatusMeta> = {
    error: { label: 'Error', tone: 'danger' },
    warning: { label: 'Warning', tone: 'waiting' },
    info: { label: 'Info', tone: 'info' },
};

export const KATEGORI: Record<string, StatusMeta> = {
    stabil: { label: 'Stabil', tone: 'success' },
    perlu_evaluasi: { label: 'Perlu evaluasi', tone: 'danger' },
};

export const MODUL_LABEL: Record<string, string> = {
    pascabayar: 'Pascabayar',
    prabayar: 'Prabayar',
    invoice: 'Invoice',
    pdil: 'PDIL',
};

export const TONE_CLASS: Record<StatusTone, string> = {
    waiting: 'bg-pln-yellow-light text-pln-yellow-ink',
    danger: 'bg-error-tint text-error-ink',
    success: 'bg-success-tint text-success-ink',
    info: 'bg-pln-blue-light text-pln-blue-dark',
    neutral: 'bg-table-head text-muted-ink',
};
