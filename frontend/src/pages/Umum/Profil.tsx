import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

import FormField from '@/components/ui/FormField';
import Kartu from '@/components/ui/Kartu';
import PageHeader from '@/components/ui/PageHeader';
import PasswordInput from '@/components/ui/PasswordInput';
import StatusChip from '@/components/ui/StatusChip';
import UserAvatar from '@/components/ui/UserAvatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { layoutByRole } from '@/layouts/MainLayout/layoutByRole';
import { formatTanggal } from '@/lib/format';
import { STATUS_ABSENSI, toneAksi, type StatusTone } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { PageProps, Role, StatusAbsensi } from '@/types';

type Props = PageProps<{
    profil: {
        username_nip: string;
        nama: string;
        role: Role;
        role_label: string;
        is_active: boolean;
        biller: { nip: string | null; login_acmt: string; kode_petugas: string; zona_wilayah: string | null } | null;
        absensi_hari_ini: { status: StatusAbsensi | 'belum'; absen_at?: string | null } | null;
    };
    sesi: { last_login_at: string | null; sisa_menit: number | null; lama_sesi_jam: number; ip: string | null; perangkat: string | null };
    aktivitas: { id: number; aksi: string; objek: string; waktu: string; ip: string | null }[];
}>;

const PERAN: Record<Role, { label: string; tone: StatusTone; avatar: string }> = {
    biller: { label: 'Biller Lapangan (biller)', tone: 'success', avatar: 'bg-success text-white' },
    admin: { label: 'Koordinator Admin (admin)', tone: 'info', avatar: 'bg-pln-blue-dark text-white' },
    super_admin: { label: 'Super Admin (super_admin)', tone: 'waiting', avatar: 'bg-ink text-pln-yellow' },
};

const JUDUL: Record<Role, string> = { biller: 'Biller', admin: 'Koordinator Admin', super_admin: 'Super Admin' };

/** Nama peramban dari User-Agent; detail lengkap tetap ada di audit trail. */
function peramban(ua: string | null): string {
    if (!ua) return 'Tidak diketahui';
    const nama = [['Edg/', 'Edge'], ['OPR/', 'Opera'], ['Firefox/', 'Firefox'], ['Chrome/', 'Chrome'], ['Safari/', 'Safari']].find(([k]) => ua.includes(k));
    return nama?.[1] ?? 'Peramban lain';
}

function Kotak({ label, nilai, keterangan, warna = 'text-ink-soft' }: { label: string; nilai: ReactNode; keterangan: ReactNode; warna?: string }) {
    return (
        <div className="rounded-10 border border-line bg-canvas px-3 py-2.5">
            <p className="text-[9px] leading-3 font-bold text-muted-ink">{label}</p>
            <p className={cn('mt-1 text-[13px] leading-4 font-extrabold', warna)}>{nilai}</p>
            <p className="mt-1 text-[10px] leading-[13px] text-muted-ink">{keterangan}</p>
        </div>
    );
}

/** Kolom terkunci: dikelola Super Admin. */
function Terkunci({ id, label, nilai, mono = true }: { id: string; label: string; nilai: string; mono?: boolean }) {
    return (
        <FormField id={id} label={label}>
            <Input id={id} value={nilai} disabled className={mono ? 'font-mono' : undefined} />
        </FormField>
    );
}

/** Isian kata sandi dengan penanda Kuat ✓ / Cocok ✓ di dalam kolom. */
function IsianSandi({ id, label, nilai, onChange, error, tanda }: { id: string; label: string; nilai: string; onChange: (v: string) => void; error?: string; tanda: string | null }) {
    return (
        <FormField id={id} label={label} required error={error}>
            <div className="relative">
                <Input id={id} type="password" autoComplete="new-password" className="pr-20" value={nilai} onChange={(e) => onChange(e.target.value)} aria-invalid={!!error} />
                {tanda && <span className="pointer-events-none absolute inset-y-0 right-3 inline-flex items-center text-[11px] font-semibold text-success">{tanda}</span>}
            </div>
        </FormField>
    );
}

