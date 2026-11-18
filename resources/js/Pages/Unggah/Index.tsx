import { Link, router, useForm, usePage } from '@inertiajs/react';
import { FileUp, LoaderCircle } from 'lucide-react';
import { useEffect } from 'react';

import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import FormField from '@/Components/shared/FormField';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import StatusChip from '@/Components/shared/StatusChip';
import Toolbar from '@/Components/shared/Toolbar';
import PeriodeSelect from '@/Components/sipermon/PeriodeSelect';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { NativeSelect } from '@/Components/ui/native-select';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { STATUS_BATCH, type StatusBatch } from '@/lib/status';
import type { Option, PageProps, Paginated, PeriodeInfo } from '@/types';

export interface BatchRingkas {
    id: string;
    unggahan_id: string;
    periode: string | null;
    modul: string | null;
    modul_label: string;
    sheet: string | null;
    file_name: string;
    status: StatusBatch;
    status_label: string;
    baris: number | null;
    error: number;
    warning: number;
    info: number;
    pengunggah: string | null;
    created_at: string | null;
    committed_at: string | null;
}

type Props = PageProps<{
    periode: PeriodeInfo | null;
    periodeOptions: Option[];
    periodeTerbuka: Option[];
    modulOptions: Option[];
    modulMenunggu: string[];
    filters: { modul?: string };
    batches: Paginated<BatchRingkas>;
    maksKb: number;
}>;

