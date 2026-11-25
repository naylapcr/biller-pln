import * as React from 'react';

import { cn } from '@/lib/utils';

function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                'flex min-h-[52px] w-full rounded-9 border border-line-strong bg-canvas px-3.5 py-3 text-xs font-medium text-ink outline-none transition-[color,box-shadow]',
                'placeholder:text-muted-ink/70 focus-visible:border-pln-blue focus-visible:ring-[3px] focus-visible:ring-pln-blue/20',
                'disabled:cursor-not-allowed disabled:bg-table-head aria-invalid:border-pln-red',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };
