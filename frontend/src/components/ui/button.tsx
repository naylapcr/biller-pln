import { cva, type VariantProps } from 'class-variance-authority';
import { Slot } from 'radix-ui';
import * as React from 'react';

import { cn } from '@/lib/utils';

/** Tombol sesuai Figma: biru (utama), hijau (simpan/kirim), putih (sekunder), merah muda (rollback), merah (permanen). */
const buttonVariants = cva(
    "inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-9 text-xs font-bold transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                default: 'bg-pln-blue text-white shadow-tombol hover:bg-pln-blue-dark',
                success: 'bg-success text-white shadow-[0_3px_8px_0_rgb(5_150_105/0.18)] hover:bg-success-dark',
                secondary: 'border border-line-strong bg-white font-semibold text-ink-soft hover:bg-table-head',
                reject: 'border border-error-line bg-error-tint text-error-ink hover:bg-error-row',
                destructive: 'bg-pln-red text-white hover:bg-error-ink',
                ghost: 'text-ink-soft hover:bg-line-row',
                link: 'h-auto px-0 text-pln-blue underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-9 px-3.5',
                sm: 'h-8 px-3.5',
                lg: 'h-12 px-5 text-sm',
                icon: 'size-9',
                'icon-sm': 'size-8',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

function Button({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: React.ComponentProps<'button'> & VariantProps<typeof buttonVariants> & { asChild?: boolean }) {
    const Comp = asChild ? Slot.Root : 'button';

    return <Comp data-slot="button" className={cn(buttonVariants({ variant, size, className }))} {...props} />;
}

export { Button, buttonVariants };
