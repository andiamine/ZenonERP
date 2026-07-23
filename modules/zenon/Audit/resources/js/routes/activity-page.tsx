import {
    Box,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogContentText,
    DialogTitle,
    MenuItem,
    OutlinedInput,
    Select,
    Stack,
    Typography,
} from '@mui/material';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiErrorAlert, DataTable, Field } from '@zenon/core/ui';
import { useActivity } from '../api/activity';
import { eventBadgeVariant, formatPropertyValue, type EventBadgeVariant } from '../lib/format';
import type { ActivityDto } from '../api/types';

const ALL_EVENTS = 'all';
const EVENTS = ['created', 'updated', 'deleted'] as const;

/** Old Badge-variant vocabulary (lib/format.ts) → MUI Chip color. */
const CHIP_COLOR: Record<EventBadgeVariant, 'success' | 'info' | 'error' | 'default'> = {
    success: 'success',
    info: 'info',
    destructive: 'error',
    secondary: 'default',
};

/**
 * Activity log viewer (CLAUDE.md §9.2). Filters are LOCAL component state, not URL search
 * params — under the dynamically-assembled module route tree, search-param typing is erased
 * (design doc §Step 6); `zenon/views` owns saved, shareable list views with real URL state
 * later.
 */
export function ActivityPage() {
    const { t } = useTranslation('audit');

    const [event, setEvent] = useState('');
    const [subjectType, setSubjectType] = useState('');
    const [causerId, setCauserId] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [page, setPage] = useState(1);
    const [viewing, setViewing] = useState<ActivityDto | null>(null);

    const query = useActivity({
        event: event || undefined,
        subject_type: subjectType || undefined,
        causer_id: causerId ? Number(causerId) : undefined,
        from: from || undefined,
        to: to || undefined,
        page,
    });

    const columns: ColumnDef<ActivityDto>[] = [
        {
            accessorKey: 'created_at',
            header: t('columns.createdAt'),
            cell: ({ row }) => new Date(row.original.created_at).toLocaleString(),
        },
        {
            id: 'causer',
            header: t('columns.causer'),
            cell: ({ row }) =>
                row.original.causer?.name ?? (
                    <Box component="span" sx={{ color: 'text.secondary' }}>
                        {t('columns.system')}
                    </Box>
                ),
        },
        {
            accessorKey: 'event',
            header: t('columns.event'),
            cell: ({ row }) =>
                row.original.event ? (
                    <Chip
                        size="small"
                        color={CHIP_COLOR[eventBadgeVariant(row.original.event)]}
                        label={t(`events.${row.original.event}`, { defaultValue: row.original.event })}
                    />
                ) : null,
        },
        {
            id: 'subject',
            header: t('columns.subject'),
            cell: ({ row }) => `${row.original.subject_type ?? '—'}${row.original.subject_id !== null ? `#${row.original.subject_id}` : ''}`,
        },
        { accessorKey: 'description', header: t('columns.description') },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => (
                <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                    <Button size="small" variant="outlined" color="inherit" onClick={() => setViewing(row.original)}>
                        {t('activity.view')}
                    </Button>
                </Box>
            ),
        },
    ];

    return (
        <Stack spacing={3}>
            <Typography variant="h5" component="h1" sx={{ fontWeight: 600 }}>
                {t('activity.title')}
            </Typography>

            <Stack direction="row" spacing={2} sx={{ flexWrap: 'wrap', alignItems: 'flex-end', rowGap: 2 }}>
                <Field label={t('filters.event')} sx={{ width: 160 }}>
                    <Select
                        value={event === '' ? ALL_EVENTS : event}
                        onChange={(next) => {
                            const value = next.target.value;
                            setEvent(value === ALL_EVENTS ? '' : value);
                            setPage(1);
                        }}
                    >
                        <MenuItem value={ALL_EVENTS}>{t('filters.allEvents')}</MenuItem>
                        {EVENTS.map((value) => (
                            <MenuItem key={value} value={value}>
                                {t(`events.${value}`)}
                            </MenuItem>
                        ))}
                    </Select>
                </Field>

                <Field label={t('filters.subjectType')} sx={{ width: 160 }}>
                    <OutlinedInput
                        value={subjectType}
                        onChange={(next) => {
                            setSubjectType(next.target.value);
                            setPage(1);
                        }}
                        placeholder={t('filters.subjectTypePlaceholder')}
                    />
                </Field>

                <Field label={t('filters.causer')} sx={{ width: 128 }}>
                    <OutlinedInput
                        value={causerId}
                        onChange={(next) => {
                            setCauserId(next.target.value);
                            setPage(1);
                        }}
                        placeholder={t('filters.causerPlaceholder')}
                    />
                </Field>

                <Field label={t('filters.from')} sx={{ width: 160 }}>
                    <OutlinedInput
                        type="date"
                        value={from}
                        onChange={(next) => {
                            setFrom(next.target.value);
                            setPage(1);
                        }}
                    />
                </Field>

                <Field label={t('filters.to')} sx={{ width: 160 }}>
                    <OutlinedInput
                        type="date"
                        value={to}
                        onChange={(next) => {
                            setTo(next.target.value);
                            setPage(1);
                        }}
                    />
                </Field>
            </Stack>

            {query.isError && <ApiErrorAlert error={query.error} />}

            <DataTable
                columns={columns}
                data={query.data?.data ?? []}
                meta={query.data?.meta}
                onPageChange={setPage}
                isLoading={query.isLoading}
                emptyMessage={t('activity.empty')}
            />

            {viewing && <PropertiesDialog activity={viewing} onClose={() => setViewing(null)} />}
        </Stack>
    );
}

