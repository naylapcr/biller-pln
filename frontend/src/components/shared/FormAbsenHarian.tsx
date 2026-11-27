import { useForm } from '@inertiajs/react';
import { Camera, FileText, LoaderCircle, LocateFixed, LogOut, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { ChipTepatWaktu, JarakKantor, LinkFoto } from '@/components/shared/BuktiAbsensi';
import FormField from '@/components/ui/FormField';
import Kartu from '@/components/ui/Kartu';
import Notice from '@/components/ui/Notice';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { formatNumber, formatTanggal } from '@/lib/format';
import { STATUS_ABSENSI } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { BarisAbsensi } from '@/types';

type Pilihan = 'hadir' | 'izin' | 'sakit';

export interface HariIniAbsensi {
    tanggal: string;
    jam_server: string;
    hari_kerja: boolean;
    absen: BarisAbsensi | null;
}

export interface AturanAbsensi {
    kantor: { lat: number | null; lng: number | null; radius: number; ada: boolean };
    akurasi_maks_m: number;
    foto_maks_kb: number;
    bobot_kehadiran: number;
    nilai_terlambat: number;
    jam_masuk: string;
    jam_pulang: string;
}

const jamTitik = (jam: string) => jam.replace(':', '.');

/** Jarak dua titik (meter), sama dengan rumus haversine di server. */
function jarakMeter(lat1: number, lng1: number, lat2: number, lng2: number) {
    const rad = (d: number) => (d * Math.PI) / 180;
    const a = Math.sin(rad(lat2 - lat1) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2;
    return 2 * 6371000 * Math.asin(Math.min(1, Math.sqrt(a)));
}

const teksJarak = (m: number) => (m >= 1000 ? `${formatNumber(m / 1000, 1)} km` : `${formatNumber(m)} m`);

type Lokasi = { keadaan: 'meminta' } | { keadaan: 'ditolak'; pesan: string } | { keadaan: 'ada'; lat: number; lng: number; akurasi: number };

/** Lokasi browser untuk absen masuk/pulang; dicari otomatis saat kantor sudah diatur. */
function useLokasi(aktif: boolean) {
    const [lokasi, setLokasi] = useState<Lokasi>({ keadaan: 'meminta' });

    const cari = useCallback(() => {
        if (!('geolocation' in navigator)) {
            setLokasi({ keadaan: 'ditolak', pesan: 'Browser ini tidak mendukung lokasi. Gunakan HP dengan GPS.' });
            return;
        }
        setLokasi({ keadaan: 'meminta' });
        navigator.geolocation.getCurrentPosition(
            (p) => setLokasi({ keadaan: 'ada', lat: Number(p.coords.latitude.toFixed(7)), lng: Number(p.coords.longitude.toFixed(7)), akurasi: Math.round(p.coords.accuracy) }),
            (e) =>
                setLokasi({
                    keadaan: 'ditolak',
                    pesan: e.code === e.PERMISSION_DENIED ? 'Akses lokasi ditolak. Izinkan lokasi untuk situs ini di pengaturan browser.' : 'Lokasi belum didapat. Nyalakan GPS lalu Perbarui Lokasi.',
                }),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    }, []);

    useEffect(() => {
        if (aktif) cari();
    }, [aktif, cari]);

    const koordinat = lokasi.keadaan === 'ada' ? { lat: lokasi.lat, lng: lokasi.lng, akurasi: lokasi.akurasi } : { lat: null, lng: null, akurasi: null };

    return { lokasi, cari, koordinat };
}

/** Chip tanggal dan jam berjalan di header; jam dimulai dari jam server. */
export function ChipJamServer({ hariIni }: { hariIni: HariIniAbsensi }) {
    const [jam, setJam] = useState(() => new Date(hariIni.jam_server));

    useEffect(() => {
        const selisih = new Date(hariIni.jam_server).getTime() - Date.now();
        const t = setInterval(() => setJam(new Date(Date.now() + selisih)), 1000);
        return () => clearInterval(t);
    }, [hariIni.jam_server]);

    return <StatusChip titik tone="sky" label={`${formatTanggal(jam, 'EEEE, d MMM yyyy')} · ${formatTanggal(jam, 'HH:mm:ss')} WIB`} />;
}

/** Banner aturan absensi. Absensi Koordinator tidak masuk skor karena skor hanya untuk Biller. */
export function CatatanAbsensi({ aturan, biller = true }: { aturan: AturanAbsensi; biller?: boolean }) {
    const jam = `Masuk paling lambat ${jamTitik(aturan.jam_masuk)} WIB (tanpa toleransi), pulang mulai ${jamTitik(aturan.jam_pulang)} WIB, keduanya dalam radius ${formatNumber(aturan.kantor.radius)} m dari kantor.`;

    return (
        <Notice tone="waiting" action={<StatusChip label="Masuk & Pulang" tone="waiting" className="bg-[#fef08a] text-[#854d0e]" />}>
            {biller
                ? `${jam} Kehadiran ikut skor kinerja (bobot ${formatNumber(aturan.bobot_kehadiran)}%): terlambat, pulang cepat, atau tidak absen pulang bernilai ${formatNumber(aturan.nilai_terlambat)}. Izin/sakit wajib lampiran. Tidak absen sampai 23:59 WIB otomatis Alpa.`
                : `${jam} Izin/sakit wajib lampiran. Tidak absen sampai 23:59 WIB otomatis tercatat Alpa.`}
        </Notice>
    );
}

/** Status lokasi untuk absen masuk/pulang: dalam radius, di luar radius, atau belum bisa dipastikan. */
function StatusLokasi({ lokasi, aturan, onMuatUlang }: { lokasi: Lokasi; aturan: AturanAbsensi; onMuatUlang: () => void }) {
    const { kantor } = aturan;
    let tone: 'success' | 'danger' | 'waiting' = 'waiting';
    let judul = 'Mencari lokasi Anda…';
    let rinci = 'Izinkan akses lokasi di browser bila diminta.';

    if (!kantor.ada) {
        judul = 'Lokasi kantor belum diatur';
        rinci = 'Super Admin perlu mengisi titik kantor di Periode & Parameter.';
    } else if (lokasi.keadaan === 'ditolak') {
        tone = 'danger';
        judul = 'Lokasi tidak tersedia';
        rinci = lokasi.pesan;
    } else if (lokasi.keadaan === 'ada') {
        const jarak = jarakMeter(kantor.lat!, kantor.lng!, lokasi.lat, lokasi.lng);
        const kabur = lokasi.akurasi > aturan.akurasi_maks_m;
        tone = kabur ? 'waiting' : jarak <= kantor.radius ? 'success' : 'danger';
        judul = kabur ? 'Sinyal GPS kurang akurat' : jarak <= kantor.radius ? 'Dalam radius kantor' : 'Belum di dalam radius kantor';
        rinci = `${teksJarak(jarak)} dari kantor (batas ${formatNumber(kantor.radius)} m) · akurasi ±${formatNumber(lokasi.akurasi)} m`;
    }

    return (
        <div
            className={cn(
                'flex flex-wrap items-center gap-3 rounded-xl border px-3.5 py-3',
                tone === 'success' && 'border-success-line bg-success-row',
                tone === 'danger' && 'border-error-line bg-error-row',
                tone === 'waiting' && 'border-pln-yellow-line bg-pln-yellow-row',
            )}
        >
            <LocateFixed className={cn('size-5 shrink-0', tone === 'success' ? 'text-success' : tone === 'danger' ? 'text-pln-red' : 'text-pln-yellow-ink')} aria-hidden />
            <div className="min-w-0 flex-1">
                <p className="text-xs font-bold text-ink">{judul}</p>
                <p className="text-[11px] text-muted-ink">{rinci}</p>
            </div>
            {kantor.ada && lokasi.keadaan !== 'meminta' && (
                <Button type="button" size="sm" variant="secondary" onClick={onMuatUlang}>
                    Perbarui Lokasi
                </Button>
            )}
        </div>
    );
}

/** Pesan merah saat absen ditolak (di luar radius, GPS kabur, dsb.): absen tidak tersimpan. */
function GalatLokasi({ pesan }: { pesan?: string }) {
    if (!pesan) return null;

    return (
        <p role="alert" className="mt-2 rounded-10 border border-error-line bg-error-row px-3 py-2 text-xs font-semibold text-error-ink">
            {pesan}
        </p>
    );
}

/** Kartu setelah absen hari ini: hadir menampilkan tombol Absen Pulang sampai pulang tercatat. */
export function KartuTercatat({ absen, aturan, urlPulang }: { absen: BarisAbsensi; aturan?: AturanAbsensi; urlPulang?: string }) {
    const perluPulang = absen.status === 'hadir' && absen.absen_at !== null && absen.pulang_at === null && !!urlPulang && !!aturan;
    const { lokasi, cari, koordinat } = useLokasi(perluPulang && !!aturan?.kantor.ada);
    const form = useForm<{ lat: number | null; lng: number | null; akurasi: number | null }>({ lat: null, lng: null, akurasi: null });
    const galat = (form.errors as Record<string, string | undefined>).lokasi ?? (form.errors as Record<string, string | undefined>).status;

    return (
        <section className="grid gap-4 rounded-2xl border-[1.5px] border-success-line bg-success-row px-[22px] py-5">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="min-w-0">
                    <span className="flex flex-wrap gap-1.5">
                        <StatusChip titik tone="success" label={`TERCATAT ${STATUS_ABSENSI[absen.status].label.toUpperCase()} HARI INI`} />
                        <ChipTepatWaktu terlambat={absen.terlambat_menit} cepat={absen.pulang_cepat_menit} />
                    </span>
                    <p className="mt-1.5 text-[17px] leading-[22px] font-extrabold text-[#065f46]">
                        {absen.sumber === 'mandiri' ? 'Absen masuk' : 'Absensi dicatat sistem'}
                        {absen.absen_at && ` ${formatTanggal(absen.absen_at, 'HH:mm:ss')} WIB`}
                        {absen.pulang_at && ` · Absen pulang ${formatTanggal(absen.pulang_at, 'HH:mm:ss')} WIB`}
                    </p>
                    <p className="mt-1 max-w-[560px] text-xs leading-[15px] text-success-dark">
                        {absen.status === 'hadir' && !absen.pulang_at
                            ? `Jangan lupa absen pulang mulai pukul ${jamTitik(aturan?.jam_pulang ?? '16:30')} WIB dari kantor. Tidak absen pulang mengurangi nilai kehadiran.`
                            : 'Sesuai aturan sistem (1× sehari), percobaan absen kedua ditolak; koreksi hanya lewat Super Admin.'}
                        {absen.keterangan && ` Keterangan: ${absen.keterangan}.`}
                    </p>
                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-success-dark">
                        {absen.lat !== null && <JarakKantor lat={absen.lat} lng={absen.lng} jarak={absen.jarak_m} />}
                        {absen.foto_url && <LinkFoto url={absen.foto_url} pdf={absen.lampiran_pdf} />}
                    </div>
                </div>
                {!perluPulang && <span className="rounded-10 bg-[#d1fae5] px-4 py-2.5 text-xs font-bold text-success-dark">Absen Terkunci ✓</span>}
            </div>

            {perluPulang && aturan && (
                <form
                    className="grid gap-3 rounded-xl bg-white p-3.5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform(() => koordinat);
                        form.post(urlPulang!, { preserveScroll: true });
                    }}
                >
                    <StatusLokasi lokasi={lokasi} aturan={aturan} onMuatUlang={cari} />
                    <GalatLokasi pesan={galat} />
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing || !aturan.kantor.ada}>
                            {form.processing ? <LoaderCircle className="animate-spin" /> : <LogOut />}
                            Absen Pulang
                        </Button>
                    </div>
                </form>
            )}
        </section>
    );
}

