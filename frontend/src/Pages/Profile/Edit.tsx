import { useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEvent } from 'react';

import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import PasswordInput from '@/Components/shared/PasswordInput';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout';
import { formatTanggalWaktu } from '@/lib/format';
import type { PageProps } from '@/types';

interface Profil {
    username_nip: string;
    nama: string;
    role_label: string;
    biller: { login_acmt: string; kode_petugas: string; zona_wilayah: string | null } | null;
    last_login_at: string | null;
}

function Section({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
    return (
        <section className="rounded-14 border border-border">
            <header className="border-b border-line-row px-5 py-4">
                <h2 className="text-section-title text-ink">{title}</h2>
                <p className="mt-0.5 text-[13px] text-muted-ink">{description}</p>
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

function ProfilForm({ profil }: { profil: Profil }) {
    const { data, setData, patch, processing, errors, isDirty } = useForm({ nama: profil.nama });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        patch(route('profil.update'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="grid gap-5" noValidate>
            <FormField id="nama" label="Nama lengkap" required error={errors.nama}>
                <Input id="nama" value={data.nama} onChange={(e) => setData('nama', e.target.value)} aria-invalid={!!errors.nama} />
            </FormField>
            <div className="flex justify-end">
                <Button type="submit" disabled={processing || !isDirty}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Simpan profil
                </Button>
            </div>
        </form>
    );
}

function PasswordForm() {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('profil.password'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (err) => {
                if (err.password) reset('password', 'password_confirmation');
                if (err.current_password) reset('current_password');
            },
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-5" noValidate>
            <FormField id="current_password" label="Kata sandi lama" required error={errors.current_password}>
                <PasswordInput
                    id="current_password"
                    value={data.current_password}
                    onChange={(e) => setData('current_password', e.target.value)}
                    autoComplete="current-password"
                    aria-invalid={!!errors.current_password}
                    required
                />
            </FormField>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField
                    id="password"
                    label="Kata sandi baru"
                    required
                    error={errors.password}
                    hint="Minimal 8 karakter."
                >
                    <PasswordInput
                        id="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
                        aria-invalid={!!errors.password}
                        required
                    />
                </FormField>
                <FormField
                    id="password_confirmation"
                    label="Konfirmasi kata sandi baru"
                    required
                    error={errors.password_confirmation}
                >
                    <PasswordInput
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                        required
                    />
                </FormField>
            </div>
            <div className="flex justify-end">
                <Button type="submit" variant="secondary" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Ubah kata sandi
                </Button>
            </div>
        </form>
    );
}

export default function ProfileEdit() {
    const { profil } = usePage<PageProps<{ profil: Profil }>>().props;

    const info: [string, React.ReactNode][] = [
        ['Username / NIP', <span className="font-mono">{profil.username_nip}</span>],
        ['Peran', profil.role_label],
        ...(profil.biller
            ? ([
                  ['Login ACMT', <span className="font-mono">{profil.biller.login_acmt}</span>],
                  ['Kode petugas', <span className="font-mono">{profil.biller.kode_petugas}</span>],
                  ['Zona wilayah', profil.biller.zona_wilayah ?? '–'],
              ] as [string, React.ReactNode][])
            : []),
        ['Terakhir masuk', formatTanggalWaktu(profil.last_login_at)],
    ];

    return (
        <AppLayout title="Profil saya" breadcrumbs={[{ label: 'Akun' }, { label: 'Profil saya' }]}>
            <PageHeader title="Profil saya" description="Perbarui nama dan kata sandi akun Anda." />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="grid gap-6">
                    <Section title="Data diri" description="Nama tampil di dashboard dan leaderboard.">
                        <ProfilForm profil={profil} />
                    </Section>
                    <Section title="Ubah kata sandi" description="Masukkan kata sandi lama untuk membuat yang baru.">
                        <PasswordForm />
                    </Section>
                </div>

                <aside className="self-start rounded-14 border border-border p-5">
                    <h2 className="text-section-title text-ink">Informasi akun</h2>
                    <p className="mt-0.5 text-[13px] text-muted-ink">Diatur oleh Super Admin.</p>
                    <dl className="mt-4 divide-y divide-line-row">
                        {info.map(([label, value]) => (
                            <div key={label} className="flex justify-between gap-4 py-3 text-sm">
                                <dt className="text-muted-ink">{label}</dt>
                                <dd className="min-w-0 text-right break-words text-ink">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </aside>
            </div>
        </AppLayout>
    );
}
