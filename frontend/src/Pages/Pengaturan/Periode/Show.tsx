import { router, useForm, usePage } from '@inertiajs/react';
import { Lock, LoaderCircle, RefreshCw } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

import ConfirmDialog from '@/Components/shared/ConfirmDialog';
import FormField from '@/Components/shared/FormField';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout';
import { formatTanggalWaktu } from '@/lib/format';
import type { PageProps } from '@/types';

interface Parameter {
    w_stan: number;
    w_eviden: number;
    mode_agregasi: 'jumlah' | 'rata_rata_modul';
    bonus_min_j: number;
    bonus_max_j: number;
    bonus_dasar_j: number | null;
    poin_per_plg_j: number | null;
    denda_bu_per_kasus: number;
    denda_ap2t_tingkat: { min: number; max: number; tingkat: Record<string, number> | [] };
    kdbaca_normal: string[];
    denda_alpa_per_hari: number;
    absensi_hari_kerja: number[];
    absensi_hari_libur: string[];
    ambang_evaluasi: number;
    jumlah_top: number;
    jumlah_bottom: number;
}

type Props = PageProps<{
    periode: { kode: string; nama: string; terbuka: boolean; ditutup_at: string | null; absensi_mulai: string | null; absensi_selesai: string | null };
    parameter: Parameter;
    bawaan: Parameter;
    wajib: string[];
    kosong: string[];
    diubah: Record<string, { oleh: string | null; waktu: string | null }>;
    batchMenunggu: number;
}>;

const s = (v: number | null | undefined) => (v === null || v === undefined ? '' : String(v));

