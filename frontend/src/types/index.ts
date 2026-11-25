export type Role = 'biller' | 'admin' | 'super_admin';

export interface AuthUser {
    id: number;
    username_nip: string;
    nama: string;
    role: Role;
    role_label: string;
    biller: {
        id: number;
        nip: string | null;
        login_acmt: string;
        kode_petugas: string;
        zona_wilayah: string | null;
    } | null;
}

/** Angka kecil per menu, kunci = id item navigasi. */
export type NavBadges = Partial<Record<string, number>>;

/** Kartu status di bawah sidebar (PRD 10.1). */
export interface KartuStatus {
    periode: { kode: string; terbuka: boolean } | null;
    absensi: { status: StatusAbsensi | 'belum'; jam: string | null } | null;
    wilayah: string | null;
    biller_aktif: number | null;
}

export interface SharedProps {
    appName: string;
    auth: { user: AuthUser | null };
    kartuStatus: KartuStatus | null;
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

export type Kategori = 'Grade A' | 'Perlu Evaluasi' | 'Kritis';

export interface NilaiPascabayar {
    jml_plg: number;
    terbaca: number;
    foto: number;
    realisasi: number;
    eviden: number;
    kecepatan: { terbaca: number; hari_aktif: number; plg_per_jam: number; nilai: number };
    bobot: { realisasi: number; eviden: number; kecepatan: number };
    nilai_dasar: number;
    baca_ulang: { kasus_salah: number; sisipan_dikecualikan: number; rasio: number };
    ap2t: { kasus_salah: number; rasio: number };
    nilai: number;
}

export interface NilaiPrabayar {
    pemeriksaan: number;
    normal_terjadwal: number;
    koordinat_valid: number;
    realisasi: number;
    eviden: number;
    nilai: number;
}

/** Bentuk respons skor satu biller (/billers/{id}/score dan /me/score, PRD 8). */
export interface SkorBiller {
    periode: string;
    biller: {
        id: number;
        nama: string;
        nip: string | null;
        login_acmt: string;
        kode_petugas: string;
        wilayah: string | null;
    };
    nilai_per_modul: { pascabayar?: NilaiPascabayar; prabayar?: NilaiPrabayar };
    jumlah_modul: number;
    rata_rata_modul: number | null;
    bonus_hari_j: { terbaca: number; jml_plg: number; persen_tuntas: number | null; nilai: number };
    final_score: number | null;
    peringkat: number | null;
    jumlah_biller: number;
    kategori: Kategori | null;
    is_final: boolean;
    dihitung_at: string | null;
    jadwal_prabayar: [string, string];
    aturan: {
        w_realisasi: number;
        w_eviden: number;
        w_kecepatan: number;
        sla_plg_per_jam: number;
        jam_kerja_per_hari: number;
        bobot_bonus_j: number;
        ambang_grade_a: number;
        ambang_kritis: number;
    } | null;
}

export interface TitikTren {
    periode: string;
    label: string;
    final_score: number | null;
    peringkat: number | null;
}

/** Baris Rincian Kesalahan: kasus Baca Ulang salah atau koreksi AP2T. */
export interface Kesalahan {
    id: string;
    idpel: string;
    kode_rbm: string | null;
    sumber: 'Baca Ulang' | 'AP2T';
    stan_awal: number | null;
    stan_bu: number | null;
    hasil: string;
    hasil_label: string;
    dihitung: boolean;
    keterangan: string | null;
    petugas_login: string | null;
    tgl_baca: string | null;
    tgl_baca_bu: string | null;
}

export interface HistoriPasca {
    id: number;
    hari_baca: string;
    kode_rbm: string;
    jenis_meter: 'tunggal' | 'ganda_industri';
    jml_plg: number;
    terbaca: number;
    foto: number;
    mulai_at: string | null;
    selesai_at: string | null;
    hari_aktif: number | null;
    plg_per_jam: number | null;
}

export interface HistoriPra {
    id: number;
    tgl_baca: string | null;
    kdrbm: string | null;
    idpel: string;
    no_meter: string | null;
    kd_baca: string | null;
    latitude: number | null;
    longitude: number | null;
    koordinat_valid: boolean;
    status: 'sesuai' | 'di_luar_jadwal' | 'kode_tidak_normal';
}

export type StatusAbsensi = 'hadir' | 'izin' | 'sakit' | 'alpa';

export interface BarisAbsensi {
    id: number;
    tanggal: string;
    status: StatusAbsensi;
    absen_at: string | null;
    lat: number | null;
    lng: number | null;
    keterangan: string | null;
    sumber: 'mandiri' | 'sistem';
    dikoreksi: boolean;
    alasan_diubah: string | null;
}

export interface RingkasanAbsensi {
    hadir: number;
    izin: number;
    sakit: number;
    alpa: number;
    koreksi: number;
}

/** Orang di pantauan harian / rekap bulanan absensi. */
export interface OrangAbsensi {
    user_id: number;
    nama: string;
    nip: string;
    username: string;
    peran: Role;
    peran_label: string;
    wilayah: string | null;
}

export interface BarisLeaderboard {
    biller_id: number;
    nama: string;
    sektor: string | null;
    peringkat: number;
    nip?: string | null;
    login_acmt?: string;
    final_score?: number;
    pascabayar?: number | null;
    prabayar?: number | null;
    bonus_hari_j?: number;
    kategori?: Kategori;
    rasio_bu?: number | null;
    rasio_ap2t?: number | null;
    realisasi?: number | null;
    plg_per_jam?: number | null;
}

export interface StatistikAbsensi {
    total: number;
    hadir: number;
    izin: number;
    sakit: number;
    alpa: number;
    belum: number;
}
