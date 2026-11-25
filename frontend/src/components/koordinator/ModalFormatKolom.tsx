import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

/**
 * 04.5c Format Kolom Wajib. Mengikuti header asli ekspor ACMT (PRD 5 dan 10.6 no. 4), bukan nama
 * kolom contoh di desain. Hari baca diambil dari grup baris dan sisipan dihitung sistem.
 */
const FORMAT: { modul: string; kolom: string[]; catatan: string }[] = [
    {
        modul: '1. Rekap WBM Pascabayar (Tunggal / Ganda)',
        kolom: ['KDPETUGAS', 'KODE_RBM', 'JMLPLG', 'TERBACA', 'BLMBACA', '% BACA', 'JML FOTO BERHASIL', 'TGL MULAI', 'TGL SELESAI', 'WAKTU'],
        catatan: 'Hari baca dari grup baris "Hari Baca - X"; baris petugas berawalan "Petugas - ".',
    },
    {
        modul: '2. Rekap Baca Ulang (Kualitas)',
        kolom: ['NO', 'UNITUP', 'IDPEL', 'TARIF', 'DAYA', 'KODE RBM', 'TGLBACA', 'STAN METER', 'PEMKWH', 'TGLBACA BU', 'STAN METER BU', 'PEMKWH BU', 'PETUGAS', 'KETERANGAN'],
        catatan: 'Sisipan dihitung sistem dari jendela hari baca RBM, bukan kolom berkas.',
    },
    {
        modul: '3. Monitoring Fisik Meter Prabayar',
        kolom: ['NO', 'IDPEL', 'NO METER', 'TARIF', 'DAYA', 'KDRBM', 'KD KELOMPOK', 'TGLBACA', 'KDBACA', 'PETUGAS', 'KWH SISA', 'STATUS TEMPER', 'SEGEL', 'LCD', 'KEYPAD', 'RELAY', 'KOORDINAT X', 'KOORDINAT Y'],
        catatan: 'Total 34 kolom; kolom lain disimpan utuh.',
    },
];

export default function ModalFormatKolom({ open, onOpenChange }: { open: boolean; onOpenChange: (v: boolean) => void }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] max-w-[560px] overflow-y-auto">
                <DialogHeader>
                    <StatusChip label="PORTAL UNGGAH · VALIDASI ACMT" tone="success" />
                    <DialogTitle>Format Kolom Wajib per Modul</DialogTitle>
                    <DialogDescription>
                        Sheet dikenali dari judul di baris 5 dan header di baris 8, bukan dari nama sheet. Kolom wajib tidak boleh hilang atau berubah urutan.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2.5">
                    {FORMAT.map((f) => (
                        <section key={f.modul} className="rounded-10 border border-line bg-canvas p-3">
                            <p className="text-xs font-bold text-ink">{f.modul}</p>
                            <p className="mt-1.5 font-mono text-[10px] leading-[15px] text-slate-ink">{f.kolom.join(' | ')}</p>
                            <p className="mt-1.5 text-[10px] text-muted-ink">{f.catatan}</p>
                        </section>
                    ))}
                </div>
                <DialogFooter>
                    <Button variant="secondary" onClick={() => onOpenChange(false)}>
                        Tutup
                    </Button>
                    <Button variant="success" asChild>
                        <a href={route('unggah.template')}>Unduh Template XLSX</a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
