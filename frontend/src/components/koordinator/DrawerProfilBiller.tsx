import { LoaderCircle, X } from 'lucide-react';
import { useEffect } from 'react';

import TrenSkor, { ChipSelisih } from '@/components/shared/TrenSkor';
import { BarisNilai } from '@/components/ui/Kartu';
import StatusChip from '@/components/ui/StatusChip';
import { formatNumber, formatPercent, formatTanggal } from '@/lib/format';
import { metaKategori } from '@/lib/status';
import { cn } from '@/lib/utils';
import type { HistoriPasca, Kesalahan, SkorBiller, TitikTren } from '@/types';

export interface DetailBiller {
    skor: SkorBiller;
    tren: TitikTren[];
    kesalahan: Kesalahan[];
    histori: HistoriPasca[];
}

const rasio = (r: number | undefined, n: number | undefined) => (r === undefined ? '–' : `${formatPercent(r * 100, 2)} (${formatNumber(n)})`);

function Judul({ children }: { children: string }) {
    return <h3 className="text-[11px] font-bold text-muted-ink">{children}</h3>;
}

function KotakRasio({ label, nilai, buruk }: { label: string; nilai: string; buruk: boolean }) {
    return (
        <div className={cn('rounded-10 px-3 py-2.5', buruk ? 'bg-error-tint text-error-ink' : 'bg-success-tint text-success-ink')}>
            <p className="text-[10px] leading-[13px] font-semibold">{label}</p>
            <p className="mt-1 text-sm leading-[18px] font-extrabold">{nilai}</p>
        </div>
    );
}

/**
 * Drawer profil biller (02.2): skor dan peringkat, nilai per modul R · E · K, prabayar, bonus Hari J,
 * rasio kesalahan, tren 4 periode, dan histori WO terbaru. Di ≥ 1280 px menjadi panel di kanan
 * tabel, di bawahnya menutup layar.
 */