export default function PeriodeShow() {
    const { periode, parameter: p, bawaan, kosong, diubah, batchMenunggu } = usePage<Props>().props;
    const [konfirmasiTutup, setKonfirmasiTutup] = useState(false);
    const [menutup, setMenutup] = useState(false);
    const terkunci = !periode.terbuka;

    const tingkatAwal = Object.entries(Array.isArray(p.denda_ap2t_tingkat.tingkat) ? {} : p.denda_ap2t_tingkat.tingkat)
        .map(([k, v]) => `${k}=${v}`)
        .join('\n');

    const form = useForm({
        w_stan: s(p.w_stan),
        w_eviden: s(p.w_eviden),
        mode_agregasi: p.mode_agregasi,
        bonus_min_j: s(p.bonus_min_j),
        bonus_max_j: s(p.bonus_max_j),
        bonus_dasar_j: s(p.bonus_dasar_j),
        poin_per_plg_j: s(p.poin_per_plg_j),
        denda_bu_per_kasus: s(p.denda_bu_per_kasus),
        ap2t_min: s(p.denda_ap2t_tingkat.min),
        ap2t_max: s(p.denda_ap2t_tingkat.max),
        ap2t_tingkat: tingkatAwal,
        kdbaca_normal: p.kdbaca_normal.join(', '),
        denda_alpa_per_hari: s(p.denda_alpa_per_hari),
        absensi_hari_kerja: (p.absensi_hari_kerja ?? []).map(String),
        absensi_hari_libur: (p.absensi_hari_libur ?? []).join('\n'),
        absensi_mulai: periode.absensi_mulai ?? '',
        absensi_selesai: periode.absensi_selesai ?? '',
        ambang_evaluasi: s(p.ambang_evaluasi),
        jumlah_top: s(p.jumlah_top),
        jumlah_bottom: s(p.jumlah_bottom),
    });
    const e = form.errors as Record<string, string | undefined>;

    const simpan = (ev: FormEvent) => {
        ev.preventDefault();
        form.transform((d) => ({
            ...d,
            denda_ap2t_tingkat: {
                min: d.ap2t_min,
                max: d.ap2t_max,
                tingkat: Object.fromEntries(
                    d.ap2t_tingkat
                        .split('\n')
                        .map((b) => b.split('=').map((x) => x.trim()))
                        .filter(([k, v]) => k && v !== undefined && v !== ''),
                ),
            },
            kdbaca_normal: d.kdbaca_normal
                .split(',')
                .map((k) => k.trim())
                .filter(Boolean),
            absensi_hari_libur: d.absensi_hari_libur
                .split(/[\n,]/)
                .map((t) => t.trim())
                .filter(Boolean),
        }));
        form.put(route('pengaturan.periode.rules', periode.kode), { preserveScroll: true });
    };

    const angka = (kunci: keyof typeof form.data, label: string, hint: ReactNode, opsi: { wajib?: boolean; step?: string } = {}) => (
        <FormField
            id={kunci}
            label={label}
            required={opsi.wajib}
            error={e[kunci]}
            hint={hint}
            aside={diubah[kunci] ? <span className="text-xs text-muted-ink">diubah {diubah[kunci].oleh}</span> : undefined}
        >
            <Input
                id={kunci}
                type="number"
                step={opsi.step ?? 'any'}
                className="font-mono"
                disabled={terkunci}
                value={form.data[kunci] as string}
                onChange={(ev) => form.setData(kunci, ev.target.value as never)}
                aria-invalid={!!e[kunci]}
            />
        </FormField>
    );

    return (
        <AppLayout
            title={`Periode ${periode.kode}`}
            breadcrumbs={[{ label: 'Periode & parameter', href: route('pengaturan.periode.index') }, { label: periode.kode }]}
        >
            <PageHeader
                title={`${periode.nama} · BLTH ${periode.kode}`}
                description={terkunci ? `Ditutup ${formatTanggalWaktu(periode.ditutup_at)}. Skor final; unggah, rollback, dan parameter terkunci.` : 'Periode terbuka: data bisa diunggah dan parameter bisa diubah. Setiap perubahan parameter langsung menghitung ulang skor.'}
                actions={
                    !terkunci && (
                        <div className="flex gap-2">
                            <Button variant="secondary" onClick={() => router.post(route('pengaturan.periode.recalculate', periode.kode), {}, { preserveScroll: true })}>
                                <RefreshCw />
                                Hitung ulang
                            </Button>
                            <Button variant="reject" onClick={() => setKonfirmasiTutup(true)}>
                                <Lock />
                                Tutup periode
                            </Button>
                        </div>
                    )
                }
            />

            {kosong.length > 0 && !terkunci && (
                <Notice tone="waiting" title="Parameter wajib belum diisi" className="mb-5">
                    {kosong.join(', ')}. Periode tidak bisa ditutup sampai semuanya terisi. Selama Bonus Hari J belum ditetapkan, bonus dihitung 0.
                </Notice>
            )}

            <form onSubmit={simpan} className="grid gap-6">
                <Bagian judul="Poin aktivitas" ket="Poin_m = StanValid × w_stan + Eviden × w_eviden. Agregat menjumlahkan poin semua modul, atau merata-ratakannya.">
                    {angka('w_stan', 'w_stan', `Bawaan ${bawaan.w_stan}.`, { wajib: true })}
                    {angka('w_eviden', 'w_eviden', `Bawaan ${bawaan.w_eviden}.`, { wajib: true })}
                    <FormField id="mode_agregasi" label="Mode agregasi" required error={e.mode_agregasi} hint="Arti 'Jumlah Aktivitas' belum dikonfirmasi; sementara jumlah.">
                        <NativeSelect id="mode_agregasi" disabled={terkunci} value={form.data.mode_agregasi} onChange={(ev) => form.setData('mode_agregasi', ev.target.value as Parameter['mode_agregasi'])}>
                            <option value="jumlah">Jumlah (Σ apa adanya)</option>
                            <option value="rata_rata_modul">Rata-rata modul yang punya data</option>
                        </NativeSelect>
                    </FormField>
                    <FormField id="kdbaca_normal" label="KDBACA normal (prabayar)" required error={e.kdbaca_normal} hint="Pisahkan dengan koma. Usulan: Z (8.855 dari 9.084 baris).">
                        <Input id="kdbaca_normal" className="font-mono" disabled={terkunci} value={form.data.kdbaca_normal} onChange={(ev) => form.setData('kdbaca_normal', ev.target.value)} />
                    </FormField>
                </Bagian>

                <Bagian judul="Bonus Hari J" ket="Bonus = clamp(bonus_dasar_j + terbaca Hari J × poin_per_plg_j, min, max); 0 bila tidak ada pelanggan Hari J.">
                    {angka('bonus_dasar_j', 'bonus_dasar_j', 'Belum ditetapkan di spesifikasi.', { wajib: true })}
                    {angka('poin_per_plg_j', 'poin_per_plg_j', 'Belum ditetapkan di spesifikasi.', { wajib: true })}
                    {angka('bonus_min_j', 'bonus_min_j', `Bawaan ${bawaan.bonus_min_j}.`, { wajib: true })}
                    {angka('bonus_max_j', 'bonus_max_j', `Bawaan ${bawaan.bonus_max_j}.`, { wajib: true })}
                </Bagian>

                <Bagian judul="Denda" ket="Denda Baca Ulang = kasus terbukti salah × denda per kasus. Denda AP2T menurut tingkat (parser AP2T menunggu contoh berkas).">
                    {angka('denda_bu_per_kasus', 'Denda Baca Ulang per kasus', `Bawaan ${bawaan.denda_bu_per_kasus}.`, { wajib: true })}
                    {angka('ap2t_min', 'Denda AP2T minimum', 'Dipakai bila tingkat kasus tidak dikenal.')}
                    {angka('ap2t_max', 'Denda AP2T maksimum', '')}
                    <FormField id="ap2t_tingkat" label="Denda AP2T per tingkat" error={e['denda_ap2t_tingkat.tingkat']} hint="Satu baris per tingkat, contoh: ringan=15">
                        <Textarea id="ap2t_tingkat" rows={3} className="font-mono" disabled={terkunci} value={form.data.ap2t_tingkat} onChange={(ev) => form.setData('ap2t_tingkat', ev.target.value)} />
                    </FormField>
                </Bagian>

                <Bagian judul="Absensi" ket="Biller absen sendiri setiap hari. Hari kerja di rentang ini yang sudah lewat tanpa absensi dihitung alpa; denda = hari alpa × denda per hari.">
                    <FormField id="absensi_mulai" label="Rentang absensi mulai" required error={e.absensi_mulai} hint="Bawaan: tanggal 1 bulan BLTH.">
                        <Input id="absensi_mulai" type="date" disabled={terkunci} value={form.data.absensi_mulai} onChange={(ev) => form.setData('absensi_mulai', ev.target.value)} />
                    </FormField>
                    <FormField id="absensi_selesai" label="Rentang absensi selesai" required error={e.absensi_selesai}>
                        <Input id="absensi_selesai" type="date" disabled={terkunci} value={form.data.absensi_selesai} onChange={(ev) => form.setData('absensi_selesai', ev.target.value)} />
                    </FormField>
                    {angka('denda_alpa_per_hari', 'Denda per hari alpa', 'Bawaan 0: alpa tercatat tetapi belum mengurangi skor.', { wajib: true })}
                    <FormField id="absensi_hari_kerja" label="Hari kerja" required error={e.absensi_hari_kerja}>
                        <div className="flex flex-wrap gap-1.5">
                            {['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map((h, i) => {
                                const v = String(i + 1);
                                const aktif = form.data.absensi_hari_kerja.includes(v);
                                return (
                                    <button
                                        key={v}
                                        type="button"
                                        disabled={terkunci}
                                        aria-pressed={aktif}
                                        onClick={() =>
                                            form.setData('absensi_hari_kerja', aktif ? form.data.absensi_hari_kerja.filter((x) => x !== v) : [...form.data.absensi_hari_kerja, v])
                                        }
                                        className={`h-9 rounded-9 px-3 text-sm ring-1 ${aktif ? 'bg-pln-blue text-white ring-pln-blue' : 'bg-white text-muted-ink ring-border hover:bg-table-head'}`}
                                    >
                                        {h}
                                    </button>
                                );
                            })}
                        </div>
                    </FormField>
                    <FormField id="absensi_hari_libur" label="Hari libur" error={e.absensi_hari_libur} hint="Satu tanggal per baris (YYYY-MM-DD). Tidak dihitung alpa." className="sm:col-span-2">
                        <Textarea id="absensi_hari_libur" rows={3} className="font-mono" disabled={terkunci} value={form.data.absensi_hari_libur} onChange={(ev) => form.setData('absensi_hari_libur', ev.target.value)} />
                    </FormField>
                </Bagian>

                <Bagian judul="Kategori dan leaderboard" ket="Ambang 1.800 dari spesifikasi perlu dikalibrasi: data nyata menghasilkan ±9.000–11.000 poin sebelum bonus dan denda.">
                    {angka('ambang_evaluasi', 'Ambang perlu evaluasi', 'Skor di bawah nilai ini dikategorikan Perlu evaluasi.', { wajib: true })}
                    {angka('jumlah_top', 'Jumlah Top', '', { wajib: true, step: '1' })}
                    {angka('jumlah_bottom', 'Jumlah Bottom', '', { wajib: true, step: '1' })}
                </Bagian>

                {!terkunci && (
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing || !form.isDirty}>
                            {form.processing && <LoaderCircle className="animate-spin" />}
                            Simpan dan hitung ulang
                        </Button>
                    </div>
                )}
            </form>

            <ConfirmDialog
                open={konfirmasiTutup}
                onOpenChange={setKonfirmasiTutup}
                title={`Tutup periode ${periode.kode}?`}
                description={
                    batchMenunggu > 0
                        ? `Masih ada ${batchMenunggu} batch yang diproses atau menunggu commit. Selesaikan dulu sebelum menutup.`
                        : 'Skor final dihitung dan dikunci. Setelah ditutup, data tidak bisa diunggah, di-rollback, atau diubah parameternya.'
                }
                confirmLabel="Tutup periode"
                tone="destructive"
                processing={menutup}
                onConfirm={() =>
                    router.post(route('pengaturan.periode.close', periode.kode), {}, {
                        preserveScroll: true,
                        onStart: () => setMenutup(true),
                        onFinish: () => {
                            setMenutup(false);
                            setKonfirmasiTutup(false);
                        },
                    })
                }
            />
        </AppLayout>
    );
}

function Bagian({ judul, ket, children }: { judul: string; ket: string; children: ReactNode }) {
    return (
        <section className="rounded-14 border border-border">
            <header className="border-b border-line-row px-5 py-4">
                <h2 className="text-section-title text-ink">{judul}</h2>
                <p className="mt-0.5 text-[13px] text-muted-ink">{ket}</p>
            </header>
            <div className="grid gap-5 p-5 sm:grid-cols-2">{children}</div>
        </section>
    );
}
