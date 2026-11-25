import { LoaderCircle } from 'lucide-react';
import type { ReactNode } from 'react';

import StatusChip from '@/components/ui/StatusChip';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { StatusTone } from '@/lib/status';

const GARIS = { merah: 'border-error-line', kuning: 'border-pln-yellow-line', biru: 'border-[#93c5fd]' };

interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: ReactNode;
    confirmLabel: string;
    /** "destructive" hanya untuk hapus. */
    tone?: 'default' | 'destructive';
    /** Chip kecil di atas judul, contoh "ROLLBACK BATCH · PERIODE TERBUKA". */
    chip?: { label: string; tone: StatusTone };
    /** Warna garis tepi mengikuti sifat aksi. */
    garis?: 'merah' | 'kuning' | 'biru';
    processing?: boolean;
    onConfirm: () => void;
    children?: ReactNode;
}

export default function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    tone = 'default',
    chip,
    garis,
    processing,
    onConfirm,
    children,
}: ConfirmDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={garis && GARIS[garis]}>
                <DialogHeader>
                    {chip && <StatusChip {...chip} />}
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {children}
                <DialogFooter>
                    <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={processing}>
                        Batal
                    </Button>
                    <Button variant={tone === 'destructive' ? 'destructive' : 'default'} onClick={onConfirm} disabled={processing}>
                        {processing && <LoaderCircle className="animate-spin" />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