export default function DrawerProfilBiller({ onClose, detail }: { onClose: () => void; detail: DetailBiller | null }) {
    useEffect(() => {
        const esc = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', esc);
        return () => window.removeEventListener('keydown', esc);
    }, [onClose]);

    const s = detail?.skor;
    const pasca = s?.nilai_per_modul.pascabayar;
    const pra = s?.nilai_per_modul.prabayar;

    return (
        <aside
            role="dialog"
            aria-label="Profil biller"
            className="fixed inset-x-3 inset-y-4 z-40 flex flex-col gap-3.5 overflow-y-auto rounded-2xl border-[1.5px] border-sky-line bg-white p-5 shadow-[0_0_0_100vmax_rgb(15_23_42/0.35)] xl:sticky xl:inset-auto xl:top-[100px] xl:z-10 xl:max-h-[calc(100dvh-120px)] xl:shadow-kartu"
        >
            <div className="flex items-center justify-between gap-3">
                <StatusChip label="DRAWER PROFIL BILLER" tone="sky" />
                <button type="button" onClick={onClose} aria-label="Tutup profil" className="-mr-2 inline-flex size-9 items-center justify-center rounded-lg text-muted-ink hover:bg-canvas">
                    <X className="size-4" />
                </button>
            </div>
            {!s ? (
                <div className="flex h-40 items-center justify-center text-muted-ink">
                    <LoaderCircle className="animate-spin" />
                </div>
            ) : (
                <>
                    <div>
                        <p className="text-lg leading-[23px] font-extrabold text-ink">{s.biller.nama}</p>
                        <p className="font-mono text-[11px] break-words text-slate-mid">
                            NIP: {s.biller.nip ?? '–'} · ACMT: {s.biller.login_acmt} ({s.biller.kode_petugas})
                        </p>
                    </div>

                    <div className="flex items-center justify-between gap-3 rounded-xl bg-pln-blue-dark px-4 py-3.5">
                        <div>
                            <p className="text-[10px] font-semibold text-sky-side">SKOR AKHIR (BLTH {s.periode})</p>
                            <p className="mt-0.5 text-[28px] leading-[35px] font-extrabold text-white">{formatNumber(s.final_score, 1)}</p>
                        </div>
                        <div className="flex flex-col items-end gap-1">
                            <StatusChip label={`#${s.peringkat ?? '–'} / ${s.jumlah_biller} · ${metaKategori(s.kategori).label}`} tone={metaKategori(s.kategori).tone} />
                            <p className="text-[11px] font-medium text-line">Bonus Hari J: +{formatNumber(s.bonus_hari_j.nilai, 1)}</p>
                        </div>
                    </div>

                    <Judul>NILAI PER MODUL (R · E · K)</Judul>
                    {pasca && (
                        <>
                            <BarisNilai label="Realisasi Pascabayar" keterangan={`${formatNumber(pasca.terbaca)} / ${formatNumber(pasca.jml_plg)} pelanggan terbaca`} nilai={formatPercent(pasca.realisasi)} />
                            <BarisNilai label="Persentase Eviden Foto" keterangan={`${formatNumber(pasca.foto)} / ${formatNumber(pasca.terbaca)} foto valid`} nilai={formatPercent(pasca.eviden)} />
                            <BarisNilai
                                label="Kecepatan Baca (ACMT)"
                                keterangan={`Standar ${formatNumber(s.aturan?.sla_plg_per_jam ?? 33.3, 1)} plg/jam (${formatNumber(pasca.kecepatan.nilai, 1)})`}
                                nilai={`${formatNumber(pasca.kecepatan.plg_per_jam, 1)} /jam`}
                            />
                        </>
                    )}
                    <BarisNilai
                        label="Modul Prabayar"
                        keterangan={pra ? `Normal ${formatPercent(pra.realisasi)} · Koord. Valid ${formatPercent(pra.eviden)}` : 'Tidak ada data prabayar'}
                        nilai={pra ? formatNumber(pra.nilai, 1) : '—'}
                    />
                    <BarisNilai
                        label="Bonus Hari J (Di Luar Rata-rata)"
                        keterangan={s.bonus_hari_j.jml_plg > 0 ? `${formatPercent(s.bonus_hari_j.persen_tuntas, 0)} tuntas Hari J` : 'Tidak ada pelanggan Hari J'}
                        nilai={`+${formatNumber(s.bonus_hari_j.nilai, 1)}`}
                    />

                    <Judul>RASIO KESALAHAN (PEMOTONGAN)</Judul>
                    <div className="grid grid-cols-2 gap-2.5">
                        <KotakRasio label="Baca Ulang (Pemilik RBM)" nilai={rasio(pasca?.baca_ulang.rasio, pasca?.baca_ulang.kasus_salah)} buruk={(pasca?.baca_ulang.kasus_salah ?? 0) > 0} />
                        <KotakRasio label="Koreksi AP2T (Biller)" nilai={rasio(pasca?.ap2t.rasio, pasca?.ap2t.kasus_salah)} buruk={(pasca?.ap2t.kasus_salah ?? 0) > 0} />
                    </div>

                    <div className="rounded-xl border border-line bg-canvas p-3">
                        <div className="mb-2 flex items-center justify-between gap-2">
                            <Judul>TREN SKOR 4 PERIODE (BLTH)</Judul>
                            <ChipSelisih data={detail.tren.map((t) => ({ label: t.label, nilai: t.final_score }))} />
                        </div>
                        <TrenSkor data={detail.tren} />
                    </div>

                    {detail.kesalahan.length > 0 && (
                        <>
                            <Judul>{`KASUS KESALAHAN (${detail.kesalahan.length})`}</Judul>
                            <ul className="grid gap-2">
                                {detail.kesalahan.slice(0, 8).map((k) => (
                                    <li key={k.id} className="rounded-10 bg-canvas px-3 py-2">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-mono text-[11px] text-ink">{k.idpel}</span>
                                            <StatusChip label={k.dihitung ? k.hasil_label : 'Tidak dihitung'} tone={k.dihitung ? 'danger' : 'neutral'} />
                                        </div>
                                        <p className="mt-1 text-[10px] text-muted-ink">
                                            {k.sumber} · {k.kode_rbm ?? '–'} · {formatNumber(k.stan_awal)} → {formatNumber(k.stan_bu)}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}

                    <Judul>HISTORI WO TERBARU</Judul>
                    <ul className="grid gap-2">
                        {detail.histori.map((h) => (
                            <li key={h.id} className="flex items-center justify-between gap-3 rounded-10 bg-canvas px-3 py-2">
                                <div className="min-w-0">
                                    <p className="text-[11px] font-semibold text-ink-soft">
                                        Hari {h.hari_baca} · <span className="font-mono">{h.kode_rbm}</span>
                                    </p>
                                    <p className="text-[10px] text-muted-ink">
                                        {formatTanggal(h.mulai_at, 'd MMM')} · {formatNumber(h.terbaca)} / {formatNumber(h.jml_plg)} plg
                                    </p>
                                </div>
                                <span className="shrink-0 text-[11px] font-bold text-pln-blue">{h.plg_per_jam === null ? '–' : `${formatNumber(h.plg_per_jam, 1)} /jam`}</span>
                            </li>
                        ))}
                        {detail.histori.length === 0 && <li className="text-[11px] text-muted-ink">Belum ada data pascabayar.</li>}
                    </ul>
                </>
            )}
        </aside>
    );
}
