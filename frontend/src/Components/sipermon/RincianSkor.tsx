import StatusChip from '@/Components/shared/StatusChip';
import { formatNumber, formatPercent, formatTanggalWaktu } from '@/lib/format';
import { KATEGORI, MODUL_LABEL } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { KasusDenda, SkorBiller } from '@/types';

/**
 * Rincian skor satu biller: skor akhir, poin per modul, bonus Hari J, denda, indikator,
 * dan kelengkapan eviden. Dipakai di halaman Skor saya dan drawer Kinerja biller.
 */
export default function RincianSkor({ skor, kasus }: { skor: SkorBiller; kasus: KasusDenda[] }) {
    const modul = Object.entries(skor.poin_per_modul);
    const kategori = skor.kategori ? KATEGORI[skor.kategori] : null;

    if (skor.final_score === null) {
        return (
            <p className="rounded-14 border border-dashed border-border p-6 text-center text-sm text-muted-ink">
                Belum ada data untuk biller ini di periode {skor.periode}. Skor muncul setelah berkas ACMT di-commit.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <section className="grid gap-3 sm:grid-cols-3">
                <Angka label="Skor akhir" nilai={formatNumber(skor.final_score, 1)} besar>
                    {skor.is_final ? 'Skor final' : 'Skor sementara, periode masih terbuka'}
                </Angka>
                <Angka label="Peringkat" nilai={skor.peringkat ? `#${skor.peringkat}` : '–'}>
                    dari {formatNumber(skor.jumlah_biller)} biller
                </Angka>
                <div className="rounded-14 border border-border p-4">
                    <p className="text-[13px] text-muted-ink">Kategori</p>
                    <div className="mt-2">{kategori ? <StatusChip {...kategori} /> : '–'}</div>
                    <p className="mt-2 text-xs text-muted-ink">Dihitung {formatTanggalWaktu(skor.dihitung_at)}</p>
                </div>
            </section>

            <section>
                <h3 className="mb-2 text-sm font-semibold text-ink">Susunan skor</h3>
                <table className="w-full text-sm">
                    <tbody className="divide-y divide-line-row">
                        {modul.map(([kunci, m]) => (
                            <tr key={kunci}>
                                <td className="py-2.5">
                                    <p className="font-medium">Poin {MODUL_LABEL[kunci] ?? kunci}</p>
                                    <p className="text-xs text-muted-ink">
                                        StanValid {formatNumber(m!.stan_valid)} × {formatNumber(skor.aturan?.w_stan ?? 0, 1)} + Eviden{' '}
                                        {formatNumber(m!.eviden)} × {formatNumber(skor.aturan?.w_eviden ?? 0, 1)}
                                        {kunci === 'pascabayar' && m!.kasus_bu ? ` (terbaca ${formatNumber(m!.terbaca)} − ${formatNumber(m!.kasus_bu)} kasus BU)` : ''}
                                    </p>
                                </td>
                                <td className="py-2.5 text-right font-mono">{formatNumber(m!.poin, 1)}</td>
                            </tr>
                        ))}
                        {skor.aturan?.mode_agregasi === 'rata_rata_modul' && (
                            <tr>
                                <td className="py-2.5 text-muted-ink">Poin tugas (rata-rata {modul.length} modul)</td>
                                <td className="py-2.5 text-right font-mono">{formatNumber(skor.poin_tugas, 1)}</td>
                            </tr>
                        )}
                        <tr>
                            <td className="py-2.5">
                                <p className="font-medium">Bonus Hari J</p>
                                <p className="text-xs text-muted-ink">{formatNumber(skor.indikator?.terbaca_hari_j ?? 0)} pelanggan Hari J</p>
                            </td>
                            <td className="py-2.5 text-right font-mono text-success-ink">+{formatNumber(skor.bonus_hari_j, 1)}</td>
                        </tr>
                        <tr>
                            <td className="py-2.5">
                                <p className="font-medium">Denda Baca Ulang</p>
                                <p className="text-xs text-muted-ink">{formatNumber(skor.denda.baca_ulang.kasus)} kasus terbukti salah</p>
                            </td>
                            <td className="py-2.5 text-right font-mono text-error-ink">−{formatNumber(skor.denda.baca_ulang.poin, 1)}</td>
                        </tr>
                        <tr>
                            <td className="py-2.5">
                                <p className="font-medium">Denda Koreksi AP2T</p>
                                <p className="text-xs text-muted-ink">{formatNumber(skor.denda.ap2t.kasus)} kasus</p>
                            </td>
                            <td className="py-2.5 text-right font-mono text-error-ink">−{formatNumber(skor.denda.ap2t.poin, 1)}</td>
                        </tr>
                        <tr>
                            <td className="py-2.5">
                                <p className="font-medium">Denda absensi</p>
                                <p className="text-xs text-muted-ink">{formatNumber(skor.denda.absensi.alpa)} hari alpa</p>
                            </td>
                            <td className="py-2.5 text-right font-mono text-error-ink">−{formatNumber(skor.denda.absensi.poin, 1)}</td>
                        </tr>
                        <tr className="font-semibold">
                            <td className="py-2.5">Skor akhir</td>
                            <td className="py-2.5 text-right font-mono">{formatNumber(skor.final_score, 1)}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section className="grid gap-3 sm:grid-cols-2">
                {Object.entries(skor.eviden).map(([kunci, e]) => (
                    <div key={kunci} className="rounded-14 border border-border p-4">
                        <p className="text-[13px] text-muted-ink">Kelengkapan eviden {MODUL_LABEL[kunci] ?? kunci}</p>
                        <p className="mt-1 font-mono text-lg font-semibold">{e.persen === null ? '–' : formatPercent(e.persen)}</p>
                        <p className="text-xs text-muted-ink">
                            {kunci === 'prabayar'
                                ? `${formatNumber(e.eviden)} koordinat valid dari ${formatNumber(e.realisasi)} KDBACA normal`
                                : `${formatNumber(e.eviden)} foto berhasil dari ${formatNumber(e.realisasi)} terbaca`}
                        </p>
                    </div>
                ))}
                {skor.absensi && (
                    <div className="rounded-14 border border-border p-4 sm:col-span-2">
                        <p className="text-[13px] text-muted-ink">Absensi periode ini ({formatNumber(skor.absensi.hari_kerja)} hari kerja sudah lewat)</p>
                        <div className="mt-2 grid grid-cols-4 gap-3 text-sm">
                            {(
                                [
                                    ['Hadir', skor.absensi.hadir, ''],
                                    ['Izin', skor.absensi.izin, ''],
                                    ['Sakit', skor.absensi.sakit, ''],
                                    ['Alpa', skor.absensi.alpa, 'text-error-ink'],
                                ] as const
                            ).map(([label, n, kelas]) => (
                                <div key={label}>
                                    <p className={cn('font-mono font-semibold', kelas)}>{formatNumber(n)}</p>
                                    <p className="text-xs text-muted-ink">{label}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
                <div className="rounded-14 border border-border p-4 sm:col-span-2">
                    <p className="text-[13px] text-muted-ink">Indikator (tidak masuk skor)</p>
                    <div className="mt-2 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p className="font-mono font-semibold">
                                {skor.indikator?.rasio_kelalaian == null ? '–' : formatPercent(skor.indikator.rasio_kelalaian * 100, 2)}
                            </p>
                            <p className="text-xs text-muted-ink">Rasio kelalaian (kasus salah ÷ terbaca)</p>
                        </div>
                        <div>
                            <p className="font-mono font-semibold">{formatNumber(skor.indikator?.rasio_kecepatan ?? null, 2)}</p>
                            <p className="text-xs text-muted-ink">Terbaca per jam kalender (WAKTU ACMT bukan jam kerja)</p>
                        </div>
                    </div>
                </div>
            </section>

            <section>
                <h3 className="mb-2 text-sm font-semibold text-ink">Rekam jejak denda ({formatNumber(kasus.length)})</h3>
                {kasus.length === 0 ? (
                    <p className="text-sm text-muted-ink">Tidak ada kasus denda di periode ini.</p>
                ) : (
                    <div className="max-h-80 overflow-auto rounded-10 border border-border">
                        <table className="w-full text-[13px]">
                            <thead className="sticky top-0 bg-table-head text-left text-muted-ink">
                                <tr>
                                    <th className="px-3 py-2 font-normal">IDPEL</th>
                                    <th className="px-3 py-2 font-normal">Sumber</th>
                                    <th className="px-3 py-2 text-right font-normal">Stan awal → BU</th>
                                    <th className="px-3 py-2 font-normal">Keterangan</th>
                                    <th className="px-3 py-2 text-right font-normal">Denda</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line-row">
                                {kasus.map((k) => (
                                    <tr key={k.id}>
                                        <td className="px-3 py-2 font-mono">{k.idpel}</td>
                                        <td className="px-3 py-2">{k.sumber_label}</td>
                                        <td className="px-3 py-2 text-right font-mono">
                                            {formatNumber(k.stan_awal)} → {formatNumber(k.stan_benar)}
                                        </td>
                                        <td className="px-3 py-2 text-muted-ink">
                                            {k.catatan}
                                            {k.batas_kwh !== null && <span className="block text-xs">batas {formatNumber(k.batas_kwh, 1)} kWh</span>}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-error-ink">−{formatNumber(k.denda_poin, 1)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    );
}

function Angka({ label, nilai, besar, children }: { label: string; nilai: string; besar?: boolean; children?: React.ReactNode }) {
    return (
        <div className="rounded-14 border border-border p-4">
            <p className="text-[13px] text-muted-ink">{label}</p>
            <p className={cn('mt-1 font-mono font-semibold text-ink', besar ? 'text-[26px] leading-tight' : 'text-xl')}>{nilai}</p>
            {children && <p className="mt-1 text-xs text-muted-ink">{children}</p>}
        </div>
    );
}
