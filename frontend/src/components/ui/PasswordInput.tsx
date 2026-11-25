import { useState, type ComponentProps } from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** Input kata sandi dengan tombol Tampilkan/Sembunyikan. */
export default function PasswordInput({ className, ...props }: Omit<ComponentProps<'input'>, 'type'>) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <Input type={visible ? 'text' : 'password'} className={cn('pr-24', className)} {...props} />
            <button
                type="button"
                onClick={() => setVisible((v) => !v)}
                aria-label={visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                aria-pressed={visible}
                className="absolute inset-y-0 right-0 inline-flex items-center rounded-r-9 px-3 text-[11px] font-semibold text-sky-val hover:text-sky-ink focus-visible:ring-[3px] focus-visible:ring-pln-blue/25 focus-visible:outline-none"
            >
                {visible ? 'Sembunyikan' : 'Tampilkan'}
            </button>
        </div>
    );
}
