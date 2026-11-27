import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Calendar, LoaderCircle, LocateFixed } from 'lucide-react';
import { useState } from 'react';

import { ChipSyarat, type SyaratTutup } from '@/components/superadmin/KartuKesiapanTutup';
import ModalTutupPeriode from '@/components/superadmin/ModalTutupPeriode';
import PeriodeTerkunci, { type RingkasanFinal } from '@/components/superadmin/PeriodeTerkunci';
import EmptyState from '@/components/ui/EmptyState';
import FormDialog from '@/components/ui/FormDialog';
import FormField from '@/components/ui/FormField';
import Kartu from '@/components/ui/Kartu';
import Notice from '@/components/ui/Notice';
import PageHeader from '@/components/ui/PageHeader';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { layoutSuperAdmin } from '@/layouts/SuperAdminLayout/SuperAdminLayout';
import { formatNumber, formatTanggal, formatTanggalWaktu } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

interface Parameter {
    w_realisasi: number;
    w_eviden: number;
    w_kecepatan: number;
    jam_kerja_per_hari: number;
    sla_plg_per_jam: number;
    toleransi_kwh: number;
    kdbaca_normal: string[];
    prabayar_mulai: number;
    prabayar_selesai: number;
    bobot_bonus_j: number;
    bobot_kehadiran: number;
    nilai_izin_tanpa_foto: number;
    nilai_terlambat: number;
    jam_masuk: string;
    jam_pulang: string;
    kantor_lat: number | null;
    kantor_lng: number | null;
    radius_absen_m: number;
    ambang_grade_a: number;
    ambang_kritis: number;
    jumlah_top: number;
    jumlah_bottom: number;
}

type Props = PageProps<{
    daftar: { kode: string; nama: string; terbuka: boolean }[];
    periode: {
        kode: string;
        nama: string;
        terbuka: boolean;
        ditutup_at: string | null;
        penutup: string | null;
        jadwal_prabayar: [string, string];
        batch_committed: string[];
        jumlah_skor: number;
    } | null;
    parameter: Parameter | null;
    kesiapan: SyaratTutup[];
    ringkasanFinal: RingkasanFinal | null;
    diubah: Record<string, { oleh: string | null; waktu: string | null }>;
}>;

/** 03.4 Periode & Parameter, dan 04.2 Periode Ditutup. */
export default function PeriodeParameter() {
    const { daftar, periode, parameter, kesiapan, ringkasanFinal, diubah } = usePage<Props>().props;
    const [tutup, setTutup] = useState(false);
    const [buka, setBuka] = useState(false);
    const [hitung, setHitung] = useState(false);

    if (!periode || !parameter) {
        return (
            <>
                <Head title="Periode & Parameter" />
                <PageHeader title="Periode & Parameter Skor (periods · scoring_rules)" />
                <EmptyState icon={Calendar} title="Belum ada periode" action={<Button onClick={() => setBuka(true)}>Buka Periode</Button>}>
                    Buka periode BLTH pertama. Parameter diisi dari nilai bawaan desain.
                </EmptyState>
                <DialogBukaPeriode open={buka} onOpenChange={setBuka} />
            </>
        );
    }

    const pilihPeriode = (
        <NativeSelect className="w-52" aria-label="Periode" value={periode.kode} onChange={(e) => router.get(route('pengaturan.periode.index'), { periode: e.target.value })}>
            {daftar.map((p) => (
                <option key={p.kode} value={p.kode}>
                    BLTH {p.kode} · {p.terbuka ? 'Terbuka' : 'Ditutup'}
                </option>
            ))}
        </NativeSelect>
    );

    if (!periode.terbuka) {
        return (
            <>
                <Head title={`Periode ${periode.kode}`} />
                <PageHeader
                    title={`BLTH ${periode.kode} (${periode.nama}) — TERKUNCI FINAL`}
                    description={`Ditutup pada ${formatTanggalWaktu(periode.ditutup_at)} oleh ${periode.penutup ?? '–'} (Super Admin)`}
                    actions={
                        <>
                            {pilihPeriode}
                            <StatusChip label="Terkunci · is_final = true" tone="neutral" className="bg-line text-slate-ink" />
                            <Button variant="success" asChild>
                                <a href={route('pengaturan.periode.unduh', { kode: periode.kode, format: 'xlsx' })}>Ekspor Laporan Final XLSX</a>
                            </Button>
                            <Button asChild>
                                <a href={route('pengaturan.periode.unduh', { kode: periode.kode, format: 'pdf' })}>Ekspor PDF Resmi</a>
                            </Button>
                        </>
                    }
                />
                <div className="grid gap-[18px]">
                    <PeriodeTerkunci
                        kode={periode.kode}
                        nama={periode.nama}
                        jumlah={periode.jumlah_skor}
                        bobot={`${formatNumber(parameter.w_realisasi)}/${formatNumber(parameter.w_eviden)}/${formatNumber(parameter.w_kecepatan)} · Kehadiran ${formatNumber(parameter.bobot_kehadiran ?? 0)}% · Bonus J +${formatNumber(parameter.bobot_bonus_j, 1)} · Ambang ${formatNumber(parameter.ambang_grade_a)}/${formatNumber(parameter.ambang_kritis)}`}
                        ringkasan={ringkasanFinal}
                    />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Periode & Parameter" />
            <PageHeader
                title="Periode & Parameter Skor (periods · scoring_rules)"
                description="Konfigurasi bobot per periode BLTH · Setiap perubahan otomatis menghitung ulang skor 25 Biller"
                actions={
                    <>
                        <Button
                            variant="secondary"
                            disabled={hitung}
                            onClick={() =>
                                router.post(route('pengaturan.periode.recalculate', periode.kode), {}, { preserveScroll: true, onStart: () => setHitung(true), onFinish: () => setHitung(false) })
                            }
                        >
                            {hitung && <LoaderCircle className="animate-spin" />}
                            Hitung Ulang Skor Sekarang
                        </Button>
                        <Button variant="destructive" onClick={() => setTutup(true)}>
                            Tutup Periode & Kunci Final
                        </Button>
                    </>
                }
            />

            <div className="grid gap-4">
                <section className="flex flex-wrap items-center gap-3 rounded-2xl border border-line-strong bg-white px-[18px] py-3">
                    <p className="text-xs font-medium text-muted-ink">Periode Aktif:</p>
                    <p className="text-[15px] font-bold text-ink">
                        BLTH {periode.kode} ({periode.nama})
                    </p>
                    <StatusChip titik label="Status: Terbuka" tone="success" />
                    <p className="min-w-0 flex-1 basis-60 text-[11px] font-bold text-pln-blue xl:text-right">Formula: Skor Akhir = (100% − b) × Rata-rata Modul + b × Kehadiran + Bonus Hari J (maks. 100)</p>
                    {daftar.length > 1 && pilihPeriode}
                    <Button variant="secondary" onClick={() => setBuka(true)}>
                        + Buka Periode
                    </Button>
                </section>

                <FormParameter key={periode.kode} kode={periode.kode} parameter={parameter} jadwal={periode.jadwal_prabayar} diubah={diubah} />

                <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border-[1.5px] border-[#ef4444] bg-error-row px-5 py-[18px]">
                    <div className="min-w-0 flex-1 basis-96">
                        <h2 className="text-section-title text-error-deep">5. Gerbang Penutupan Periode (Tutup Periode BLTH {periode.kode})</h2>
                        <p className="mt-0.5 mb-3 text-xs text-error-deep">Setelah ditutup, skor final dikunci (is_final = true). Unggah data, rollback, dan ubah parameter akan dinonaktifkan.</p>
                        <ChipSyarat syarat={kesiapan} />
                    </div>
                    <Button variant="destructive" onClick={() => setTutup(true)}>
                        Kunci & Tutup Periode BLTH {periode.kode}
                    </Button>
                </section>
            </div>

            <ModalTutupPeriode open={tutup} onOpenChange={setTutup} kode={periode.kode} syarat={kesiapan} />
            <DialogBukaPeriode open={buka} onOpenChange={setBuka} />
        </>
    );
}

PeriodeParameter.layout = layoutSuperAdmin;

/** Empat kartu parameter: bobot pascabayar, bonus Hari J dan jadwal prabayar, kehadiran dan lokasi absen, ambang kategori. */
function FormParameter({ kode, parameter, jadwal, diubah }: { kode: string; parameter: Parameter; jadwal: [string, string]; diubah: Props['diubah'] }) {
    const form = useForm({
        ...parameter,
        kdbaca_normal: parameter.kdbaca_normal.join(', '),
        kantor_lat: parameter.kantor_lat === null ? '' : String(parameter.kantor_lat),
        kantor_lng: parameter.kantor_lng === null ? '' : String(parameter.kantor_lng),
    });
    const [lokasiSaya, setLokasiSaya] = useState<{ memuat: boolean; pesan: string | null }>({ memuat: false, pesan: null });
    const pakaiLokasiSaya = () => {
        if (!('geolocation' in navigator)) {
            setLokasiSaya({ memuat: false, pesan: 'Browser ini tidak mendukung lokasi.' });
            return;
        }
        setLokasiSaya({ memuat: true, pesan: null });
        navigator.geolocation.getCurrentPosition(
            (p) => {
                form.setData((d) => ({ ...d, kantor_lat: p.coords.latitude.toFixed(7), kantor_lng: p.coords.longitude.toFixed(7) }));
                setLokasiSaya({ memuat: false, pesan: `Titik terisi (akurasi ±${Math.round(p.coords.accuracy)} m). Tekan "Simpan Lokasi Kantor" agar berlaku untuk absensi.` });
            },
            () => setLokasiSaya({ memuat: false, pesan: 'Lokasi tidak didapat. Izinkan akses lokasi lalu coba lagi.' }),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    };
    const total = Number(form.data.w_realisasi) + Number(form.data.w_eviden) + Number(form.data.w_kecepatan);
    // Titik kantor baru berlaku setelah disimpan; tampilkan statusnya agar tidak terlewat.
    const titikTersimpan = parameter.kantor_lat !== null && parameter.kantor_lng !== null;
    const beda = (isi: unknown, simpan: number | null) =>
        String(isi ?? '').trim() === '' ? simpan !== null : simpan === null || Math.abs(Number(isi) - simpan) > 1e-7;
    const titikBerubah = beda(form.data.kantor_lat, parameter.kantor_lat) || beda(form.data.kantor_lng, parameter.kantor_lng);
    const adaGalat = Object.keys(form.errors).length > 0;

    const isian = (k: keyof Parameter, opsi: { step?: string; satuan?: string; className?: string } = {}) => (
        <div className="relative">
            <Input
                id={k}
                type="number"
                step={opsi.step ?? 'any'}
                title={diubah[k] ? `Diubah ${diubah[k].oleh} · ${formatTanggalWaktu(diubah[k].waktu)}` : undefined}
                className={cn('[appearance:textfield] pr-12 font-mono font-bold [&::-webkit-inner-spin-button]:appearance-none', opsi.className)}
                value={String(form.data[k as keyof typeof form.data] ?? '')}
                onChange={(e) => form.setData(k as never, e.target.value as never)}
            />
            {opsi.satuan && <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 font-mono text-xs text-muted-ink">{opsi.satuan}</span>}
        </div>
    );
    const angka = (k: keyof Parameter, label: string, opsi: { step?: string; satuan?: string } = {}) => (
        <FormField id={k} label={label} error={form.errors[k as keyof typeof form.errors]}>
            {isian(k, opsi)}
        </FormField>
    );

    const simpan = () => {
        form.transform((d) => ({
            ...d,
            kdbaca_normal: String(d.kdbaca_normal).split(',').map((s) => s.trim()).filter(Boolean),
            kantor_lat: String(d.kantor_lat).trim() === '' ? null : d.kantor_lat,
            kantor_lng: String(d.kantor_lng).trim() === '' ? null : d.kantor_lng,
        }));
        form.put(route('pengaturan.periode.rules', kode), { preserveScroll: true });
    };

    return (
        <form
            className="grid gap-4"
            onSubmit={(e) => {
                e.preventDefault();
                simpan();
            }}
        >
            {adaGalat && (
                <Notice tone="danger">
                    Parameter belum tersimpan. Periksa isian bertanda merah: {Object.values(form.errors).join(' ')}
                </Notice>
            )}
            <div className="grid items-start gap-4 xl:grid-cols-2">
                <Kartu judul={`1. Bobot Modul Pascabayar (Total = ${formatNumber(total)}%)`}>
                    <p className="text-[11px] leading-[14px] text-muted-ink">Nilai dipotong terpisah oleh rasio Baca Ulang & AP2T: nilai × (1 − kasus salah ÷ total plg).</p>
                    <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        {angka('w_realisasi', 'w_realisasi / w_stan', { satuan: '%' })}
                        {angka('w_eviden', 'w_eviden (Foto)', { satuan: '%' })}
                        {angka('w_kecepatan', 'w_kecepatan', { satuan: '%' })}
                        {angka('sla_plg_per_jam', 'Standar plg/jam')}
                        {angka('jam_kerja_per_hari', 'Jam kerja / hari', { satuan: 'jam' })}
                        {angka('toleransi_kwh', 'Toleransi Baca Ulang', { satuan: 'kWh' })}
                    </div>
                    {Math.abs(total - 100) > 0.01 && <p className="text-[11px] font-semibold text-pln-red">Total bobot harus 100%, sekarang {formatNumber(total)}%.</p>}
                    <p className="text-[11px] leading-[14px] text-sky-ink">Standar kecepatan baku: 33,3 pelanggan/jam (200 plg / 6 jam) dari TGL MULAI–SELESAI ACMT.</p>
                </Kartu>

                <Kartu judul="2. Bonus Hari J (Wajib) & Jadwal Prabayar">
                    <p className="text-[11px] leading-[14px] text-muted-ink">
                        Bonus = pelanggan Hari J terbaca ÷ total pelanggan biller × 100, paling besar sesuai bobot (5 dari 1.493 → +0,33). Jadwal prabayar periode ini:{' '}
                        {formatTanggal(jadwal[0], 'd MMM')} – {formatTanggal(jadwal[1], 'd MMM yyyy')}.
                    </p>
                    <div className="grid grid-cols-2 gap-2.5">
                        {angka('bobot_bonus_j', 'Bobot Maks. Bonus Hari J *', { satuan: 'Poin' })}
                        <FormField id="kdbaca_normal" label="KDBACA Normal (pisahkan koma)" error={form.errors.kdbaca_normal}>
                            <Input id="kdbaca_normal" className="font-mono font-bold" value={form.data.kdbaca_normal} onChange={(e) => form.setData('kdbaca_normal', e.target.value)} />
                        </FormField>
                        {angka('prabayar_mulai', 'Jadwal Prabayar Mulai (Tgl)', { step: '1' })}
                        {angka('prabayar_selesai', 'Jadwal Prabayar Selesai (Tgl)', { step: '1' })}
                    </div>
                    <StatusChip label="Wajib terisi sebelum periode pertama ditutup" tone="waiting" className="max-w-full whitespace-normal" />
                </Kartu>
            </div>

            <Kartu
                judul="3. Kehadiran & Lokasi Absen"
                aksi={
                    titikBerubah ? (
                        <StatusChip titik tone="waiting" label="Titik kantor belum disimpan" />
                    ) : titikTersimpan ? (
                        <StatusChip titik tone="success" label="Titik kantor tersimpan ✓" />
                    ) : (
                        <StatusChip titik tone="danger" label="Titik kantor belum diatur" />
                    )
                }
            >
                <p className="text-[11px] leading-[14px] text-muted-ink">
                    Nilai kehadiran = rata-rata hari kerja di bulan kerja (bulan sebelum BLTH): hadir tepat waktu 100; terlambat, pulang cepat, atau tidak absen pulang sesuai
                    isian; izin/sakit berlampiran 100 (tanpa lampiran sesuai isian); alpa 0. Absen masuk dan pulang hanya diterima dalam radius dari titik kantor.
                </p>
                <div className="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                    {angka('bobot_kehadiran', 'Bobot Kehadiran *', { satuan: '%' })}
                    {angka('nilai_terlambat', 'Terlambat / Pulang Cepat', { satuan: '/100' })}
                    {angka('nilai_izin_tanpa_foto', 'Izin/Sakit tanpa Lampiran', { satuan: '/100' })}
                    {angka('radius_absen_m', 'Radius Absen', { step: '1', satuan: 'm' })}
                    {(['jam_masuk', 'jam_pulang'] as const).map((k) => (
                        <FormField key={k} id={k} label={k === 'jam_masuk' ? 'Jam Masuk (tanpa toleransi)' : 'Jam Pulang'} error={form.errors[k]}>
                            <Input id={k} type="time" className="font-mono font-bold" value={String(form.data[k] ?? '')} onChange={(e) => form.setData(k, e.target.value)} />
                        </FormField>
                    ))}
                    {angka('kantor_lat', 'Lintang Kantor (lat)', { step: 'any' })}
                    {angka('kantor_lng', 'Bujur Kantor (lng)', { step: 'any' })}
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <Button type="button" variant="secondary" onClick={pakaiLokasiSaya} disabled={lokasiSaya.memuat}>
                        {lokasiSaya.memuat ? <LoaderCircle className="animate-spin" /> : <LocateFixed />}
                        Pakai Lokasi Saya Sekarang
                    </Button>
                    {form.data.kantor_lat !== '' && form.data.kantor_lng !== '' && (
                        <a
                            href={`https://www.google.com/maps/search/?api=1&query=${form.data.kantor_lat},${form.data.kantor_lng}`}
                            target="_blank"
                            rel="noreferrer"
                            className="text-xs font-semibold text-pln-blue hover:underline"
                        >
                            Cek titik di Google Maps
                        </a>
                    )}
                    <p className={cn('min-w-0 flex-1 basis-60 text-[11px]', titikBerubah ? 'font-semibold text-pln-yellow-ink' : 'text-muted-ink')}>
                        {lokasiSaya.pesan ??
                            (titikBerubah
                                ? 'Titik kantor sudah diubah tetapi belum disimpan. Absen biller masih memakai titik lama sampai disimpan.'
                                : titikTersimpan
                                  ? 'Titik kantor sudah berlaku untuk absen masuk dan pulang.'
                                  : 'Titik kantor belum diisi: absen hadir ditolak. Buka halaman ini dari kantor, tekan "Pakai Lokasi Saya Sekarang", lalu Simpan.')}
                    </p>
                    <Button type="submit" disabled={form.processing} className="sm:ml-auto">
                        {form.processing && <LoaderCircle className="animate-spin" />}
                        Simpan Lokasi Kantor
                    </Button>
                </div>
            </Kartu>

            <Kartu
                judul="4. Ambang Kategori Penilaian (Wajib Sebelum Tutup Periode)"
                aksi={
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <LoaderCircle className="animate-spin" />}
                        Simpan Parameter & Hitung Ulang
                    </Button>
                }
            >
                <div className="grid gap-3.5 md:grid-cols-3">
                    <div className="rounded-xl border border-[#86efac] bg-success-row p-4">
                        <label htmlFor="ambang_grade_a" className="text-[11px] font-bold text-success-ink">
                            Baik
                        </label>
                        <div className="mt-1 flex items-center gap-2 font-mono text-base font-bold whitespace-nowrap text-success-ink">
                            Skor ≥ {isian('ambang_grade_a', { className: 'h-9 w-24 bg-white pr-3 text-base text-success-ink' })}
                        </div>
                        {form.errors.ambang_grade_a && <p className="mt-1 text-[11px] text-error-ink">{form.errors.ambang_grade_a}</p>}
                    </div>
                    <div className="rounded-xl border border-pln-yellow-line bg-pln-yellow-row p-4">
                        <p className="text-[11px] font-bold text-pln-yellow-ink">Cukup</p>
                        <p className="mt-1 flex h-9 items-center font-mono text-base font-bold text-pln-yellow-ink">
                            {formatNumber(Number(form.data.ambang_kritis), 1)} – {formatNumber(Number(form.data.ambang_grade_a) - 0.1, 1)}
                        </p>
                    </div>
                    <div className="rounded-xl border border-error-line bg-error-row p-4">
                        <label htmlFor="ambang_kritis" className="text-[11px] font-bold text-error-ink">
                            Perlu Pendampingan
                        </label>
                        <div className="mt-1 flex items-center gap-2 font-mono text-base font-bold whitespace-nowrap text-error-ink">
                            Skor &lt; {isian('ambang_kritis', { className: 'h-9 w-24 bg-white pr-3 text-base text-error-ink' })}
                        </div>
                        {form.errors.ambang_kritis && <p className="mt-1 text-[11px] text-error-ink">{form.errors.ambang_kritis}</p>}
                    </div>
                </div>
                <div className="grid max-w-sm grid-cols-2 gap-2.5">
                    {angka('jumlah_top', 'Jumlah Teratas', { step: '1' })}
                    {angka('jumlah_bottom', 'Jumlah Terbawah', { step: '1' })}
                </div>
            </Kartu>
        </form>
    );
}

function DialogBukaPeriode({ open, onOpenChange }: { open: boolean; onOpenChange: (v: boolean) => void }) {
    const form = useForm({ kode: '' });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            chip={{ label: 'SUPER ADMIN · TABEL PERIODS', tone: 'info' }}
            title="Buka Periode Baru"
            description="Parameter disalin dari periode sebelumnya."
            submitLabel="Buka Periode"
            processing={form.processing}
            onSubmit={() => form.post(route('pengaturan.periode.store'), { onSuccess: () => onOpenChange(false) })}
        >
            <FormField id="kode" label="Kode BLTH" required error={form.errors.kode} hint="Contoh 202612">
                <Input id="kode" className="font-mono" inputMode="numeric" maxLength={6} value={form.data.kode} onChange={(e) => form.setData('kode', e.target.value)} />
            </FormField>
        </FormDialog>
    );
}
