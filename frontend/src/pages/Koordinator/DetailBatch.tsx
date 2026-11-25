import { Head, Link, router, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import BarisIssue, { type Issue } from '@/components/koordinator/BarisIssue';
import DialogRollback from '@/components/koordinator/DialogRollback';
import KartuBatchDitolak, { type CekOtomatis } from '@/components/koordinator/KartuBatchDitolak';
import RingkasanBatch from '@/components/koordinator/RingkasanBatch';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import DataTable, { Pagination, type ColumnDef } from '@/components/ui/DataTable';
import Kartu from '@/components/ui/Kartu';
import Notice from '@/components/ui/Notice';
import PageHeader from '@/components/ui/PageHeader';
import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { layoutKoordinator } from '@/layouts/KoordinatorLayout/KoordinatorLayout';
import { formatNumber, formatTanggal, formatTanggalWaktu } from '@/lib/format';
import { labelBatch, type StatusMeta, type TingkatIssue } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { PageProps, Paginated } from '@/types';
import type { BatchRingkas } from './PortalUnggah';

interface BarisStaging {
    row_number: number;
    data: Record<string, unknown>;
    biller: string | null;
    issues: { kode: string; tingkat: TingkatIssue }[];
}

type Props = PageProps<{
    batch: BatchRingkas & {
        ringkasan: Record<string, unknown> | null;
        storage_path: string;
        checksum: string;
        periode_terbuka: boolean;
        bisa: { commit: boolean; batal: boolean; rollback: boolean };
        cek: CekOtomatis[];
        login: { terpetakan: number; belum: string[] };
        akan_menggantikan: string | null;
    };
    saudara: BatchRingkas[];
    issues: Paginated<Issue>;
    staging: Paginated<BarisStaging>;
    filters: { tingkat?: string };
}>;

type Aksi = 'commit' | 'batal' | 'rollback' | null;

/** Kolom pratinjau per modul setelah LOGIN ACMT dan PEMETAAN BILLER. */
const KOLOM: Record<string, string[]> = {
    pascabayar_tunggal: ['kode_rbm', 'hari_baca', 'jml_plg', 'terbaca', 'foto_berhasil', 'rentang'],
    pascabayar_ganda: ['kode_rbm', 'hari_baca', 'jml_plg', 'terbaca', 'foto_berhasil', 'rentang'],
    baca_ulang: ['idpel', 'kode_rbm', 'tgl_baca', 'stan_awal', 'tgl_baca_bu', 'stan_bu', 'hasil'],
    prabayar: ['idpel', 'no_meter', 'kdrbm', 'tgl_baca', 'kd_baca', 'koordinat_valid'],
};

const JUDUL_KOLOM: Record<string, string> = {
    hari_baca: 'HARI', kode_rbm: 'KODE RBM', jml_plg: 'JML PLG', terbaca: 'TERBACA', foto_berhasil: 'FOTO', rentang: 'MULAI – SELESAI',
    idpel: 'IDPEL', tgl_baca: 'TGL BACA', stan_awal: 'STAN', tgl_baca_bu: 'TGL BU', stan_bu: 'STAN BU', hasil: 'HASIL',
    no_meter: 'NO. METER', kdrbm: 'KODE RBM', kd_baca: 'KODE BACA', koordinat_valid: 'KOORDINAT',
};

const MONO = ['kode_rbm', 'kdrbm', 'idpel', 'no_meter', 'rentang', 'tgl_baca', 'tgl_baca_bu'];

function tampil(kolom: string, d: Record<string, unknown>): string {
    if (kolom === 'rentang') return `${formatTanggal(d.mulai_at as string, 'd/MM HH:mm')} – ${formatTanggal(d.selesai_at as string, 'd/MM HH:mm')}`;
    const v = d[kolom];
    if (v === null || v === undefined || v === '') return '–';
    if (typeof v === 'boolean') return v ? 'Valid' : 'Di luar wilayah';
    if (kolom.startsWith('tgl_')) return formatTanggal(String(v), 'd/MM/yy HH:mm');
    if (typeof v === 'number') return formatNumber(v, Number.isInteger(v) ? 0 : 2);
    return String(v);
}

/** 02.4 Detail Batch dan 04.1 Batch Ditolak. */
export default function DetailBatch() {
    const { batch, saudara, issues, staging, filters } = usePage<Props>().props;
    const [aksi, setAksi] = useState<Aksi>(null);
    const [proses, setProses] = useState(false);

    useEffect(() => {
        if (batch.status !== 'diproses' && !saudara.some((s) => s.status === 'diproses')) return;
        const t = setInterval(() => router.reload(), 2000);
        return () => clearInterval(t);
    }, [batch.status, saudara]);

    const jalankan = () => {
        const url = {
            commit: route('unggah.commit', batch.id),
            rollback: route('unggah.rollback', batch.id),
            batal: route('unggah.destroy', batch.id),
        }[aksi!];
        router.visit(url, {
            method: aksi === 'batal' ? 'delete' : 'post',
            preserveScroll: true,
            onStart: () => setProses(true),
            onFinish: () => {
                setProses(false);
                setAksi(null);
            },
        });
    };

    const ditolak = batch.status === 'gagal';
    const { error, warning, info } = batch;
    const chip: StatusMeta = ditolak
        ? { label: `${formatNumber(error)} Error Fatal · Commit Diblokir`, tone: 'danger' }
        : batch.status === 'siap_commit'
          ? { label: `0 Error · ${formatNumber(warning)} Warning · Siap Commit`, tone: warning > 0 ? 'waiting' : 'success' }
          : labelBatch(batch.status, error, warning);

    const columnsStaging: ColumnDef<BarisStaging>[] = [
        {
            header: 'LOGIN ACMT',
            cell: ({ row: { original: r } }) => <span className={cn('font-mono', !r.biller && 'text-pln-yellow-ink')}>{String(r.data.petugas_login ?? '–')}</span>,
        },
        { header: 'PEMETAAN BILLER', cell: ({ row: { original: r } }) => (r.biller ? `${r.biller} ✓` : <StatusChip label="Belum Terpetakan" tone="waiting" />) },
        ...(KOLOM[batch.modul ?? ''] ?? []).map<ColumnDef<BarisStaging>>((k) => ({
            id: k,
            header: JUDUL_KOLOM[k] ?? k,
            cell: ({ row }) => <span className={cn('whitespace-nowrap', MONO.includes(k) && 'font-mono')}>{tampil(k, row.original.data)}</span>,
        })),
    ];

    return (
        <>
            <Head title={batch.nomor} />
            <PageHeader
                bahaya={ditolak}
                title={`Detail Batch — ${batch.nomor}${ditolak ? ' (DITOLAK)' : ''}`}
                description={
                    ditolak
                        ? `${batch.file_name} · Modul ${batch.modul_label} · Validasi Gagal — Harus Diperbaiki`
                        : `${batch.modul_label} · ${batch.file_name} · Diunggah oleh ${batch.pengunggah} · ${formatTanggalWaktu(batch.created_at)}`
                }
                actions={
                    <>
                        <Button variant="secondary" asChild>
                            <Link href={route('unggah.index', { periode: batch.periode })}>← {ditolak ? 'Kembali ke Portal Unggah' : 'Portal Unggah'}</Link>
                        </Button>
                        <Button variant="secondary" asChild>
                            <a href={route('unggah.berkas', batch.id)}>Berkas Asli</a>
                        </Button>
                        <StatusChip titik {...chip} />
                    </>
                }
            />

            <div className="grid gap-4">
                {batch.status === 'diproses' && (
                    <Notice tone="info" action={<LoaderCircle className="size-4 animate-spin" />}>
                        Berkas sedang dibaca dan divalidasi. Halaman ini diperbarui otomatis; sheet besar (±10.000 baris) butuh sekitar 30 detik.
                    </Notice>
                )}
                {ditolak && <KartuBatchDitolak cek={batch.cek} jumlahError={error} csv={route('unggah.issues', batch.id)} />}

                {!ditolak && <RingkasanBatch modul={batch.modul} r={batch.ringkasan ?? {}} login={batch.login} />}

                {saudara.length > 0 && (
                    <section className="flex flex-wrap items-center gap-2 rounded-2xl border border-line bg-white px-[18px] py-3 shadow-kartu">
                        <p className="text-[11px] font-bold text-muted-ink">BATCH LAIN DARI BERKAS YANG SAMA</p>
                        {saudara.map((s) => (
                            <Link key={s.id} href={route('unggah.show', s.id)} className="inline-flex items-center gap-2 rounded-10 border border-line px-3 py-1.5 text-xs font-semibold text-ink-soft hover:bg-canvas">
                                {s.modul_label}
                                <StatusChip {...labelBatch(s.status, s.error, s.warning)} />
                            </Link>
                        ))}
                    </section>
                )}

                {batch.status !== 'diproses' && (
                    <Kartu
                        judul={ditolak ? 'Rincian Baris Error Validasi (upload_row_issues)' : 'Daftar Error & Warning Validasi'}
                        aksi={
                            <>
                                {!ditolak && <StatusChip label={error ? `${formatNumber(error)} Error` : '0 Error (Lolos)'} tone={error ? 'danger' : 'success'} />}
                                {!ditolak && warning > 0 && <StatusChip label={`${formatNumber(warning)} Warning (Perlu Tinjauan)`} tone="waiting" />}
                                {error + warning + info > 0 && (
                                    <NativeSelect
                                        className="w-40"
                                        aria-label="Tingkat issue"
                                        value={filters.tingkat ?? ''}
                                        onChange={(e) => router.get(route('unggah.show', batch.id), e.target.value ? { tingkat: e.target.value } : {}, { preserveScroll: true, preserveState: true })}
                                    >
                                        <option value="">Semua ({formatNumber(error + warning + info)})</option>
                                        <option value="error">Error ({formatNumber(error)})</option>
                                        <option value="warning">Warning ({formatNumber(warning)})</option>
                                        <option value="info">Info ({formatNumber(info)})</option>
                                    </NativeSelect>
                                )}
                            </>
                        }
                    >
                        {issues.data.length === 0 ? (
                            <p className="text-xs text-muted-ink">Tidak ada issue. Semua baris lolos validasi.</p>
                        ) : (
                            <>
                                <ul className="grid gap-2.5">
                                    {issues.data.map((i, n) => (
                                        <BarisIssue key={i.id} issue={i} nomor={(issues.from ?? 1) + n} />
                                    ))}
                                </ul>
                                {issues.last_page > 1 && <Pagination page={issues} />}
                            </>
                        )}
                    </Kartu>
                )}

                {staging.total > 0 ? (
                    <Kartu judul={`Pratinjau Data Berkas (${staging.current_page === 1 ? `${staging.data.length} Baris Pertama` : `Baris ${staging.from}–${staging.to}`})`}>
                        <DataTable columns={columnsStaging} data={staging} getRowId={(r) => String(r.row_number)} />
                    </Kartu>
                ) : (
                    (batch.status === 'committed' || batch.status === 'digantikan') && (
                        <Notice tone="success">
                            Data sudah di tabel final{batch.committed_at && ` sejak ${formatTanggalWaktu(batch.committed_at)}`}; baris staging dihapus. Berkas asli tersimpan (SHA-256 {batch.checksum.slice(0, 12)}…).
                        </Notice>
                    )
                )}

                {(batch.status === 'siap_commit' || ditolak || batch.bisa.rollback) && (
                    <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-line-strong bg-white px-5 py-4 shadow-kartu">
                        <p className="min-w-0 flex-1 basis-72 text-xs leading-[15px] text-slate-mid">
                            {ditolak
                                ? `Perbaiki ${formatNumber(error)} error di atas pada berkas sumber lalu unggah kembali melalui Portal Unggah Data.`
                                : batch.status === 'siap_commit'
                                  ? batch.akan_menggantikan
                                      ? `Commit akan menggantikan data dari berkas ${batch.akan_menggantikan} & menghitung ulang skor seluruh biller.`
                                      : 'Commit menulis data ke tabel final & menghitung ulang skor seluruh biller.'
                                  : 'Rollback tersedia selama periode masih Terbuka. Skor seluruh biller dihitung ulang.'}
                        </p>
                        <div className="flex flex-wrap gap-2.5">
                            {batch.bisa.batal && (
                                <Button variant={ditolak ? 'destructive' : 'reject'} onClick={() => setAksi('batal')}>
                                    {ditolak ? 'Hapus Batch Gagal' : 'Batalkan Batch'}
                                </Button>
                            )}
                            {batch.bisa.rollback && (
                                <Button variant="reject" onClick={() => setAksi('rollback')}>
                                    Rollback
                                </Button>
                            )}
                            {ditolak && (
                                <Button disabled className="bg-line text-faint shadow-none disabled:opacity-100">
                                    Commit Dinonaktifkan (Ada Error)
                                </Button>
                            )}
                            {batch.status === 'siap_commit' && (
                                <Button variant="success" onClick={() => setAksi('commit')} disabled={!batch.bisa.commit}>
                                    Commit & Hitung Ulang Skor ✓
                                </Button>
                            )}
                        </div>
                    </section>
                )}
            </div>

            <ConfirmDialog
                open={aksi === 'commit'}
                onOpenChange={(v) => !v && setAksi(null)}
                chip={batch.akan_menggantikan ? { label: 'TIMPA DATA LAMA · MODUL SAMA', tone: 'info' } : { label: 'COMMIT BATCH · PERIODE TERBUKA', tone: 'info' }}
                garis="biru"
                title={batch.akan_menggantikan ? `Commit Ulang Modul ${batch.modul_label}?` : `Commit ${batch.nomor}?`}
                description={
                    batch.akan_menggantikan
                        ? `Batch lama (${batch.akan_menggantikan}) → Digantikan (Superseded). ${formatNumber(batch.baris ?? 0)} baris baru ditulis ke tabel final, lalu skor periode ${batch.periode} dihitung ulang.`
                        : `${formatNumber(batch.baris ?? 0)} baris ditulis ke tabel final, lalu skor periode ${batch.periode} dihitung ulang.`
                }
                confirmLabel={batch.akan_menggantikan ? 'Timpa & Hitung Ulang ✓' : 'Commit & Hitung Ulang ✓'}
                processing={proses}
                onConfirm={jalankan}
            />
            <ConfirmDialog
                open={aksi === 'batal'}
                onOpenChange={(v) => !v && setAksi(null)}
                chip={{ label: 'BATALKAN BATCH', tone: 'danger' }}
                garis="merah"
                title={`Batalkan ${batch.nomor}?`}
                description="Batch tidak akan di-commit dan baris stagingnya dihapus."
                confirmLabel="Ya, Batalkan Batch"
                tone="destructive"
                processing={proses}
                onConfirm={jalankan}
            />
            <DialogRollback batch={aksi === 'rollback' ? batch : null} processing={proses} onClose={() => setAksi(null)} onConfirm={jalankan} />
        </>
    );
}

DetailBatch.layout = layoutKoordinator;