function PropertiesDialog({ activity, onClose }: { activity: ActivityDto; onClose: () => void }) {
    const { t } = useTranslation('audit');
    // Coerced to always-defined objects (`?? {}`) so property access below needs no
    // undefined-narrowing through the `isUpdate` boolean, including inside the `.map()` closure.
    const isUpdate = activity.properties.old !== undefined;
    const oldValues: Record<string, unknown> = activity.properties.old ?? {};
    const newValues: Record<string, unknown> = activity.properties.attributes ?? {};
    const keys = Object.keys(isUpdate ? oldValues : newValues);

    return (
        <Dialog open onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>{t('activity.propertiesTitle')}</DialogTitle>
            <DialogContent>
                <DialogContentText sx={{ mb: 2 }}>
                    {activity.subject_type ?? '—'}
                    {activity.subject_id !== null ? `#${activity.subject_id}` : ''}
                </DialogContentText>
                {keys.length === 0 ? (
                    <Typography variant="body2" color="text.secondary">
                        {t('activity.noProperties')}
                    </Typography>
                ) : (
                    <Stack component="dl" spacing={1.5} sx={{ m: 0 }}>
                        {keys.map((key) => (
                            <Box key={key} sx={{ pb: 1.5, borderBottom: 1, borderColor: 'divider', '&:last-child': { borderBottom: 0, pb: 0 } }}>
                                <Typography component="dt" variant="body2" sx={{ fontWeight: 500 }}>
                                    {key}
                                </Typography>
                                <Typography component="dd" variant="body2" color="text.secondary" sx={{ m: 0, display: 'grid', gap: 0.25 }}>
                                    {isUpdate && (
                                        <span>
                                            {t('activity.old')}:{' '}
                                            <Box component="span" sx={{ color: 'text.primary' }}>
                                                {formatPropertyValue(oldValues[key])}
                                            </Box>
                                        </span>
                                    )}
                                    <span>
                                        {isUpdate ? `${t('activity.new')}: ` : ''}
                                        <Box component="span" sx={{ color: 'text.primary' }}>
                                            {formatPropertyValue(newValues[key])}
                                        </Box>
                                    </span>
                                </Typography>
                            </Box>
                        ))}
                    </Stack>
                )}
            </DialogContent>
            <DialogActions>
                <Button color="inherit" onClick={onClose}>
                    {t('shell:common.cancel')}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