/** 01.5, 02.9, 03.7 Profil Saya. */
export default function Profil() {
    const { profil, sesi, aktivitas } = usePage<Props>().props;
    const nama = useForm({ nama: profil.nama });
    const sandi = useForm({ current_password: '', password: '', password_confirmation: '' });
    const p = sandi.data.password;
    const kuat = p.length >= 8 && /[a-z]/.test(p) && /[A-Z]/.test(p) && /\d/.test(p);
    const cocok = p !== '' && p === sandi.data.password_confirmation;
    const peran = PERAN[profil.role];
    const b = profil.biller;

    // Sisa sesi dihitung mundur per menit sejak halaman dibuka.
    const [lewat, setLewat] = useState(0);
    useEffect(() => {
        const t = setInterval(() => setLewat((n) => n + 1), 60000);
        return () => clearInterval(t);
    }, []);
    const sisa = sesi.sisa_menit === null ? null : Math.max(0, sesi.sisa_menit - lewat);
    const teksSisa = sisa === null ? `${sesi.lama_sesi_jam} jam` : `${String(Math.floor(sisa / 60)).padStart(2, '0')}j ${String(sisa % 60).padStart(2, '0')}m`;
    const keluar = sisa === null ? null : new Date(Date.now() + sisa * 60000);

    return (
        <>
            <Head title="Profil Saya" />
            <PageHeader
                title={`Profil Saya & Keamanan Akun (${JUDUL[profil.role]})`}
                description="Kelola identitas profil, pantau waktu terakhir aktif & sesi login, serta perbarui kata sandi Anda"
                actions={
                    <>
                        <StatusChip titik label={`Terakhir Aktif: Baru saja (${formatTanggal(new Date(), 'HH:mm')} WIB)`} tone="success" />
                        <StatusChip label={`Sisa Sesi: ${teksSisa}`} tone="sky" />
                    </>
                }
            />

            <div className="grid gap-[18px]">
                <section className="flex flex-col gap-3.5 rounded-2xl border border-line bg-white p-[18px] shadow-kartu">
                    <div className="flex flex-wrap items-center gap-3.5">
                        <UserAvatar name={profil.nama} size={56} className={peran.avatar} />
                        <div className="min-w-0 flex-1 basis-60">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="text-lg leading-[23px] font-extrabold text-ink">{profil.nama}</p>
                                <StatusChip titik label={peran.label} tone={peran.tone} />
                                <StatusChip label={profil.is_active ? 'Akun Aktif ✓' : 'Nonaktif'} tone={profil.is_active ? 'success' : 'neutral'} />
                            </div>
                            <p className="mt-1 font-mono text-[11px] leading-[15px] break-words text-slate-mid">
                                Username / NIP: {b?.nip ?? profil.username_nip}
                                {b ? ` · Kode Petugas ACMT: ${b.kode_petugas} · Login ACMT: ${b.login_acmt}` : " · Unit: PLN ULP Kota Barat"}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Kotak label="TERAKHIR LOGIN (LAST_LOGIN_AT)" nilai={sesi.last_login_at ? `${formatTanggal(sesi.last_login_at, 'd MMM yyyy · HH:mm')} WIB` : '–'} keterangan="Waktu server WIB tercatat" />
                        <Kotak label="STATUS & TERAKHIR AKTIF" nilai="Online · Baru Saja" keterangan="Aktivitas terakhir < 1 menit lalu" warna="text-success" />
                        <Kotak
                            label={`MASA BERLAKU SESI (${sesi.lama_sesi_jam} JAM)`}
                            nilai={`Sisa ${teksSisa}`}
                            keterangan={keluar ? `Logout otomatis pukul ${formatTanggal(keluar, 'HH:mm')} WIB` : 'Logout otomatis saat sesi habis'}
                            warna="text-pln-blue"
                        />
                        <Kotak label="PERANGKAT & IP TERAKHIR" nilai={`${peramban(sesi.perangkat)} · IP ${sesi.ip ?? '–'}`} keterangan="Dicatat saat login terakhir" />
                    </div>
                </section>

                <div className="grid items-start gap-[18px] xl:grid-cols-2">
                    <Kartu judul="Informasi & Ubah Profil Saya">
                        <p className="text-[11px] leading-[14px] text-muted-ink">Perbarui nama tampilan Anda. NIP, peran baku, dan pemetaan kode ACMT dikelola oleh Super Admin.</p>
                        <form
                            className="grid gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                nama.patch(route('profil.update'), { preserveScroll: true });
                            }}
                        >
                            <FormField id="nama" label="Nama Lengkap (Dapat Diubah)" required error={nama.errors.nama}>
                                <Input id="nama" value={nama.data.nama} onChange={(e) => nama.setData('nama', e.target.value)} />
                            </FormField>
                            <Terkunci id="username" label="Username / NIP (Terkunci)" nilai={profil.username_nip} />
                            {profil.role === 'biller' && (
                                <>
                                    <Terkunci id="wilayah" label="Wilayah Tugas & Sektor" nilai={`${b?.zona_wilayah ?? 'Belum diisi'} · ULP Kota Barat`} mono={false} />
                                    <Terkunci id="kode" label="Kode Petugas & Login ACMT" nilai={b ? `${b.kode_petugas} · ${b.login_acmt}` : 'Belum ditautkan'} />
                                </>
                            )}
                            {profil.role === 'admin' && (
                                <>
                                    <Terkunci id="peran" label="Peran Akses Baku (users.role)" nilai="Koordinator Admin (admin) — Unggah, Validasi, Pantau & Laporan" mono={false} />
                                    <Terkunci
                                        id="absen"
                                        label="Status Absensi Mandiri Hari Ini"
                                        nilai={
                                            profil.absensi_hari_ini
                                                ? `${STATUS_ABSENSI[profil.absensi_hari_ini.status].label}${profil.absensi_hari_ini.absen_at ? ` · ${formatTanggal(profil.absensi_hari_ini.absen_at, 'HH:mm')} WIB` : ''}`
                                                : '–'
                                        }
                                        mono={false}
                                    />
                                </>
                            )}
                            {profil.role === 'super_admin' && (
                                <>
                                    <Terkunci id="peran" label="Peran Akses Baku (users.role)" nilai="Super Admin (super_admin) — Akun, Master, Parameter & Audit" mono={false} />
                                    <Terkunci id="absen" label="Kebijakan Absensi" nilai="Super Admin tidak wajib absen" mono={false} />
                                </>
                            )}
                            <Button type="submit" className="w-full" disabled={nama.processing || nama.data.nama.trim() === profil.nama}>
                                {nama.processing && <LoaderCircle className="animate-spin" />}
                                Simpan Perubahan Profil
                            </Button>
                        </form>
                    </Kartu>

                    <Kartu judul="Ganti Kata Sandi Akun">
                        <p className="text-[11px] leading-[14px] text-muted-ink">Gunakan kombinasi huruf besar, huruf kecil, dan angka minimal 8 karakter untuk menjaga keamanan akun.</p>
                        <form
                            className="grid gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                sandi.put(route('profil.password'), { preserveScroll: true, onSuccess: () => sandi.reset() });
                            }}
                        >
                            <FormField id="current_password" label="Kata Sandi Saat Ini" required error={sandi.errors.current_password}>
                                <PasswordInput id="current_password" autoComplete="current-password" value={sandi.data.current_password} onChange={(e) => sandi.setData('current_password', e.target.value)} />
                            </FormField>
                            <IsianSandi id="password" label="Kata Sandi Baru (Min. 8 Karakter)" nilai={p} onChange={(v) => sandi.setData('password', v)} error={sandi.errors.password} tanda={kuat ? 'Kuat ✓' : null} />
                            <IsianSandi
                                id="password_confirmation"
                                label="Konfirmasi Kata Sandi Baru"
                                nilai={sandi.data.password_confirmation}
                                onChange={(v) => sandi.setData('password_confirmation', v)}
                                tanda={cocok ? 'Cocok ✓' : null}
                            />
                            <div className="rounded-10 border border-sky-side bg-sky-row px-3 py-2.5 text-[10px] leading-[15px] text-slate-ink">
                                <p className="text-[9px] font-bold text-sky-ink">KEBIJAKAN KEAMANAN & SESI</p>
                                <ul className="mt-0.5 list-inside list-disc">
                                    <li>Perubahan kata sandi langsung tercatat pada Audit Trail sistem.</li>
                                    <li>Akun terkunci setelah 5 kali gagal masuk; hanya Super Admin yang bisa membukanya.</li>
                                    <li>Kata sandi tersimpan terenkripsi (hash).</li>
                                </ul>
                            </div>
                            <Button type="submit" variant="success" className="w-full" disabled={sandi.processing || !kuat || !cocok || !sandi.data.current_password}>
                                {sandi.processing && <LoaderCircle className="animate-spin" />}
                                Perbarui Kata Sandi
                            </Button>
                        </form>
                    </Kartu>
                </div>

                <Kartu judul="Riwayat Login & Aktivitas Akun Terakhir" aksi={<StatusChip label={`${aktivitas.length} Aktivitas Terakhir`} tone="neutral" />}>
                    <ul className="grid gap-2">
                        {aktivitas.map((a) => (
                            <li key={a.id} className="grid gap-x-4 gap-y-1 rounded-10 bg-canvas px-3 py-2.5 sm:grid-cols-[170px_1fr_140px_auto] sm:items-center">
                                <span className="font-mono text-[11px] font-bold text-ink">{formatTanggal(a.waktu, 'd MMM yyyy · HH:mm')} WIB</span>
                                <span className="text-xs font-semibold text-ink-soft">{a.objek || 'Masuk ke sistem'}</span>
                                <span className="font-mono text-[11px] text-muted-ink">{a.ip ?? '–'}</span>
                                <StatusChip label={a.aksi} tone={toneAksi(a.aksi)} />
                            </li>
                        ))}
                        {aktivitas.length === 0 && <li className="text-xs text-muted-ink">Belum ada aktivitas.</li>}
                    </ul>
                </Kartu>
            </div>
        </>
    );
}

Profil.layout = layoutByRole;
