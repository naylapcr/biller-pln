import { Link } from '@inertiajs/react';

import Kartu from '@/components/ui/Kartu';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import type { BarisLeaderboard } from '@/types';

export interface RingkasanFinal {
    rata: number;
    top: BarisLeaderboard[];
    kategori: Record<string, number>;
    batch: number;
}

/** 04.2 Periode ditutup: banner arsip, tiga aksi terkunci, dan ringkasan skor final. */
export default function PeriodeTerkunci({ kode, nama, jumlah, bobot, ringkasan }: { kode: string; nama: string; jumlah: number; bobot: string; ringkasan: RingkasanFinal | null }) {
    const juara = ringkasan?.top[0];
    const terkunci = [
        ['Unggah Berkas ACMT / AP2T', 'Tidak dapat menambah atau menimpa berkas pada periode yang sudah ditutup.', 'Unggah Dinonaktifkan'],
        ['Rollback Batch Committed', 'Riwayat batch hanya dapat dilihat (mode arsip). Rollback tidak tersedia.', 'Rollback Dinonaktifkan'],
        ['Parameter & Bobot Skor', `Bobot (${bobot}) terkunci dalam snapshot.`, 'Edit Parameter Terkunci'],
    ];
    const kotak: [string, string][] = ringkasan
        ? [
              ['Rata-Rata Skor Final', formatNumber(ringkasan.rata, 1)],
              ['Peringkat #1 Final', juara ? `${juara.nama} (${formatNumber(juara.final_score, 1)})` : '–'],
              ['Grade A / Evaluasi / Kritis', `${['Grade A', 'Perlu Evaluasi', 'Kritis'].map((k) => ringkasan.kategori[k] ?? 0).join(' / ')} Biller`],
              ['Total Batch Terkunci', `${ringkasan.batch} Batch Final`],
          ]
        : [];

    return (
        <>
            <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-ink px-[22px] py-[18px]">
                <div className="min-w-0 flex-1 basis-72">
                    <span className="inline-flex rounded-full bg-ink-soft px-2.5 py-1 text-[11px] leading-[14px] font-semibold text-pln-yellow">PERIODE FINAL & ARSIP RESMI</span>
                    <p className="mt-1.5 text-base font-extrabold text-white">
                        Skor {jumlah} Biller Periode {nama} Telah Dikunci Permanen
                    </p>
                    <p className="mt-0.5 max-w-[540px] text-xs leading-[15px] text-line-strong">
                        Seluruh aksi unggah berkas, rollback batch, pemetaan alias periode ini, dan pengubahan bobot parameter telah dinonaktifkan.
                    </p>
                </div>
                <Button variant="secondary" asChild>
                    <Link href={route('pengaturan.periode.index')}>← Kembali ke Periode Aktif</Link>
                </Button>
            </section>
            <div className="grid gap-4 md:grid-cols-3">
                {terkunci.map(([judul, isi, tombol]) => (
                    <div key={judul} className="flex flex-col gap-2.5 rounded-2xl border border-line bg-line-row p-5">
                        <p className="text-sm font-bold text-slate-mid">{judul}</p>
                        <p className="flex-1 text-xs leading-[18px] text-muted-ink">{isi}</p>
                        <span aria-disabled className="rounded-10 bg-line py-2.5 text-center text-xs font-bold text-faint">
                            {tombol}
                        </span>
                    </div>
                ))}
            </div>
            <Kartu judul={`Ringkasan Skor Final Terkunci — BLTH ${kode}`}>
                <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                    {kotak.map(([label, nilai]) => (
                        <div key={label} className="rounded-xl border border-line bg-canvas px-3.5 py-3">
                            <p className="text-[11px] font-semibold text-slate-mid">{label}</p>
                            <p className="mt-1 text-[15px] leading-5 font-extrabold text-ink">{nilai}</p>
                        </div>
                    ))}
                </div>
            </Kartu>
        </>
    );
}
