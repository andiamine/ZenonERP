import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Badge,
    Button,
    DataTable,
    Dialog,
    DialogContent,
    DialogDescription,
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
import { useActivity } from '../api/activity';
import { eventBadgeVariant, formatPropertyValue } from '../lib/format';
import type { ActivityDto } from '../api/types';

const ALL_EVENTS = 'all';
const EVENTS = ['created', 'updated', 'deleted'] as const;

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
            cell: ({ row }) => row.original.causer?.name ?? <span className="text-muted-foreground">{t('columns.system')}</span>,
        },
        {
            accessorKey: 'event',
            header: t('columns.event'),
            cell: ({ row }) =>
                row.original.event ? (
                    <Badge variant={eventBadgeVariant(row.original.event)}>{t(`events.${row.original.event}`, { defaultValue: row.original.event })}</Badge>
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
                <div className="flex justify-end">
                    <Button size="sm" variant="outline" onClick={() => setViewing(row.original)}>
                        {t('activity.view')}
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <div className="flex flex-col gap-6">
            <h1 className="text-lg font-semibold">{t('activity.title')}</h1>

            <div className="flex flex-wrap items-end gap-3">
                <Field label={t('filters.event')}>
                    <Select
                        value={event === '' ? ALL_EVENTS : event}
                        items={[{ label: t('filters.allEvents'), value: ALL_EVENTS }, ...EVENTS.map((value) => ({ label: t(`events.${value}`), value }))]}
                        onValueChange={(next) => {
                            setEvent(next === null || next === ALL_EVENTS ? '' : (next as string));
                            setPage(1);
                        }}
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_EVENTS}>{t('filters.allEvents')}</SelectItem>
                            {EVENTS.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {t(`events.${value}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field label={t('filters.subjectType')}>
                    <Input
                        className="w-40"
                        value={subjectType}
                        onValueChange={(next) => {
                            setSubjectType(next);
                            setPage(1);
                        }}
                        placeholder={t('filters.subjectTypePlaceholder')}
                    />
                </Field>

                <Field label={t('filters.causer')}>
                    <Input
                        className="w-32"
                        value={causerId}
                        onValueChange={(next) => {
                            setCauserId(next);
                            setPage(1);
                        }}
                        placeholder={t('filters.causerPlaceholder')}
                    />
                </Field>

                <Field label={t('filters.from')}>
                    <Input
                        type="date"
                        className="w-40"
                        value={from}
                        onValueChange={(next) => {
                            setFrom(next);
                            setPage(1);
                        }}
                    />
                </Field>

                <Field label={t('filters.to')}>
                    <Input
                        type="date"
                        className="w-40"
                        value={to}
                        onValueChange={(next) => {
                            setTo(next);
                            setPage(1);
                        }}
                    />
                </Field>
            </div>

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
        </div>
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
        <Dialog open onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{t('activity.propertiesTitle')}</DialogTitle>
                    <DialogDescription>
                        {activity.subject_type ?? '—'}
                        {activity.subject_id !== null ? `#${activity.subject_id}` : ''}
                    </DialogDescription>
                </DialogHeader>
                {keys.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('activity.noProperties')}</p>
                ) : (
                    <dl className="flex flex-col gap-3 text-sm">
                        {keys.map((key) => (
                            <div key={key} className="flex flex-col gap-1 border-b border-border pb-2 last:border-none">
                                <dt className="font-medium">{key}</dt>
                                <dd className="flex flex-col gap-0.5 text-muted-foreground">
                                    {isUpdate && (
                                        <span>
                                            {t('activity.old')}: <span className="text-foreground">{formatPropertyValue(oldValues[key])}</span>
                                        </span>
                                    )}
                                    <span>
                                        {isUpdate ? `${t('activity.new')}: ` : ''}
                                        <span className="text-foreground">{formatPropertyValue(newValues[key])}</span>
                                    </span>
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        {t('shell:common.cancel')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
