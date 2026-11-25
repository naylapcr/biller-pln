import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { formatNumber } from '@/lib/format';

interface DialogRollbackProps {
    batch: { nomor: string; modul_label: string; baris: number | null; file_name: string } | null;
    processing: boolean;
    onClose: () => void;
    onConfirm: () => void;
}

/** 04.4b Konfirmasi rollback, dipakai Portal Unggah dan Detail Batch. */
export default function DialogRollback({ batch, processing, onClose, onConfirm }: DialogRollbackProps) {
    return (
        <ConfirmDialog
            open={batch !== null}
            onOpenChange={(v) => !v && onClose()}
            chip={{ label: 'ROLLBACK BATCH · PERIODE TERBUKA', tone: 'waiting' }}
            garis="kuning"
            title={batch ? `Rollback ${batch.nomor} (${batch.modul_label})?` : ''}
            description={
                batch
                    ? `${formatNumber(batch.baris ?? 0)} baris dari batch ini dihapus dari tabel final; batch sebelumnya dipulihkan bila ada. Skor seluruh biller dihitung ulang otomatis.`
                    : ''
            }
            confirmLabel="Ya, Rollback & Hitung Ulang"
            tone="destructive"
            processing={processing}
            onConfirm={onConfirm}
        >
            <div className="rounded-10 bg-pln-yellow-row px-3 py-2.5 text-[11px] leading-[17px] text-[#92400e]">
                <p className="font-mono font-bold break-all">Berkas: {batch?.file_name}</p>
                <p>Aksi dicatat otomatis ke Audit Trail (ROLLBACK_BATCH).</p>
            </div>
        </ConfirmDialog>
    );
}
