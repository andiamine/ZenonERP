import {
    flexRender,
    getCoreRowModel,
    useReactTable,
    type ColumnDef,
    type OnChangeFn,
    type SortingState,
} from '@tanstack/react-table';
import type { ReactNode } from 'react';
import type { PageMeta } from '../apiClient';
import { Button } from './button';
import { cn } from './cn';
import { Skeleton } from './skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from './table';

export interface DataTableProps<TData> {
    /** TanStack Table column defs. The value generic is deliberately `any` (TanStack's own
     * convention — https://tanstack.com/table/latest/docs/guide/column-defs): columns for a
     * single row type routinely carry heterogeneous cell value types, and `unknown` breaks
     * consumer assignability here. */
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    columns: ColumnDef<TData, any>[];
    data: TData[];
    /** Omit for tables with no server-driven pagination (e.g. always-short lists). */
    meta?: PageMeta;
    onPageChange?: (page: number) => void;
    sorting?: SortingState;
    onSortingChange?: OnChangeFn<SortingState>;
    isLoading?: boolean;
    emptyMessage?: ReactNode;
    className?: string;
}

/**
 * Generic data table on @tanstack/react-table 8.21 — getCoreRowModel only, manual
 * pagination + manual sorting (the server owns both via spatie/laravel-query-builder).
 * Composed from the `table.tsx` primitives rather than ReUI's data-grid: data-grid pulls in
 * dnd-kit + react-virtual for drag/virtualization we don't need here (CLAUDE.md task brief).
 */
function DataTable<TData>({
    columns,
    data,
    meta,
    onPageChange,
    sorting,
    onSortingChange,
    isLoading = false,
    emptyMessage = 'No data available',
    className,
}: DataTableProps<TData>) {
    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        manualSorting: true,
        onSortingChange,
        state: sorting ? { sorting } : undefined,
    });

    const skeletonRowCount = meta?.per_page ?? 10;
    const columnCount = columns.length || 1;

    return (
        <div data-slot="data-table" className={cn('flex flex-col gap-3', className)}>
            <div className="overflow-x-auto rounded-md border border-border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            Array.from({ length: skeletonRowCount }).map((_, rowIndex) => (
                                <TableRow key={`skeleton-${rowIndex}`}>
                                    {Array.from({ length: columnCount }).map((__, colIndex) => (
                                        <TableCell key={colIndex}>
                                            <Skeleton className="h-4 w-full" />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : table.getRowModel().rows.length > 0 ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columnCount} className="h-24 text-center whitespace-normal text-muted-foreground">
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            {meta && (
                <div data-slot="data-table-pagination" className="flex items-center justify-between gap-4 text-sm text-muted-foreground">
                    <span>
                        {meta.total > 0
                            ? `${(meta.current_page - 1) * meta.per_page + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total}`
                            : `0 of ${meta.total}`}
                    </span>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={isLoading || meta.current_page <= 1}
                            onClick={() => onPageChange?.(meta.current_page - 1)}
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={isLoading || meta.current_page >= meta.last_page}
                            onClick={() => onPageChange?.(meta.current_page + 1)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

export { DataTable };
