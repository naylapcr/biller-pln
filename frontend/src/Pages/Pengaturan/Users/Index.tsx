import { useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import ConfirmDialog from '@/Components/shared/ConfirmDialog';
import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormDialog from '@/Components/shared/FormDialog';
import FormField from '@/Components/shared/FormField';
import PageHeader from '@/Components/shared/PageHeader';
import PasswordInput from '@/Components/shared/PasswordInput';
import RowActions from '@/Components/shared/RowActions';
import SearchInput from '@/Components/shared/SearchInput';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NativeSelect } from '@/Components/ui/native-select';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import { useFilters } from '@/hooks/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatRelatif } from '@/lib/format';
import type { Option, PageProps, Paginated, Role } from '@/types';

interface UserRow {
    id: number;
    username_nip: string;
    nama: string;
    role: Role;
    role_label: string;
    biller_id: number | null;
    biller: string | null;
    is_active: boolean;
    last_login_at: string | null;
}

type BillerOption = Option<number> & { terpakai: boolean };

type Props = PageProps<{
    users: Paginated<UserRow>;
    filters: { q?: string; role?: string };
    roleOptions: Option[];
    billerOptions: BillerOption[];
}>;

export default function UsersIndex() {
    const { users, filters, roleOptions, billerOptions, auth } = usePage<Props>().props;
    const f = useFilters({ q: filters.q ?? '', role: filters.role ?? '' });
    const [form, setForm] = useState<{ open: boolean; user: UserRow | null }>({ open: false, user: null });
    const hapus = useConfirmAction<UserRow>();

    const columns: ColumnDef<UserRow>[] = [
        {
            header: 'Pengguna',
            cell: ({ row: { original: u } }) => (
                <div>
                    <p className="font-medium text-ink">{u.nama}</p>
                    <p className="font-mono text-xs text-muted-ink">{u.username_nip}</p>
                </div>
            ),
        },
        { header: 'Peran', cell: ({ row }) => row.original.role_label },
        { header: 'Data biller', cell: ({ row }) => <span className="text-[13px]">{row.original.biller ?? '–'}</span> },
        {
            header: 'Status',
            cell: ({ row }) => (row.original.is_active ? <StatusChip label="Aktif" tone="success" /> : <StatusChip label="Nonaktif" tone="neutral" />),
        },
        {
            header: 'Terakhir masuk',
            cell: ({ row }) => (
                <span className="text-[13px] text-muted-ink">{row.original.last_login_at ? formatRelatif(row.original.last_login_at) : 'Belum pernah'}</span>
            ),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'w-14 text-right' },
            cell: ({ row: { original: u } }) => (
                <RowActions
                    label={u.nama}
                    actions={[
                        { label: 'Ubah', icon: Pencil, onSelect: () => setForm({ open: true, user: u }) },
                        { label: 'Hapus', icon: Trash2, danger: true, hidden: u.id === auth.user.id, onSelect: () => hapus.ask(u) },
                    ]}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Pengguna">
            <PageHeader
                title="Pengguna"
                description={`Akun Super Admin, Koordinator Admin, dan Biller. Akun Biller ditautkan ke satu data biller. ${formatNumber(users.total)} akun.`}
                actions={
                    <Button onClick={() => setForm({ open: true, user: null })}>
                        <Plus />
                        Tambah pengguna
                    </Button>
                }
            />

            <Toolbar>
                <SegmentedTabs
                    label="Filter peran"
                    value={f.values.role}
                    onChange={(v) => f.set('role', v)}
                    items={[{ value: '', label: 'Semua' }, ...roleOptions.map((r) => ({ value: r.value, label: r.label }))]}
                />
                <SearchInput className="md:w-72" value={f.values.q} onChange={(v) => f.set('q', v, { debounce: true })} placeholder="Cari nama atau username" />
            </Toolbar>

            <DataTable columns={columns} data={users} getRowId={(u) => String(u.id)} empty="Tidak ada pengguna yang cocok dengan filter." />

            {form.open && (
                <UserFormDialog key={form.user?.id ?? 'baru'} user={form.user} roleOptions={roleOptions} billerOptions={billerOptions} onClose={() => setForm({ open: false, user: null })} />
            )}

            <ConfirmDialog
                open={hapus.open}
                onOpenChange={(v) => !v && hapus.close()}
                title="Hapus pengguna?"
                description={
                    <>
                        Akun <strong className="text-ink">{hapus.target?.nama}</strong> tidak bisa dipakai masuk lagi. Data biller dan skornya tetap tersimpan.
                    </>
                }
                confirmLabel="Hapus pengguna"
                tone="destructive"
                processing={hapus.processing}
                onConfirm={() => hapus.target && hapus.run('delete', route('pengaturan.users.destroy', hapus.target.id))}
            />
        </AppLayout>
    );
}

function UserFormDialog({
    user,
    roleOptions,
    billerOptions,
    onClose,
}: {
    user: UserRow | null;
    roleOptions: Option[];
    billerOptions: BillerOption[];
    onClose: () => void;
}) {
    const { data, setData, post, put, processing, errors } = useForm({
        username_nip: user?.username_nip ?? '',
        nama: user?.nama ?? '',
        role: (user?.role ?? 'biller') as Role,
        biller_id: user?.biller_id ? String(user.biller_id) : '',
        password: '',
        is_active: user?.is_active ?? true,
    });

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (user) put(route('pengaturan.users.update', user.id), opts);
        else post(route('pengaturan.users.store'), opts);
    };

    return (
        <FormDialog
            open
            onOpenChange={(v) => !v && onClose()}
            title={user ? `Ubah ${user.nama}` : 'Tambah pengguna'}
            description="Login memakai username/NIP dan kata sandi."
            submitLabel={user ? 'Simpan perubahan' : 'Tambah pengguna'}
            processing={processing}
            onSubmit={submit}
        >
            <FormField id="nama" label="Nama lengkap" required error={errors.nama}>
                <Input id="nama" value={data.nama} onChange={(e) => setData('nama', e.target.value)} aria-invalid={!!errors.nama} />
            </FormField>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField id="username_nip" label="Username / NIP" required error={errors.username_nip}>
                    <Input
                        id="username_nip"
                        className="font-mono"
                        autoCapitalize="none"
                        value={data.username_nip}
                        onChange={(e) => setData('username_nip', e.target.value)}
                        aria-invalid={!!errors.username_nip}
                    />
                </FormField>
                <FormField id="role" label="Peran" required error={errors.role}>
                    <NativeSelect id="role" value={data.role} onChange={(e) => setData('role', e.target.value as Role)}>
                        {roleOptions.map((r) => (
                            <option key={r.value} value={r.value}>
                                {r.label}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
            </div>
            {data.role === 'biller' && (
                <FormField id="biller_id" label="Data biller" required error={errors.biller_id} hint="Biller hanya bisa melihat skor dari data biller ini.">
                    <NativeSelect id="biller_id" value={data.biller_id} onChange={(e) => setData('biller_id', e.target.value)} aria-invalid={!!errors.biller_id}>
                        <option value="">Pilih biller</option>
                        {billerOptions.map((b) => (
                            <option key={b.value} value={b.value} disabled={b.terpakai && b.value !== user?.biller_id}>
                                {b.label}
                                {b.terpakai && b.value !== user?.biller_id ? ' (sudah punya akun)' : ''}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
            )}
            <FormField
                id="password"
                label={user ? 'Kata sandi baru' : 'Kata sandi awal'}
                required={!user}
                error={errors.password}
                hint={user ? 'Kosongkan bila tidak diganti. Minimal 8 karakter.' : 'Minimal 8 karakter.'}
            >
                <PasswordInput id="password" autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} aria-invalid={!!errors.password} />
            </FormField>
            <div className="flex items-center gap-2.5">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                <Label htmlFor="is_active" className="text-sm font-normal">
                    Akun aktif dan boleh masuk
                </Label>
            </div>
        </FormDialog>
    );
}
