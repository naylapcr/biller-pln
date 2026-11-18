import { Link, router, usePage } from '@inertiajs/react';
import { CircleCheck, LoaderCircle, RotateCcw, Upload, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import ConfirmDialog from '@/Components/shared/ConfirmDialog';
import DataTable, { type ColumnDef } from '@/Components/shared/DataTable';
import Notice from '@/Components/shared/Notice';
import PageHeader from '@/Components/shared/PageHeader';
import SegmentedTabs from '@/Components/shared/SegmentedTabs';
import StatusChip from '@/Components/shared/StatusChip';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber, formatTanggalWaktu } from '@/lib/format';
import { STATUS_BATCH, TINGKAT_ISSUE, type TingkatIssue } from '@/lib/status';
import type { PageProps, Paginated } from '@/types';
import type { BatchRingkas } from './Index';

interface Issue {
    id: number;
    sheet: string | null;
    row_number: number | null;
    kode: string;
    tingkat: TingkatIssue;
    pesan: string;
}

interface BarisStaging {
    row_number: number;
    data: Record<string, unknown>;
    issues: { kode: string; tingkat: TingkatIssue }[];
}

type Props = PageProps<{
    batch: BatchRingkas & {
        ringkasan: Record<string, unknown> | null;
        storage_path: string;
        checksum: string;
        periode_terbuka: boolean;
        bisa: { commit: boolean; batal: boolean; rollback: boolean };
        akan_menggantikan: string | null;
    };
    saudara: BatchRingkas[];
    issues: Paginated<Issue>;
    staging: Paginated<BarisStaging>;
    filters: { tingkat?: string };
}>;

type Aksi = 'commit' | 'batal' | 'rollback' | null;

/** Kolom pratinjau per modul (urutan tampil); kolom lain disembunyikan agar tabel tetap terbaca. */
const KOLOM: Record<string, string[]> = {
    pascabayar_tunggal: ['hari_baca', 'petugas_login', 'kode_petugas', 'kode_rbm', 'jml_plg', 'terbaca', 'foto_berhasil', 'mulai_at', 'selesai_at', 'durasi_detik'],
    pascabayar_ganda: ['hari_baca', 'petugas_login', 'kode_petugas', 'kode_rbm', 'jml_plg', 'terbaca', 'foto_berhasil', 'mulai_at', 'selesai_at', 'durasi_detik'],
    baca_ulang: ['idpel', 'petugas_login', 'kode_rbm', 'daya_va', 'tgl_baca', 'stan_awal', 'tgl_baca_bu', 'stan_bu', 'status_bu', 'batas_kwh', 'hasil'],
    prabayar: ['idpel', 'no_meter', 'petugas_login', 'kdrbm', 'tgl_baca', 'kd_baca', 'kwh_sisa', 'latitude', 'longitude', 'koordinat_valid'],
};

const JUDUL_KOLOM: Record<string, string> = {
    hari_baca: 'Hari', petugas_login: 'Petugas', kode_petugas: 'Kode', kode_rbm: 'KODE RBM', jml_plg: 'JMLPLG', terbaca: 'Terbaca',
    foto_berhasil: 'Foto', mulai_at: 'Mulai', selesai_at: 'Selesai', durasi_detik: 'Durasi', idpel: 'IDPEL', daya_va: 'Daya',
    tgl_baca: 'Tgl baca', stan_awal: 'Stan', tgl_baca_bu: 'Tgl BU', stan_bu: 'Stan BU', status_bu: 'Keterangan', batas_kwh: 'Batas kWh',
    hasil: 'Hasil', no_meter: 'No meter', kdrbm: 'KDRBM', kd_baca: 'KDBACA', kwh_sisa: 'kWh sisa', latitude: 'Lintang',
    longitude: 'Bujur', koordinat_valid: 'Koordinat',
};