/**
 * Form absen harian (01.2 dan 02.6): Hadir = absen masuk dari dalam radius kantor; di luar radius tombol tetap
 * bisa ditekan tetapi server menolak dan pesan merah tampil. Izin/Sakit wajib keterangan dan lampiran (foto/PDF).
 */
export default function FormAbsenHarian({ hariIni, url, aturan }: { hariIni: HariIniAbsensi; url: string; aturan: AturanAbsensi }) {
    const form = useForm<{ status: Pilihan; keterangan: string; foto: File | null }>({ status: 'hadir', keterangan: '', foto: null });
    const { lokasi, cari, koordinat } = useLokasi(aturan.kantor.ada);
    const [pratinjau, setPratinjau] = useState<string | null>(null);

    useEffect(() => () => void (pratinjau && URL.revokeObjectURL(pratinjau)), [pratinjau]);

    const status = form.data.status;
    const hadir = status === 'hadir';
    const galatLokasi = (form.errors as Record<string, string | undefined>).lokasi;
    const pdf = form.data.foto?.type === 'application/pdf';
    const keterangan: Record<Pilihan, string> = { hadir: `Masuk s.d. ${jamTitik(aturan.jam_masuk)} · di kantor`, izin: 'Keterangan + lampiran', sakit: 'Keterangan + lampiran' };

    const pilihFoto = (f: File | null) => {
        form.setData('foto', f);
        setPratinjau(f && f.type.startsWith('image/') ? URL.createObjectURL(f) : null);
    };

    return (
        <Kartu judul="Form Absensi Hari Ini" className="p-[22px]">
            <p className="text-xs text-muted-ink">Pilih status kehadiran Anda hari ini. Izin atau Sakit wajib keterangan dan lampiran bukti (foto atau PDF).</p>
            {!hariIni.hari_kerja && <p className="text-xs font-semibold text-pln-yellow-ink">Hari ini bukan hari kerja. Anda tetap bisa absen bila sedang bertugas.</p>}
            <form
                className="grid gap-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((d) => (d.status === 'hadir' ? { status: d.status, keterangan: d.keterangan, ...koordinat } : d));
                    form.post(url, { preserveScroll: true, forceFormData: true });
                }}
                noValidate
            >
                <div>
                    <div role="radiogroup" aria-label="Status hari ini" className="grid grid-cols-3 gap-2 sm:gap-3">
                        {(['hadir', 'izin', 'sakit'] as Pilihan[]).map((s) => (
                            <button
                                key={s}
                                type="button"
                                role="radio"
                                aria-checked={status === s}
                                onClick={() => form.setData('status', s)}
                                className={cn(
                                    'min-h-[66px] rounded-xl px-3 py-3 text-left transition-colors sm:px-4',
                                    status === s ? 'bg-success-row ring-2 ring-success-line ring-inset' : 'bg-white ring-1 ring-line-strong ring-inset hover:bg-canvas',
                                )}
                            >
                                <span className={cn('block text-sm font-bold', status === s ? 'text-success' : 'text-slate-ink')}>{STATUS_ABSENSI[s].label}</span>
                                <span className="mt-0.5 block text-[11px] text-muted-ink">{keterangan[s]}</span>
                            </button>
                        ))}
                    </div>
                    {form.errors.status && <p className="mt-2 text-xs text-error-ink">{form.errors.status}</p>}
                </div>

                {hadir ? (
                    <div>
                        <StatusLokasi lokasi={lokasi} aturan={aturan} onMuatUlang={cari} />
                        <GalatLokasi pesan={galatLokasi} />
                    </div>
                ) : (
                    <FormField
                        id="foto"
                        label={`Lampiran Bukti ${STATUS_ABSENSI[status].label} (Wajib · JPG/PNG/WEBP/PDF maks. ${formatNumber(aturan.foto_maks_kb / 1024)} MB)`}
                        error={form.errors.foto}
                        hint="Contoh: surat dokter atau surat izin. Bisa difoto langsung dengan kamera HP atau unggah berkas."
                    >
                        {form.data.foto ? (
                            <div className="flex items-center gap-3 rounded-xl border border-line-strong bg-canvas p-2.5">
                                {pratinjau ? (
                                    <img src={pratinjau} alt="Pratinjau lampiran" className="size-16 rounded-lg object-cover" />
                                ) : (
                                    <span className="inline-flex size-16 items-center justify-center rounded-lg bg-error-row text-error-ink">
                                        <FileText className="size-7" aria-hidden />
                                    </span>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-xs font-semibold text-ink">{form.data.foto.name}</p>
                                    <p className="text-[11px] text-muted-ink">
                                        {pdf ? 'PDF · ' : ''}
                                        {formatNumber(form.data.foto.size / 1024)} KB
                                    </p>
                                </div>
                                <Button type="button" size="sm" variant="ghost" onClick={() => pilihFoto(null)} aria-label="Hapus lampiran">
                                    <X />
                                </Button>
                            </div>
                        ) : (
                            <label
                                htmlFor="foto"
                                className="flex cursor-pointer items-center justify-center gap-2 rounded-xl border-[1.5px] border-dashed border-line-strong bg-canvas px-4 py-5 text-xs font-semibold text-slate-ink hover:border-pln-blue hover:text-pln-blue"
                            >
                                <Camera className="size-4" aria-hidden />
                                Ambil Foto / Pilih Berkas (Foto atau PDF)
                            </label>
                        )}
                        <input
                            id="foto"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            className="sr-only"
                            onChange={(e) => pilihFoto(e.target.files?.[0] ?? null)}
                        />
                    </FormField>
                )}

                <FormField id="keterangan" label="Keterangan (Wajib untuk Izin / Sakit · Opsional untuk Hadir)" error={form.errors.keterangan}>
                    <Textarea
                        id="keterangan"
                        rows={1}
                        placeholder={hadir ? 'Contoh: Apel pagi, lalu bertugas di Sektor Sukamaju' : 'Contoh: Demam, surat dokter terlampir'}
                        value={form.data.keterangan}
                        onChange={(e) => form.setData('keterangan', e.target.value)}
                        maxLength={300}
                    />
                </FormField>

                <div className="flex flex-wrap items-center justify-end gap-3">
                    <Button type="submit" variant="success" disabled={form.processing || (hadir && !aturan.kantor.ada)}>
                        {form.processing && <LoaderCircle className="animate-spin" />}
                        {hadir ? 'Kirim Absen Masuk' : `Kirim Absensi ${STATUS_ABSENSI[status].label}`}
                    </Button>
                </div>
            </form>
        </Kartu>
    );
}
