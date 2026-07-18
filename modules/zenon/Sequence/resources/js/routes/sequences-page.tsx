import type { ColumnDef } from '@tanstack/react-table';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
import { useUiStore } from '@zenon/core/store';
import {
    Badge,
    Button,
    DataTable,
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    Field,
    Input,
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@zenon/core/ui';
import { ApiErrorAlert } from '../components/api-error-alert';
import { useSequenceDefinitions, useSequences, useUpdateSequence } from '../api/sequences';
import type { ResetPeriod, SequenceDefinitionDto, SequenceDto } from '../api/types';

const RESET_PERIODS: ResetPeriod[] = ['never', 'year', 'month'];

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
            cell: ({ row }) => <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">{row.original.mask}</code>,
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
            cell: ({ row }) => <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">{row.original.preview}</code>,
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) =>
                canUpdate ? (
                    <div className="flex justify-end">
                        <Button size="sm" variant="outline" onClick={() => setEditing(row.original)}>
                            {t('shell:common.edit')}
                        </Button>
                    </div>
                ) : null,
        },
    ];

    const definitionColumns: ColumnDef<SequenceDefinitionDto>[] = [
        { accessorKey: 'code', header: t('columns.code') },
        {
            accessorKey: 'label',
            header: t('columns.label'),
            cell: ({ row }) => row.original.label ?? <span className="text-muted-foreground">—</span>,
        },
        {
            accessorKey: 'mask',
            header: t('columns.mask'),
            cell: ({ row }) => <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">{row.original.mask}</code>,
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
                <Badge variant={row.original.per_company ? 'success' : 'secondary'}>
                    {row.original.per_company ? t('common.yes') : t('common.no')}
                </Badge>
            ),
        },
        {
            accessorKey: 'gapless',
            header: t('columns.gapless'),
            cell: ({ row }) => (
                <Badge variant={row.original.gapless ? 'success' : 'secondary'}>{row.original.gapless ? t('common.yes') : t('common.no')}</Badge>
            ),
        },
        {
            accessorKey: 'materialized',
            header: t('columns.materialized'),
            cell: ({ row }) =>
                row.original.materialized ? (
                    <Badge variant="success">{t('definitions.materialized')}</Badge>
                ) : (
                    <Badge variant="secondary">{t('definitions.notMaterialized')}</Badge>
                ),
        },
    ];

    return (
        <div className="flex flex-col gap-8">
            <div className="flex flex-col gap-4">
                <h1 className="text-lg font-semibold">{t('sequences.title')}</h1>

                {sequencesQuery.isError && <ApiErrorAlert error={sequencesQuery.error} />}

                <DataTable
                    columns={columns}
                    data={sequencesQuery.data?.data ?? []}
                    meta={sequencesQuery.data?.meta}
                    onPageChange={setPage}
                    isLoading={sequencesQuery.isLoading}
                    emptyMessage={t('sequences.empty')}
                />
            </div>

            <div className="flex flex-col gap-4">
                <h2 className="text-base font-semibold">{t('definitions.title')}</h2>

                {definitionsQuery.isError && <ApiErrorAlert error={definitionsQuery.error} />}

                <DataTable
                    columns={definitionColumns}
                    data={definitionsQuery.data?.data ?? []}
                    isLoading={definitionsQuery.isLoading}
                    emptyMessage={t('definitions.empty')}
                />
            </div>

            {editing && <EditSequenceDialog sequence={editing} onClose={() => setEditing(null)} />}
        </div>
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
        <div className="flex flex-col gap-2">
            <span className="text-xs font-medium text-muted-foreground">{t('tokens.legend')}</span>
            <ul className="flex flex-col gap-1 rounded-md border border-border bg-muted/40 p-3 text-xs text-muted-foreground">
                {tokens.map(({ token, key }) => (
                    <li key={token}>
                        <code className="rounded bg-muted px-1 py-0.5 font-mono text-foreground">{token}</code> — {t(key)}
                    </li>
                ))}
            </ul>
        </div>
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
        <Dialog open onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('sequences.edit')} — {sequence.code}
                    </DialogTitle>
                </DialogHeader>
                <form className="flex flex-col gap-4" onSubmit={submit}>
                    <Field label={t('columns.mask')} htmlFor="sequence-mask" error={fieldErrors?.mask}>
                        <Input id="sequence-mask" value={mask} onValueChange={setMask} />
                    </Field>

                    <TokenLegend />

                    {nonFieldError && <ApiErrorAlert error={nonFieldError} />}

                    <Field label={t('columns.resetPeriod')}>
                        <Select
                            value={resetPeriod}
                            items={RESET_PERIODS.map((period) => ({ label: t(`resetPeriod.${period}`), value: period }))}
                            onValueChange={(next) => next !== null && setResetPeriod(next as ResetPeriod)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {RESET_PERIODS.map((period) => (
                                    <SelectItem key={period} value={period}>
                                        {t(`resetPeriod.${period}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose} disabled={update.isPending}>
                            {t('shell:common.cancel')}
                        </Button>
                        <Button type="submit" disabled={update.isPending}>
                            {t('shell:common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