export default function UnggahShow() {
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

    const kolomData = KOLOM[batch.modul ?? ''] ?? Object.keys(staging.data[0]?.data ?? {});
    const columnsStaging: ColumnDef<BarisStaging>[] = [
        {
            header: 'Baris',
            meta: { className: 'w-16' },
            cell: ({ row: { original: r } }) => (
                <div>
                    <span className="font-mono text-[13px]">{r.row_number}</span>
                    {r.issues.map((i, n) => (
                        <span key={n} title={i.kode} className={`ml-1 inline-block size-2 rounded-full ${i.tingkat === 'error' ? 'bg-pln-red' : i.tingkat === 'warning' ? 'bg-pln-yellow' : 'bg-pln-blue'}`} />
                    ))}
                </div>
            ),
        },
        ...kolomData.map<ColumnDef<BarisStaging>>((k) => ({
            id: k,
            header: JUDUL_KOLOM[k] ?? k,
            cell: ({ row }) => <span className="font-mono text-[12px] whitespace-nowrap">{tampil(k, row.original.data[k])}</span>,
        })),
    ];

    const columnsIssue: ColumnDef<Issue>[] = [
        { header: 'Baris', meta: { className: 'w-20' }, cell: ({ row }) => <span className="font-mono text-[13px]">{row.original.row_number ?? 'berkas'}</span> },
        { header: 'Tingkat', meta: { className: 'w-28' }, cell: ({ row }) => <StatusChip {...TINGKAT_ISSUE[row.original.tingkat]} /> },
        { header: 'Kode', cell: ({ row }) => <span className="font-mono text-[12px]">{row.original.kode}</span> },
        { header: 'Pesan', cell: ({ row }) => <span className="text-[13px]">{row.original.pesan}</span> },
    ];

    const r = batch.ringkasan ?? {};
    const jumlahIssue = (r.issues as { error: number; warning: number; info: number } | undefined) ?? { error: 0, warning: 0, info: 0 };

    return (
        <AppLayout title={batch.modul_label} breadcrumbs={[{ label: 'Unggah data', href: route('unggah.index', { periode: batch.periode }) }, { label: batch.modul_label }]}>
            <PageHeader
                title={batch.modul_label}
                description={
                    <>
                        {batch.file_name}
                        {batch.sheet && ` · sheet ${batch.sheet}`} · BLTH {batch.periode} · diunggah {batch.pengunggah} {formatTanggalWaktu(batch.created_at)}
                    </>
                }
                actions={
                    <div className="flex flex-wrap gap-2">
                        {batch.bisa.batal && (
                            <Button variant="secondary" onClick={() => setAksi('batal')}>
                                <X />
                                Batalkan
                            </Button>
                        )}
                        {batch.bisa.rollback && (
                            <Button variant="reject" onClick={() => setAksi('rollback')}>
                                <RotateCcw />
                                Rollback
                            </Button>
                        )}
                        {batch.bisa.commit && (
                            <Button onClick={() => setAksi('commit')}>
                                <CircleCheck />
                                Commit
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="mb-5 flex flex-wrap items-center gap-2">
                {batch.status === 'diproses' && <LoaderCircle className="size-4 animate-spin text-pln-blue" />}
                <StatusChip {...STATUS_BATCH[batch.status]} />
                {batch.committed_at && <span className="text-[13px] text-muted-ink">di-commit {formatTanggalWaktu(batch.committed_at)}</span>}
            </div>

            {batch.status === 'diproses' && (
                <Notice tone="info" title="Berkas sedang dibaca dan divalidasi" className="mb-5">
                    Halaman ini diperbarui otomatis. Sheet besar (±10.000 baris) butuh sekitar 30 detik.
                </Notice>
            )}
            {batch.status === 'gagal' && (
                <Notice tone="danger" title="Batch tidak bisa di-commit" className="mb-5">
                    Ada {formatNumber(jumlahIssue.error)} error. Perbaiki berkas di sumbernya lalu unggah ulang; baris dengan error tercantum di bawah.
                </Notice>
            )}
            {batch.status === 'siap_commit' && (
                <Notice tone="waiting" title="Lolos validasi, menunggu keputusan Anda" className="mb-5">
                    {jumlahIssue.warning > 0 ? `${formatNumber(jumlahIssue.warning)} warning tetap bisa di-commit, tetapi periksa dulu. ` : ''}
                    {batch.akan_menggantikan
                        ? `Commit akan menggantikan data dari berkas ${batch.akan_menggantikan} untuk modul ini. `
                        : ''}
                    Setelah commit, skor seluruh biller periode ini dihitung ulang.
                </Notice>
            )}

            <Ringkasan modul={batch.modul} r={r} />

            {saudara.length > 0 && (
                <section className="mb-6">
                    <h2 className="mb-2 text-sm font-semibold text-ink">Batch lain dari berkas yang sama</h2>
                    <div className="flex flex-wrap gap-2">
                        {saudara.map((s) => (
                            <Link key={s.id} href={route('unggah.show', s.id)} className="inline-flex items-center gap-2 rounded-10 border border-border px-3 py-2 text-sm hover:bg-table-head">
                                {s.modul_label}
                                <StatusChip {...STATUS_BATCH[s.status]} />
                            </Link>
                        ))}
                    </div>
                </section>
            )}

            <section className="mb-7">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-section-title text-ink">Issue ({formatNumber(issues.total)})</h2>
                    <SegmentedTabs
                        label="Tingkat issue"
                        value={filters.tingkat ?? ''}
                        onChange={(v) => router.get(route('unggah.show', batch.id), v ? { tingkat: v } : {}, { preserveScroll: true, preserveState: true })}
                        items={[
                            { value: '', label: 'Semua' },
                            { value: 'error', label: 'Error', count: jumlahIssue.error },
                            { value: 'warning', label: 'Warning', count: jumlahIssue.warning },
                            { value: 'info', label: 'Info', count: jumlahIssue.info },
                        ]}
                    />
                </div>
                <DataTable columns={columnsIssue} data={issues} getRowId={(i) => String(i.id)} empty="Tidak ada issue. Semua baris lolos validasi." />
            </section>

            <section>
                <h2 className="mb-1 text-section-title text-ink">Pratinjau baris ({formatNumber(staging.total)})</h2>
                <p className="mb-3 text-[13px] text-muted-ink">
                    {batch.status === 'committed' || batch.status === 'digantikan'
                        ? 'Baris staging dihapus setelah commit; data kini ada di tabel final.'
                        : 'Hasil parsing sebelum commit, 50 baris per halaman. Titik berwarna menandai baris yang punya issue.'}
                </p>
                <DataTable columns={columnsStaging} data={staging} getRowId={(r) => String(r.row_number)} empty="Tidak ada baris staging." />
            </section>

            <ConfirmDialog
                open={aksi !== null}
                onOpenChange={(v) => !v && setAksi(null)}
                title={aksi === 'commit' ? 'Commit batch ini?' : aksi === 'rollback' ? 'Rollback batch ini?' : 'Batalkan batch ini?'}
                description={
                    aksi === 'commit'
                        ? `${formatNumber((r.baris as number) ?? 0)} baris ditulis ke tabel final${batch.akan_menggantikan ? ` menggantikan berkas ${batch.akan_menggantikan}` : ''}, lalu skor periode ${batch.periode} dihitung ulang.`
                        : aksi === 'rollback'
                          ? 'Data batch ini dihapus dari tabel final. Bila batch ini menggantikan batch sebelumnya, data batch sebelumnya dipulihkan dari berkas aslinya. Skor dihitung ulang.'
                          : 'Batch tidak akan di-commit dan baris stagingnya dihapus.'
                }
                confirmLabel={aksi === 'commit' ? 'Commit' : aksi === 'rollback' ? 'Rollback' : 'Batalkan batch'}
                tone={aksi === 'commit' ? 'default' : 'destructive'}
                processing={proses}
                onConfirm={jalankan}
            />

            {batch.status === 'committed' && (
                <p className="mt-6 text-[13px] text-muted-ink">
                    <Upload className="mr-1 inline size-3.5" />
                    Berkas asli tersimpan di {batch.storage_path} (SHA-256 {batch.checksum.slice(0, 12)}…).
                </p>
            )}
        </AppLayout>
    );
}

function tampil(kolom: string, v: unknown): string {
    if (v === null || v === undefined || v === '') return '–';
    if (typeof v === 'boolean') return v ? 'Valid' : 'Di luar wilayah';
    if (kolom.endsWith('_at') || kolom === 'tgl_baca' || kolom === 'tgl_baca_bu') return formatTanggalWaktu(String(v)).replace(' WIB', '');
    if (kolom === 'durasi_detik' && typeof v === 'number') {
        const h = Math.floor(v / 86400);
        const j = Math.floor((v % 86400) / 3600);
        const m = Math.floor((v % 3600) / 60);
        return `${h > 0 ? `${h} hr ` : ''}${j} j ${m} m`;
    }
    if (typeof v === 'number') return kolom === 'latitude' || kolom === 'longitude' ? String(v) : formatNumber(v, Number.isInteger(v) ? 0 : 2);
    return String(v);
}

/** Kartu ringkasan hasil parsing, isinya mengikuti modul. */
function Ringkasan({ modul, r }: { modul: string | null; r: Record<string, any> }) {
    const kartu: { label: string; nilai: string; ket?: string }[] = [];
    if (modul === 'pascabayar_tunggal' || modul === 'pascabayar_ganda') {
        const g = r.grand_total;
        kartu.push(
            { label: 'Baris petugas', nilai: formatNumber(r.baris), ket: `${formatNumber(r.login_unik)} login · hari ${(r.hari_baca ?? []).join(', ')}` },
            { label: 'Σ JMLPLG', nilai: formatNumber(r.jumlah?.jml_plg), ket: g ? `GRAND TOTAL ${formatNumber(g.jml_plg)}` : 'GRAND TOTAL tidak ada' },
            { label: 'Σ TERBACA', nilai: formatNumber(r.jumlah?.terbaca), ket: g ? `GRAND TOTAL ${formatNumber(g.terbaca)}` : undefined },
            { label: 'Σ foto berhasil', nilai: formatNumber(r.jumlah?.foto_berhasil), ket: g ? `GRAND TOTAL ${formatNumber(g.foto_berhasil)}` : undefined },
            { label: 'Hari J', nilai: formatNumber(r.hari_j?.terbaca), ket: `${formatNumber(r.hari_j?.baris)} baris petugas` },
        );
    } else if (modul === 'baca_ulang') {
        const h = r.hasil ?? {};
        kartu.push(
            { label: 'Baris', nilai: formatNumber(r.baris), ket: `${formatNumber(r.idpel_unik)} IDPEL unik` },
            { label: 'Sudah / belum baca', nilai: `${formatNumber(r.sudah_baca)} / ${formatNumber(r.belum_baca)}` },
            { label: 'Terbukti salah', nilai: formatNumber((h.salah_turun ?? 0) + (h.salah_lebih ?? 0)), ket: `${formatNumber(h.salah_turun)} stan turun · ${formatNumber(h.salah_lebih)} melebihi batas` },
            { label: 'Wajar / tidak dinilai', nilai: `${formatNumber(h.wajar)} / ${formatNumber(h.tidak_dinilai)}` },
        );
    } else if (modul === 'prabayar') {
        kartu.push(
            { label: 'Pemeriksaan', nilai: formatNumber(r.baris), ket: `${formatNumber(r.idpel_unik)} IDPEL unik · ${formatNumber(r.login_unik)} login` },
            { label: 'KDBACA', nilai: Object.entries(r.kd_baca ?? {}).slice(0, 4).map(([k, n]) => `${k}: ${formatNumber(n as number)}`).join(' · ') || '–' },
            { label: 'Koordinat valid', nilai: formatNumber(r.koordinat_valid), ket: `AKURASI kosong di ${formatNumber(r.akurasi_kosong)} baris` },
            { label: 'STATUS TEMPER nyala', nilai: formatNumber(r.temper_nyala) },
        );
    }
    if (kartu.length === 0) return null;

    return (
        <section className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            {kartu.map((k) => (
                <div key={k.label} className="rounded-14 border border-border p-4">
                    <p className="text-[13px] text-muted-ink">{k.label}</p>
                    <p className="mt-1 font-mono text-lg font-semibold text-ink">{k.nilai}</p>
                    {k.ket && <p className="mt-0.5 text-xs text-muted-ink">{k.ket}</p>}
                </div>
            ))}
        </section>
    );
}
