import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import DialogRollback from '@/components/koordinator/DialogRollback';
import ModalFormatKolom from '@/components/koordinator/ModalFormatKolom';
import UnggahDropzone from '@/components/koordinator/UnggahDropzone';
import ChipPeriode from '@/components/shared/ChipPeriode';
import PeriodeSelect from '@/components/shared/PeriodeSelect';
import DataTable, { type ColumnDef } from '@/components/ui/DataTable';
import Kartu from '@/components/ui/Kartu';
import Notice from '@/components/ui/Notice';
import PageHeader from '@/components/ui/PageHeader';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useConfirmAction } from '@/hooks/useConfirmAction';
import { layoutKoordinator } from '@/layouts/KoordinatorLayout/KoordinatorLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { labelBatch, type StatusBatch } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { Option, PageProps, Paginated, PeriodeInfo } from '@/types';

export interface BatchRingkas {
    id: string;
    nomor: string;
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

const VALIDASI = [
    'Format kolom & header sesuai modul',
    'Kecocokan periode BLTH & unit ULP',
    'Total baris cocok dengan GRAND TOTAL',
    'Deteksi berkas ganda (duplikat hash)',
    'Cek login petugas ACMT (alias check)',
];

/** 02.3 Portal Unggah Data. */
export default function PortalUnggah() {
    const { periode, periodeOptions, periodeTerbuka, modulOptions, modulMenunggu, filters, batches, maksKb } = usePage<Props>().props;
    const [format, setFormat] = useState(false);
    const rollback = useConfirmAction<BatchRingkas>();
    const form = useForm<{ file: File | null; periode: string; modul: string }>({ file: null, periode: '', modul: '' });
    const terbaru = batches.data[0];

    // Perbarui daftar setiap 2 detik selama masih ada batch yang diproses.
    const adaDiproses = batches.data.some((b) => b.status === 'diproses');
    useEffect(() => {
        if (!adaDiproses) return;
        const t = setInterval(() => router.reload({ only: ['batches', 'navBadges'] }), 2000);
        return () => clearInterval(t);
    }, [adaDiproses]);

    const kirim = (file: File) => {
        form.transform((d) => ({ ...d, file, periode: periode?.kode ?? '' }));
        form.post(route('unggah.store'), { forceFormData: true });
    };

    const columns: ColumnDef<BatchRingkas>[] = [
        { header: 'BATCH ID', cell: ({ row }) => <span className="font-mono font-bold whitespace-nowrap">{row.original.nomor}</span> },
        { header: 'MODUL', cell: ({ row }) => row.original.modul_label },
        {
            header: 'BERKAS',
            cell: ({ row: { original: b } }) => (
                <span className="block max-w-[220px] truncate" title={`${b.file_name} · ${formatTanggalWaktu(b.created_at)} · ${b.pengunggah ?? ''}`}>
                    {b.file_name}
                </span>
            ),
        },
        { header: 'TOTAL BARIS', cell: ({ row }) => (row.original.baris === null ? '–' : `${formatNumber(row.original.baris)} baris`) },
        {
            header: 'STATUS',
            cell: ({ row: { original: b } }) => (
                <span className="inline-flex items-center gap-1.5">
                    {b.status === 'diproses' && <LoaderCircle className="size-3.5 animate-spin text-pln-blue" />}
                    <StatusChip {...labelBatch(b.status, b.error, b.warning)} />
                </span>
            ),
        },
        {
            id: 'aksi',
            header: 'AKSI',
            cell: ({ row: { original: b } }) => (
                <div className="flex gap-2">
                    {b.status === 'committed' && periode?.terbuka && (
                        <Button variant="reject" size="sm" onClick={() => rollback.ask(b)}>
                            Rollback
                        </Button>
                    )}
                    <Button variant={b.status === 'siap_commit' ? 'default' : 'secondary'} size="sm" asChild>
                        <Link href={route('unggah.show', b.id)}>{b.status === 'siap_commit' ? 'Tinjau Batch' : b.status === 'gagal' ? 'Lihat Error' : 'Detail'}</Link>
                    </Button>
                </div>
            ),
        },
    ];

    const tile = (value: string, label: string) => {
        const aktif = form.data.modul === value;
        return (
            <button
                key={value}
                type="button"
                role="radio"
                aria-checked={aktif}
                onClick={() => form.setData('modul', value)}
                className={cn(
                    'flex flex-col items-start gap-1 rounded-10 border px-3 py-2.5 text-left text-[11px] font-bold transition-colors',
                    aktif ? 'border-pln-blue bg-pln-blue text-white' : 'border-line bg-canvas text-ink-soft hover:border-line-strong',
                )}
            >
                {label}
                <StatusChip label="Siap" tone="success" className={aktif ? 'bg-[#3b82f6] text-white' : undefined} />
            </button>
        );
    };

    return (
        <>
            <Head title="Portal Unggah Data" />
            <PageHeader
                title="Portal Unggah & Pemrosesan Data"
                description="Unggah berkas ekspor ACMT & AP2T per periode BLTH · Validasi otomatis sebelum commit"
                actions={
                    <>
                        <Button variant="secondary" onClick={() => setFormat(true)}>
                            Format Kolom Wajib
                        </Button>
                        <PeriodeSelect value={periode?.kode} options={periodeOptions} params={{ modul: filters.modul }} />
                        <ChipPeriode periode={periode} />
                    </>
                }
            />

            <div className="grid gap-4">
                <section className="rounded-2xl border border-line bg-white p-4 shadow-kartu">
                    <h2 className="text-[11px] font-bold text-muted-ink">PILIH MODUL UNGGAH BERKAS</h2>
                    <div role="radiogroup" aria-label="Modul" className="mt-3 flex flex-wrap gap-2">
                        {tile('', 'Deteksi Otomatis (Semua Sheet)')}
                        {modulOptions.map((m) => tile(m.value, m.label))}
                        {modulMenunggu.map((m) => (
                            <div key={m} className="flex flex-col items-start gap-1 rounded-10 border border-line bg-canvas px-3 py-2.5 text-[11px] font-bold text-slate-mid">
                                {m}
                                <StatusChip label="Menunggu" tone="waiting" />
                            </div>
                        ))}
                    </div>
                    <p className="mt-3 text-[11px] text-muted-ink">Catatan cakupan: Modul TUSBUG ditunda dan belum masuk versi pertama. Commit ulang modul sama menggantikan data lama.</p>
                </section>

                <div className="grid items-start gap-4 xl:grid-cols-[1fr_340px]">
                    {periode?.terbuka ? (
                        <UnggahDropzone
                            maksKb={maksKb}
                            modul={form.data.modul ? (modulOptions.find((m) => m.value === form.data.modul)?.label ?? '') : 'Semua sheet dikenali otomatis'}
                            error={form.errors.file ?? form.errors.modul ?? form.errors.periode}
                            progres={form.processing ? (form.progress?.percentage ?? 0) : null}
                            onPilih={kirim}
                        />
                    ) : (
                        <Notice tone="waiting">
                            {periode
                                ? `Periode ${periode.kode} sudah ditutup, unggah dinonaktifkan.`
                                : 'Belum ada periode. Super Admin perlu membuka periode BLTH lebih dulu.'}
                            {periode && periodeTerbuka.length > 0 && ` Pilih periode terbuka: ${periodeTerbuka.map((p) => p.value).join(', ')}.`}
                        </Notice>
                    )}
                    <section className="rounded-2xl border border-line bg-white p-[18px] shadow-kartu">
                        <h2 className="text-[11px] font-bold text-ink">VALIDASI OTOMATIS SISTEM</h2>
                        <ul className="mt-2 grid gap-2 text-[11px] font-medium text-slate-ink">
                            {VALIDASI.map((v) => (
                                <li key={v}>✓ {v}</li>
                            ))}
                        </ul>
                    </section>
                </div>

                <Kartu
                    judul={`Riwayat Unggah Batch — Periode BLTH ${periode?.kode ?? '–'}`}
                    subjudul="Rollback tersedia selama periode masih Terbuka. Setiap commit/rollback menghitung ulang skor penuh."
                    aksi={
                        <>
                            <NativeSelect
                                className="w-48"
                                aria-label="Saring modul"
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
                            {terbaru && (
                                <Button asChild>
                                    <Link href={route('unggah.show', terbaru.id)}>Buka Detail Batch Terbaru →</Link>
                                </Button>
                            )}
                        </>
                    }
                >
                    <DataTable columns={columns} data={batches} getRowId={(b) => b.id} empty="Belum ada berkas yang diunggah untuk periode ini." />
                </Kartu>
            </div>

            <ModalFormatKolom open={format} onOpenChange={setFormat} />
            <DialogRollback
                batch={rollback.target}
                processing={rollback.processing}
                onClose={rollback.close}
                onConfirm={() => rollback.target && rollback.run('post', route('unggah.rollback', rollback.target.id))}
            />
        </>
    );
}

PortalUnggah.layout = layoutKoordinator;
