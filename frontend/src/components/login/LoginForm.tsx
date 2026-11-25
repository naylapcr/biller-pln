import { useForm } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, LoaderCircle, Lock, User, type LucideIcon } from 'lucide-react';
import { useState, type ComponentProps, type FormEvent, type ReactNode } from 'react';

import { cn } from '@/lib/utils';
import LoginErrorNotice from './LoginErrorNotice';

interface IsianMasukProps extends ComponentProps<'input'> {
    id: string;
    label: string;
    icon: LucideIcon;
    galat: boolean;
    kanan?: ReactNode;
}

/** Kolom isian putih dengan ikon, khusus halaman masuk. */
function IsianMasuk({ id, label, icon: Icon, galat, kanan, ...props }: IsianMasukProps) {
    return (
        <div className="grid gap-2">
            <label htmlFor={id} className={cn('text-xs leading-[15px] font-semibold', galat ? 'text-[#ffbfbf]' : 'text-[#e0f0ff]')}>
                {label}
            </label>
            <div
                className={cn(
                    'flex items-center gap-3 rounded-lg border-[1.5px] bg-white px-4 shadow-[0_4px_10px_rgb(0_0_0/0.1)] focus-within:ring-3 focus-within:ring-[#00c2fa]/50',
                    galat ? 'border-[#f24040] text-[#d92626]' : 'border-transparent text-[#0f1f3d]',
                )}
            >
                <Icon className={cn('size-[18px] shrink-0', !galat && 'text-[#5c7399]')} aria-hidden />
                <input id={id} aria-invalid={galat} className="h-[46px] min-w-0 flex-1 bg-transparent text-sm font-medium outline-none placeholder:text-[#6b7f9c]" {...props} />
                {kanan}
            </div>
        </div>
    );
}

/** Formulir "Masuk ke Akun Anda" (00.1 dan 00.2). */
export default function LoginForm({ status }: { status?: string | null }) {
    const [lihat, setLihat] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ login: '', password: '' });
    const galat = !!errors.login;
    const Mata = lihat ? EyeOff : Eye;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('login.store'), { onFinish: () => reset('password') });
    };

    return (
        <form onSubmit={submit} noValidate>
            <LoginErrorNotice pesan={errors.login} />
            {status && !galat && (
                <p role="status" className="mb-5 rounded-lg border-[1.5px] border-white/40 bg-white/15 px-4 py-[11px] text-xs font-bold text-white">
                    {status}
                </p>
            )}

            <h2 className="text-[28px] leading-[35px] font-extrabold text-white">Masuk ke Akun Anda</h2>
            <p className="mt-1.5 text-[13px] leading-5 text-[#e0f0ff]">
                Silakan masukkan NIP atau Username terdaftar.
                <br />
                Hak akses peran akan disesuaikan otomatis.
            </p>

            <div className="mt-5 grid gap-5">
                <IsianMasuk
                    id="login"
                    label="NIP / Username Petugas"
                    icon={User}
                    galat={galat}
                    name="login"
                    autoComplete="username"
                    autoFocus
                    autoCapitalize="none"
                    placeholder="Contoh: 18111.akmal"
                    value={data.login}
                    onChange={(e) => setData('login', e.target.value)}
                    required
                />
                <div>
                    <IsianMasuk
                        id="password"
                        label="Kata Sandi"
                        icon={Lock}
                        galat={galat || !!errors.password}
                        name="password"
                        type={lihat ? 'text' : 'password'}
                        autoComplete="current-password"
                        placeholder="Masukkan kata sandi"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        required
                        kanan={
                            <button
                                type="button"
                                onClick={() => setLihat((v) => !v)}
                                aria-label={lihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                                className="-mr-2 inline-flex size-9 items-center justify-center text-[#8c9eb8] hover:text-[#5c7399]"
                            >
                                <Mata className="size-[18px]" />
                            </button>
                        }
                    />
                    {errors.password && <p className="mt-2 text-xs text-[#ffbfbf]">{errors.password}</p>}
                </div>
                <button
                    type="submit"
                    disabled={processing}
                    className="inline-flex h-12 items-center justify-center gap-2.5 rounded-lg bg-[#00c2fa] text-sm font-extrabold tracking-[1.2px] text-[#0a1f40] shadow-[0_6px_14px_rgb(0_153_229/0.35)] transition-colors hover:bg-[#33d0ff] focus-visible:ring-3 focus-visible:ring-white/60 focus-visible:outline-none disabled:opacity-70"
                >
                    {galat ? 'COBA MASUK LAGI' : 'MASUK SISTEM'}
                    {processing ? <LoaderCircle className="size-[18px] animate-spin" /> : <ArrowRight className="size-[18px]" strokeWidth={2.2} />}
                </button>
            </div>

            <p className="mt-7 text-center text-[10px] leading-[15px] text-[#b8dbff]">
                {galat ? 'Akun terkunci otomatis setelah 5x gagal · Hubungi Admin ULP Kota Barat' : 'Sistem Informasi Resmi PT PLN (Persero) · Dilindungi Enkripsi & Audit Trail'}
            </p>
        </form>
    );
}
