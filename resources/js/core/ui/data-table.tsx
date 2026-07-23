import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
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
 * Generic data table on @tanstack/react-table 8.21 rendered with MUI Table primitives —
 * getCoreRowModel only, manual pagination + manual sorting (the server owns both via
 * spatie/laravel-query-builder). Deliberately NOT MUI X DataGrid (CLAUDE.md: avoids the
 * commercial license in the platform contract; TanStack is already a shared singleton).
 * Column defs own their header UI — a sortable column renders TableSortLabel in its own
 * header component.
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
        <Stack spacing={1.5} className={className}>
            <TableContainer component={Paper} variant="outlined">
                <Table size="small">
                    <TableHead>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableCell key={header.id} sx={{ fontWeight: 600, whiteSpace: 'nowrap' }}>
                                        {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableHead>
                    <TableBody>
                        {isLoading ? (
                            Array.from({ length: skeletonRowCount }).map((_, rowIndex) => (
                                <TableRow key={`skeleton-${rowIndex}`}>
                                    {Array.from({ length: columnCount }).map((__, colIndex) => (
                                        <TableCell key={colIndex}>
                                            <Skeleton variant="text" />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : table.getRowModel().rows.length > 0 ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id} hover>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columnCount} align="center" sx={{ py: 6, color: 'text.secondary' }}>
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </TableContainer>
            {meta && (
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2 }}>
                    <Typography variant="body2" color="text.secondary">
                        {meta.total > 0
                            ? `${(meta.current_page - 1) * meta.per_page + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total}`
                            : `0 of ${meta.total}`}
                    </Typography>
                    <Stack direction="row" spacing={1}>
                        <Button
                            variant="outlined"
                            color="inherit"
                            size="small"
                            disabled={isLoading || meta.current_page <= 1}
                            onClick={() => onPageChange?.(meta.current_page - 1)}
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outlined"
                            color="inherit"
                            size="small"
                            disabled={isLoading || meta.current_page >= meta.last_page}
                            onClick={() => onPageChange?.(meta.current_page + 1)}
                        >
                            Next
                        </Button>
                    </Stack>
                </Box>
            )}
        </Stack>
    );
}

export { DataTable };
