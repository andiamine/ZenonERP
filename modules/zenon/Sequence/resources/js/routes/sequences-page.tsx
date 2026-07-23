import {
    Box,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
    OutlinedInput,
    Select,
    Stack,
    Typography,
} from '@mui/material';
import type { SxProps, Theme } from '@mui/material';
import type { ColumnDef } from '@tanstack/react-table';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
import { useUiStore } from '@zenon/core/store';
import { ApiErrorAlert, DataTable, Field } from '@zenon/core/ui';
import { useSequenceDefinitions, useSequences, useUpdateSequence } from '../api/sequences';
import type { ResetPeriod, SequenceDefinitionDto, SequenceDto } from '../api/types';

const RESET_PERIODS: ResetPeriod[] = ['never', 'year', 'month'];

/** Inline `<code>` chip for masks/previews/tokens (the old `bg-muted` code style). */
const codeSx: SxProps<Theme> = {
    px: 0.5,
    py: 0.25,
    borderRadius: 0.5,
    bgcolor: 'action.hover',
    fontFamily: 'monospace',
    fontSize: '0.75rem',
};

/**
 * Sequence administration (CLAUDE.md §9.2): materialized counters (rows drawn at least once)
 * plus a definitions table for codes a consumer module registered but has never drawn from
 * (the honest counterpart — SequencesController::definitions()' doc comment). Edits are
 * mask/reset_period only; `next_number` is never hand-edited (gapless allocation invariant).
 */
export function SequencesPage() {
    const { t } = useTranslation('sequence');
    const boot = useBoot();
    const companyId = useUiStore((state) => state.currentCompanyId) ?? boot.current_company_id;
    const [page, setPage] = useState(1);

    const sequencesQuery = useSequences(companyId, { page });
    const definitionsQuery = useSequenceDefinitions();

    const [editing, setEditing] = useState<SequenceDto | null>(null);
    const canUpdate = hasPermission(boot, 'sequence.sequences.update');

    function companyLabel(id: number | null): string {
        if (id === null) {
            return t('shared');
        }
        return boot.companies.find((company) => company.id === id)?.name ?? String(id);
    }

    const columns: ColumnDef<SequenceDto>[] = [
        { accessorKey: 'code', header: t('columns.code') },
        { id: 'company', header: t('columns.company'), cell: ({ row }) => companyLabel(row.original.company_id) },
        {
            accessorKey: 'mask',
            header: t('columns.mask'),
            cell: ({ row }) => (
                <Box component="code" sx={codeSx}>
                    {row.original.mask}
                </Box>
            ),
        },
        { accessorKey: 'next_number', header: t('columns.nextNumber') },
        {
            accessorKey: 'reset_period',
            header: t('columns.resetPeriod'),
            cell: ({ row }) => t(`resetPeriod.${row.original.reset_period}`),
        },
        {
            accessorKey: 'preview',
            header: t('columns.preview'),
            cell: ({ row }) => (
                <Box component="code" sx={codeSx}>
                    {row.original.preview}
                </Box>
            ),
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) =>
                canUpdate ? (
                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button size="small" variant="outlined" color="inherit" onClick={() => setEditing(row.original)}>
                            {t('shell:common.edit')}
                        </Button>
                    </Box>
                ) : null,
        },
    ];

    const definitionColumns: ColumnDef<SequenceDefinitionDto>[] = [
        { accessorKey: 'code', header: t('columns.code') },
        {
            accessorKey: 'label',
            header: t('columns.label'),
            cell: ({ row }) =>
                row.original.label ?? (
                    <Box component="span" sx={{ color: 'text.secondary' }}>
                        —
                    </Box>
                ),
        },
        {
            accessorKey: 'mask',
            header: t('columns.mask'),
            cell: ({ row }) => (
                <Box component="code" sx={codeSx}>
                    {row.original.mask}
                </Box>
            ),
        },
        {
            accessorKey: 'reset_period',
            header: t('columns.resetPeriod'),
            cell: ({ row }) => t(`resetPeriod.${row.original.reset_period}`),
        },
        {
            accessorKey: 'per_company',
            header: t('columns.perCompany'),
            cell: ({ row }) => (
                <Chip
                    size="small"
                    color={row.original.per_company ? 'success' : 'default'}
                    label={row.original.per_company ? t('common.yes') : t('common.no')}
                />
            ),
        },
        {
            accessorKey: 'gapless',
            header: t('columns.gapless'),
            cell: ({ row }) => (
                <Chip size="small" color={row.original.gapless ? 'success' : 'default'} label={row.original.gapless ? t('common.yes') : t('common.no')} />
            ),
        },
        {
            accessorKey: 'materialized',
            header: t('columns.materialized'),
            cell: ({ row }) =>
                row.original.materialized ? (
                    <Chip size="small" color="success" label={t('definitions.materialized')} />
                ) : (
                    <Chip size="small" color="default" label={t('definitions.notMaterialized')} />
                ),
        },
    ];

    return (
        <Stack spacing={4}>
            <Stack spacing={2}>
                <Typography variant="h5" component="h1" sx={{ fontWeight: 600 }}>
                    {t('sequences.title')}
                </Typography>

                {sequencesQuery.isError && <ApiErrorAlert error={sequencesQuery.error} />}

                <DataTable
                    columns={columns}
                    data={sequencesQuery.data?.data ?? []}
                    meta={sequencesQuery.data?.meta}
                    onPageChange={setPage}
                    isLoading={sequencesQuery.isLoading}
                    emptyMessage={t('sequences.empty')}
                />
            </Stack>

            <Stack spacing={2}>
                <Typography variant="h6" component="h2">
                    {t('definitions.title')}
                </Typography>

                {definitionsQuery.isError && <ApiErrorAlert error={definitionsQuery.error} />}

                <DataTable
                    columns={definitionColumns}
                    data={definitionsQuery.data?.data ?? []}
                    isLoading={definitionsQuery.isLoading}
                    emptyMessage={t('definitions.empty')}
                />
            </Stack>

            {editing && <EditSequenceDialog sequence={editing} onClose={() => setEditing(null)} />}
        </Stack>
    );
}

