export type Role = 'biller' | 'admin' | 'super_admin';

export interface AuthUser {
    id: number;
    username_nip: string;
    nama: string;
    role: Role;
    role_label: string;
    biller: { id: number; login_acmt: string; kode_petugas: string } | null;
}

/** Angka kecil per menu, kunci = id item navigasi. */
export type NavBadges = Partial<Record<string, number>>;

export interface SharedProps {
    appName: string;
    auth: { user: AuthUser | null };
    navBadges: NavBadges | null;
    flash: { success: string | null; error: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}

/** Props halaman yang membutuhkan pengguna sudah masuk. */
export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T &
    SharedProps & { auth: { user: AuthUser } };

/** Hasil paginate() Laravel yang dikirim lewat Inertia. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export interface Option<V extends string | number = string> {
    value: V;
    label: string;
}

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

/** Periode yang sedang ditampilkan (BLTH). */
export interface PeriodeInfo {
    kode: string;
    nama: string;
    status: 'terbuka' | 'ditutup';
    terbuka: boolean;
}

export interface PoinModul {
    stan_valid: number;
    eviden: number;
    poin: number;
    terbaca?: number;
    jml_plg?: number;
    kasus_bu?: number;
    pemeriksaan?: number;
}

/** Bentuk respons skor satu biller (/billers/{id}/score dan /me/score). */
export interface SkorBiller {
    periode: string;
    biller: { id: number; nama: string; login_acmt: string; kode_petugas: string };
    poin_per_modul: Partial<Record<'pascabayar' | 'prabayar' | 'invoice' | 'pdil', PoinModul>>;
    bonus_hari_j: number;
    denda: {
        baca_ulang: { kasus: number; poin: number };
        ap2t: { kasus: number; poin: number };
        absensi: { alpa: number; poin: number };
    };
    absensi: RekapAbsensi | null;
    poin_tugas: number | null;
    final_score: number | null;
    peringkat: number | null;
    jumlah_biller: number;
    kategori: 'stabil' | 'perlu_evaluasi' | null;
    is_final: boolean;
    dihitung_at: string | null;
    indikator: {
        terbaca_hari_j: number;
        kasus_bu: number;
        kasus_ap2t: number;
        rasio_kelalaian: number | null;
        rasio_kecepatan: number | null;
    } | null;
    eviden: Record<string, { target: number; realisasi: number; eviden: number; persen: number | null }>;
    aturan: { w_stan: number; w_eviden: number; mode_agregasi: string; denda_bu_per_kasus: number } | null;
}

export interface RekapAbsensi {
    hadir: number;
    izin: number;
    sakit: number;
    alpa: number;
    alpa_tanpa_absen: number;
    hari_kerja: number;
}

export type StatusAbsensi = 'hadir' | 'izin' | 'sakit' | 'alpa';

export interface KasusDenda {
    id: number;
    sumber: 'BU' | 'AP2T';
    sumber_label: string;
    idpel: string;
    stan_awal: number | null;
    stan_benar: number | null;
    tingkat: string | null;
    denda_poin: number;
    catatan: string | null;
    kode_rbm: string | null;
    daya_va: number | null;
    tgl_baca: string | null;
    tgl_baca_bu: string | null;
    batas_kwh: number | null;
}

export interface BarisLeaderboard {
    biller_id: number;
    nama: string;
    kode_petugas: string;
    peringkat: number;
    final_score: number;
    login_acmt?: string;
    poin_tugas?: number;
    poin_hari_j?: number;
    total_denda?: number;
    kategori?: string;
}
