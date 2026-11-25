import { router } from '@inertiajs/react';
import { flexRender, getCoreRowModel, useReactTable, type ColumnDef } from '@tanstack/react-table';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export type { ColumnDef };

declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData, TValue> {
        /** Kelas untuk <th> dan <td>, misal lebar atau rata kanan. */
        className?: string;
    }
}

interface DataTableProps<T> {
    columns: ColumnDef<T, any>[];
    /** Data paginasi server (paginate()) atau array biasa. */
    data: Paginated<T> | T[];
    getRowId?: (row: T) => string;
    rowClassName?: (row: T) => string | undefined;
    /** Klik baris (misal membuka drawer rincian). */
    onRowClick?: (row: T) => void;
    empty?: ReactNode;
    className?: string;
    /** Lebar minimum tabel sebelum digulir mendatar. */
    minLebar?: number;
}

/**
 * Tabel data: border radius 12, header abu 10 px kapital, baris 46 px,
 * bisa digulir mendatar di HP, paginasi "Menampilkan x sampai y dari z".
 */
export default function DataTable<T>({ columns, data, getRowId, rowClassName, onRowClick, empty, className, minLebar = 720 }: DataTableProps<T>) {
    const rows = Array.isArray(data) ? data : data.data;
    const paginated = Array.isArray(data) ? null : data;

    const table = useReactTable({
        data: rows,
        columns,
        getRowId,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
    });

    return (
        <div className={cn('overflow-hidden rounded-xl border border-line bg-white', className)}>
            <div className="relative overflow-x-auto">
                <table style={{ minWidth: minLebar }} className="w-full border-collapse text-xs font-medium text-ink-soft">
                    <thead className="bg-table-head">
                        {table.getHeaderGroups().map((hg) => (
                            <tr key={hg.id}>
                                {hg.headers.map((header) => (
                                    <th
                                        key={header.id}
                                        scope="col"
                                        className={cn(
                                            'h-[38px] border-b border-line px-3.5 text-left align-middle text-[10px] font-bold whitespace-nowrap text-muted-ink',
                                            header.column.columnDef.meta?.className,
                                        )}
                                    >
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(header.column.columnDef.header, header.getContext())}
                                    </th>
                                ))}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {table.getRowModel().rows.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length} className="px-4 py-10 text-center text-xs text-muted-ink">
                                    {empty ?? 'Belum ada data.'}
                                </td>
                            </tr>
                        ) : (
                            table.getRowModel().rows.map((row) => (
                                <tr
                                    key={row.id}
                                    onClick={onRowClick ? () => onRowClick(row.original) : undefined}
                                    className={cn(
                                        'border-b border-line-row transition-colors last:border-0 hover:bg-canvas',
                                        rowClassName?.(row.original),
                                    )}
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <td
                                            key={cell.id}
                                            className={cn(
                                                'h-[46px] px-3.5 py-2 align-middle',
                                                cell.column.columnDef.meta?.className,
                                            )}
                                        >
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
            {paginated && paginated.total > 0 && <Pagination page={paginated} />}
        </div>
    );
}

/** Baris "Menampilkan x sampai y dari z" dengan tombol halaman, juga dipakai daftar selain tabel. */
export function Pagination<T>({ page }: { page: Paginated<T> }) {
    const go = (url: string | null) =>
        url && router.get(url, {}, { preserveState: true, preserveScroll: false });

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line px-3.5 py-2.5">
            <p className="text-[11px] text-muted-ink">
                Menampilkan {formatNumber(page.from ?? 0)} sampai {formatNumber(page.to ?? 0)} dari{' '}
                {formatNumber(page.total)}
            </p>
            {page.last_page > 1 && (
                <div className="flex gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        disabled={!page.prev_page_url}
                        onClick={() => go(page.prev_page_url)}
                    >
                        <ChevronLeft />
                        Sebelumnya
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        disabled={!page.next_page_url}
                        onClick={() => go(page.next_page_url)}
                    >
                        Berikutnya
                        <ChevronRight />
                    </Button>
                </div>
            )}
        </div>
    );
}