export default function UnggahIndex() {
    const { periode, periodeOptions, periodeTerbuka, modulOptions, modulMenunggu, filters, batches, maksKb } = usePage<Props>().props;
    const form = useForm<{ file: File | null; periode: string; modul: string }>({
        file: null,
        periode: periode?.terbuka ? periode.kode : (periodeTerbuka[0]?.value ?? ''),
        modul: '',
    });

    // Perbarui daftar setiap 2 detik selama masih ada batch yang diproses.
    const adaDiproses = batches.data.some((b) => b.status === 'diproses');
    useEffect(() => {
        if (!adaDiproses) return;
        const t = setInterval(() => router.reload({ only: ['batches', 'navBadges'] }), 2000);
        return () => clearInterval(t);
    }, [adaDiproses]);

    const kirim = () => form.post(route('unggah.store'), { forceFormData: true, onSuccess: () => form.reset('file') });

    const columns: ColumnDef<BatchRingkas>[] = [
        {
            header: 'Berkas / modul',
            cell: ({ row: { original: b } }) => (
                <div className="min-w-0">
                    <p className="font-medium text-ink">{b.modul_label}</p>
                    <p className="truncate text-xs text-muted-ink">
                        {b.file_name}
                        {b.sheet ? ` · sheet ${b.sheet}` : ''}
                    </p>
                </div>
            ),
        },
        { header: 'Periode', cell: ({ row }) => <span className="font-mono text-[13px]">{row.original.periode}</span> },
        {
            header: 'Baris',
            meta: { className: 'text-right' },
            cell: ({ row }) => <span className="font-mono text-[13px]">{formatNumber(row.original.baris)}</span>,
        },
        {
            header: 'Issue',
            cell: ({ row: { original: b } }) => (
                <div className="flex flex-wrap gap-1 text-xs">
                    {b.error > 0 && <StatusChip label={`${formatNumber(b.error)} error`} tone="danger" />}
                    {b.warning > 0 && <StatusChip label={`${formatNumber(b.warning)} warning`} tone="waiting" />}
                    {b.info > 0 && <StatusChip label={`${formatNumber(b.info)} info`} tone="info" />}
                    {b.error + b.warning + b.info === 0 && b.status !== 'diproses' && <span className="text-muted-ink">Bersih</span>}
                </div>
            ),
        },
        {
            header: 'Status',
            cell: ({ row: { original: b } }) => (
                <span className="inline-flex items-center gap-1.5">
                    {b.status === 'diproses' && <LoaderCircle className="size-3.5 animate-spin text-pln-blue" />}
                    <StatusChip {...STATUS_BATCH[b.status]} />
                </span>
            ),
        },
        {
            header: 'Diunggah',
            cell: ({ row: { original: b } }) => (
                <div className="text-[13px]">
                    <p>{formatTanggalWaktu(b.created_at)}</p>
                    <p className="text-xs text-muted-ink">{b.pengunggah}</p>
                </div>
            ),
        },
        {
            id: 'aksi',
            header: () => <span className="sr-only">Aksi</span>,
            meta: { className: 'text-right' },
            cell: ({ row: { original: b } }) => (
                <Button variant={b.status === 'siap_commit' ? 'default' : 'secondary'} size="sm" asChild>
                    <Link href={route('unggah.show', b.id)}>{b.status === 'siap_commit' ? 'Pratinjau & commit' : 'Lihat'}</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Unggah data">
            <PageHeader
                title="Unggah data ACMT/AP2T"
                description="Unggah ekspor rekap ACMT. Setiap sheet yang dikenali menjadi satu batch: diproses, divalidasi, lalu menunggu Anda commit. Data baru masuk skor setelah di-commit."
            />

            <section className="mb-7 rounded-14 border border-border p-5">
                {periodeTerbuka.length === 0 ? (
                    <Notice tone="waiting" title="Tidak ada periode terbuka">
                        Super Admin perlu membuka periode BLTH lebih dulu sebelum berkas bisa diunggah.
                    </Notice>
                ) : (
                    <form
                        className="grid gap-5 lg:grid-cols-[1fr_200px_240px_auto] lg:items-start"
                        onSubmit={(e) => {
                            e.preventDefault();
                            kirim();
                        }}
                    >
                        <FormField
                            id="file"
                            label="Berkas rekap"
                            required
                            error={form.errors.file}
                            hint={`.xlsx, .xls, atau .csv, maksimal ${formatNumber(maksKb / 1024)} MB. Contoh: DATA_REKAP_WBM.xlsx berisi beberapa sheet.`}
                        >
                            <Input
                                id="file"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                                aria-invalid={!!form.errors.file}
                            />
                        </FormField>
                        <FormField id="periode" label="Periode (BLTH)" required error={form.errors.periode} hint="Harus sama dengan BLTH di berkas.">
                            <NativeSelect id="periode" value={form.data.periode} onChange={(e) => form.setData('periode', e.target.value)}>
                                {periodeTerbuka.map((p) => (
                                    <option key={p.value} value={p.value}>
                                        {p.value} · {p.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField id="modul" label="Modul" error={form.errors.modul} hint="Deteksi otomatis mengenali sheet dari judul dan header.">
                            <NativeSelect id="modul" value={form.data.modul} onChange={(e) => form.setData('modul', e.target.value)}>
                                <option value="">Deteksi otomatis (semua sheet)</option>
                                {modulOptions.map((m) => (
                                    <option key={m.value} value={m.value}>
                                        {m.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <div className="lg:pt-7">
                            <Button type="submit" disabled={form.processing || !form.data.file}>
                                {form.processing ? <LoaderCircle className="animate-spin" /> : <FileUp />}
                                Unggah
                            </Button>
                        </div>
                    </form>
                )}
                {modulMenunggu.length > 0 && (
                    <p className="mt-4 text-xs text-muted-ink">Parser {modulMenunggu.join(', ')} dibuat setelah contoh berkasnya diterima.</p>
                )}
            </section>

            <h2 className="mb-3 text-section-title text-ink">Riwayat batch</h2>
            <Toolbar>
                <PeriodeSelect value={periode?.kode} options={periodeOptions} params={{ modul: filters.modul }} />
                <NativeSelect
                    className="md:w-60"
                    aria-label="Modul"
                    value={filters.modul ?? ''}
                    onChange={(e) => router.get(route('unggah.index'), { periode: periode?.kode, ...(e.target.value ? { modul: e.target.value } : {}) }, { preserveScroll: true })}
                >
                    <option value="">Semua modul</option>
                    {modulOptions.map((m) => (
                        <option key={m.value} value={m.value}>
                            {m.label}
                        </option>
                    ))}
                </NativeSelect>
            </Toolbar>
            <DataTable columns={columns} data={batches} getRowId={(b) => b.id} empty="Belum ada berkas yang diunggah untuk periode ini." />
        </AppLayout>
    );
}
