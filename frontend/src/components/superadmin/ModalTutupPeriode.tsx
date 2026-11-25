import { useForm } from '@inertiajs/react';

import FormDialog from '@/components/ui/FormDialog';
import FormField from '@/components/ui/FormField';
import { Input } from '@/components/ui/input';
import { bisaDitutup, DaftarSyarat, type SyaratTutup } from './KartuKesiapanTutup';

/**
 * 04.4a Modal Tutup Periode: checklist prasyarat dan isian ketik kode periode (contoh "202611").
 * Setelah ditutup, skor final dihitung dan unggah, rollback, serta parameter dikunci.
 */
export default function ModalTutupPeriode({ open, onOpenChange, kode, syarat }: { open: boolean; onOpenChange: (v: boolean) => void; kode: string; syarat: SyaratTutup[] }) {
    const form = useForm({ konfirmasi_kode: '' });

    return (
        <FormDialog
            open={open}
            onOpenChange={(v) => {
                if (!v) form.reset();
                onOpenChange(v);
            }}
            chip={{ label: 'AKSI PERMANEN · SUPER ADMIN', tone: 'danger' }}
            bahaya
            title={`Konfirmasi Tutup Periode ${kode}?`}
            description="Skor final seluruh biller dikunci (is_final = true). Unggah berkas, rollback batch, dan ubah parameter dinonaktifkan permanen."
            submitLabel="Ya, Tutup & Kunci Final"
            processing={form.processing}
            submitDisabled={!bisaDitutup(syarat) || form.data.konfirmasi_kode !== kode}
            onSubmit={() => form.post(route('pengaturan.periode.close', kode), { preserveScroll: true, onSuccess: () => onOpenChange(false) })}
        >
            <DaftarSyarat syarat={syarat} />
            <FormField id="konfirmasi_kode" label={`Ketik kode periode "${kode}" untuk konfirmasi`} error={form.errors.konfirmasi_kode ?? (form.errors as Record<string, string>).periode}>
                <Input
                    id="konfirmasi_kode"
                    className="font-mono"
                    inputMode="numeric"
                    autoComplete="off"
                    value={form.data.konfirmasi_kode}
                    onChange={(e) => form.setData('konfirmasi_kode', e.target.value.trim())}
                    placeholder={kode}
                />
            </FormField>
        </FormDialog>
    );
}