function TokenLegend() {
    const { t } = useTranslation('sequence');
    const tokens = [
        { token: '{seq}', key: 'tokens.seq' },
        { token: '{seq:5}', key: 'tokens.seqPadded' },
        { token: '{year}', key: 'tokens.year' },
        { token: '{year2}', key: 'tokens.year2' },
        { token: '{month}', key: 'tokens.month' },
        { token: '{company}', key: 'tokens.company' },
    ] as const;

    return (
        <Stack spacing={1}>
            <Typography variant="caption" sx={{ fontWeight: 500 }} color="text.secondary">
                {t('tokens.legend')}
            </Typography>
            <Box
                component="ul"
                sx={{
                    m: 0,
                    p: 1.5,
                    listStyle: 'none',
                    display: 'grid',
                    gap: 0.5,
                    borderRadius: 1,
                    border: 1,
                    borderColor: 'divider',
                    bgcolor: 'action.hover',
                    typography: 'caption',
                    color: 'text.secondary',
                }}
            >
                {tokens.map(({ token, key }) => (
                    <li key={token}>
                        <Box component="code" sx={{ ...codeSx, bgcolor: 'action.selected', color: 'text.primary' }}>
                            {token}
                        </Box>{' '}
                        — {t(key)}
                    </li>
                ))}
            </Box>
        </Stack>
    );
}

/**
 * Mounted fresh per row selection (`{editing && <EditSequenceDialog .../>}` in the parent, the
 * same conditional-mount pattern as Core's TeamMembersDialog) rather than kept alive behind an
 * `open` boolean — so the initial `useState` values are always the freshly-selected row's; no
 * re-seed effect is needed.
 */
function EditSequenceDialog({ sequence, onClose }: { sequence: SequenceDto; onClose: () => void }) {
    const { t } = useTranslation('sequence');
    const update = useUpdateSequence();
    const [mask, setMask] = useState(sequence.mask);
    const [resetPeriod, setResetPeriod] = useState<ResetPeriod>(sequence.reset_period);

    const fieldErrors = update.error instanceof ApiError ? update.error.errors : undefined;
    const nonFieldError = update.error instanceof ApiError && update.error.type !== 'validation_error' ? update.error : null;

    function submit(event: FormEvent) {
        event.preventDefault();
        update.mutate({ id: sequence.id, mask, reset_period: resetPeriod }, { onSuccess: onClose });
    }

    return (
        <Dialog open onClose={onClose} maxWidth="sm" fullWidth slotProps={{ paper: { component: 'form', onSubmit: submit } }}>
            <DialogTitle>
                {t('sequences.edit')} — {sequence.code}
            </DialogTitle>
            <DialogContent>
                <Stack spacing={2.5} sx={{ pt: 0.5 }}>
                    <Field label={t('columns.mask')} htmlFor="sequence-mask" error={fieldErrors?.mask}>
                        <OutlinedInput id="sequence-mask" value={mask} onChange={(event) => setMask(event.target.value)} />
                    </Field>

                    <TokenLegend />

                    {nonFieldError && <ApiErrorAlert error={nonFieldError} />}

                    <Field label={t('columns.resetPeriod')}>
                        <Select
                            value={resetPeriod}
                            onChange={(event) => setResetPeriod(event.target.value as ResetPeriod)}
                        >
                            {RESET_PERIODS.map((period) => (
                                <MenuItem key={period} value={period}>
                                    {t(`resetPeriod.${period}`)}
                                </MenuItem>
                            ))}
                        </Select>
                    </Field>
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button color="inherit" onClick={onClose} disabled={update.isPending}>
                    {t('shell:common.cancel')}
                </Button>
                <Button type="submit" variant="contained" disabled={update.isPending}>
                    {t('shell:common.save')}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
