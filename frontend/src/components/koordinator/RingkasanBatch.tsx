import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

interface Kotak {
    label: string;
    nilai: string;
    ket?: string;
    tone?: 'cocok' | 'beda' | 'peringatan';
}

const WARNA = { cocok: 'text-success', beda: 'text-pln-red', peringatan: 'text-pln-yellow-ink' };

function grandTotal(jumlah: number | undefined, grand: number | undefined | null): Pick<Kotak, 'ket' | 'tone'> {
    if (grand === undefined || grand === null) return { ket: 'GRAND TOTAL tidak ada di berkas', tone: 'peringatan' };
    const cocok = jumlah === grand;
    return { ket: `GRAND TOTAL: ${formatNumber(grand)} · ${cocok ? 'Cocok ✓' : 'Tidak cocok ✕'}`, tone: cocok ? 'cocok' : 'beda' };
}

/** Ringkasan perhitungan sistem vs GRAND TOTAL berkas (02.4); isinya mengikuti modul. */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export default function RingkasanBatch({ modul, r, login }: { modul: string | null; r: Record<string, any>; login: { terpetakan: number; belum: string[] } }) {
    const total = login.terpetakan + login.belum.length;
    const kotakLogin: Kotak = {
        label: 'Total Petugas Login',
        nilai: `${formatNumber(total || r.login_unik)} Login`,
        ket: total ? `${login.terpetakan} Terpetakan · ${login.belum.length} Belum Terpetakan` : undefined,
        tone: login.belum.length ? 'peringatan' : 'cocok',
    };
    let kotak: Kotak[] = [];
    let judul = 'RINGKASAN HASIL PEMBACAAN BERKAS';

    if (modul === 'pascabayar_tunggal' || modul === 'pascabayar_ganda') {
        judul = 'RINGKASAN PERHITUNGAN SISTEM VS BARIS GRAND TOTAL BERKAS';
        const [j, g] = [r.jumlah ?? {}, r.grand_total ?? {}];
        kotak = [
            { label: 'Total Pelanggan (JML PLG)', nilai: formatNumber(j.jml_plg), ...grandTotal(j.jml_plg, g.jml_plg) },
            { label: 'Total Terbaca', nilai: formatNumber(j.terbaca), ...grandTotal(j.terbaca, g.terbaca) },
            { label: 'Foto Berhasil (Eviden)', nilai: formatNumber(j.foto_berhasil), ...grandTotal(j.foto_berhasil, g.foto_berhasil) },
            kotakLogin,
        ];
    } else if (modul === 'baca_ulang') {
        const h = r.hasil ?? {};
        kotak = [
            { label: 'Total Kasus', nilai: formatNumber(r.baris), ket: `${formatNumber(r.idpel_unik)} IDPEL unik` },
            { label: 'Sudah / Belum Baca', nilai: `${formatNumber(r.sudah_baca)} / ${formatNumber(r.belum_baca)}`, ket: 'Belum baca tidak mengurangi skor' },
            {
                label: 'Terbukti Salah',
                nilai: formatNumber((h.salah_turun ?? 0) + (h.salah_lebih ?? 0)),
                ket: `${formatNumber(h.salah_turun)} stan turun · ${formatNumber(h.salah_lebih)} melebihi batas`,
                tone: 'peringatan',
            },
            kotakLogin,
        ];
    } else if (modul === 'prabayar') {
        kotak = [
            { label: 'Total Pemeriksaan', nilai: formatNumber(r.baris), ket: `${formatNumber(r.idpel_unik)} IDPEL unik` },
            {
                label: 'Kode Baca',
                nilai: Object.entries(r.kd_baca ?? {}).slice(0, 3).map(([k, n]) => `${k}: ${formatNumber(n as number)}`).join(' · ') || '–',
                ket: `STATUS TEMPER nyala: ${formatNumber(r.temper_nyala)}`,
            },
            { label: 'Koordinat Valid', nilai: formatNumber(r.koordinat_valid), ket: `AKURASI kosong di ${formatNumber(r.akurasi_kosong)} baris` },
            kotakLogin,
        ];
    }
    if (kotak.length === 0) return null;

    return (
        <section className="rounded-2xl border border-line bg-white p-[18px] shadow-kartu">
            <h2 className="text-[11px] font-bold text-muted-ink">{judul}</h2>
            <div className="mt-3 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                {kotak.map((k) => (
                    <div key={k.label} className="rounded-xl border border-line bg-canvas px-3.5 py-3">
                        <p className="text-[11px] font-semibold text-slate-mid">{k.label}</p>
                        <p className="mt-1 text-xl leading-[25px] font-extrabold text-ink">{k.nilai}</p>
                        {k.ket && <p className={cn('mt-0.5 text-[11px] font-semibold', k.tone ? WARNA[k.tone] : 'text-muted-ink')}>{k.ket}</p>}
                    </div>
                ))}
            </div>
        </section>
    );
}
