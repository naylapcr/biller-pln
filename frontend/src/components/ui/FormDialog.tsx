import { LoaderCircle } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

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
import { cn } from '@/lib/utils';

interface FormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    chip?: { label: string; tone: StatusTone };
    /** Aksi permanen: garis dan tombol merah. */
    bahaya?: boolean;
    description?: ReactNode;
    submitLabel: string;
    processing?: boolean;
    /** Tombol simpan nonaktif (misalnya konfirmasi belum diketik) tanpa mengunci dialog. */
    submitDisabled?: boolean;
    onSubmit: () => void;
    children: ReactNode;
    className?: string;
}

/** Dialog berisi formulir: isi bisa digulir, tombol tetap di bawah. */
export default function FormDialog({
    open,
    onOpenChange,
    title,
    chip,
    bahaya,
    description,
    submitLabel,
    processing,
    submitDisabled,
    onSubmit,
    children,
    className,
}: FormDialogProps) {
    const submit = (e: FormEvent) => {
        e.preventDefault();
        onSubmit();
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !processing && onOpenChange(v)}>
            <DialogContent className={cn('max-h-[calc(100dvh-2rem)] gap-0 overflow-hidden p-0', bahaya && 'border-error-line', className)}>
                <form onSubmit={submit} noValidate className="flex max-h-[calc(100dvh-2rem)] flex-col">
                    <DialogHeader className="px-6 pt-6 pb-3.5">
                        {chip && <StatusChip {...chip} />}
                        <DialogTitle>{title}</DialogTitle>
                        {description && <DialogDescription>{description}</DialogDescription>}
                    </DialogHeader>
                    <div className="grid gap-3.5 overflow-y-auto px-6 pb-4">{children}</div>
                    <DialogFooter className="px-6 pt-1 pb-6">
                        <Button type="button" variant="secondary" onClick={() => onOpenChange(false)} disabled={processing}>
                            Batal
                        </Button>
                        <Button type="submit" variant={bahaya ? 'destructive' : 'default'} disabled={processing || submitDisabled}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
