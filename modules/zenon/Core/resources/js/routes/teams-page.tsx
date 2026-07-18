import type { ColumnDef } from '@tanstack/react-table';
import { type FormEvent, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
import {
    Badge,
    Button,
    Checkbox,
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
    Switch,
} from '@zenon/core/ui';
import { ApiErrorAlert } from '../components/api-error-alert';
import { ConfirmDialog } from '../components/confirm-dialog';
import { useCreateTeam, useDeleteTeam, useSyncTeamMembers, useTeams, useUpdateTeam } from '../api/teams';
import { useUsers } from '../api/users';
import type { TeamDto } from '../api/types';

const SHARED = 'shared';

export function TeamsPage() {
    const { t } = useTranslation('core');
    const boot = useBoot();
    const [page, setPage] = useState(1);
    const query = useTeams({ page });
    const deleteTeam = useDeleteTeam();

    const [editing, setEditing] = useState<TeamDto | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [members, setMembers] = useState<TeamDto | null>(null);
    const [deleting, setDeleting] = useState<TeamDto | null>(null);

    const canCreate = hasPermission(boot, 'core.teams.create');
    const canUpdate = hasPermission(boot, 'core.teams.update');
    const canDelete = hasPermission(boot, 'core.teams.delete');

    function companyName(companyId: number | null) {
        if (companyId === null) {
            return t('teams.shared');
        }
        return boot.companies.find((company) => company.id === companyId)?.name ?? String(companyId);
    }

    const columns: ColumnDef<TeamDto>[] = [
        { accessorKey: 'name', header: t('columns.name') },
        {
            accessorKey: 'description',
            header: t('columns.description'),
            cell: ({ row }) => row.original.description ?? <span className="text-muted-foreground">—</span>,
        },
        { id: 'company', header: t('columns.company'), cell: ({ row }) => companyName(row.original.company_id) },
        {
            accessorKey: 'active',
            header: t('columns.active'),
            cell: ({ row }) =>
                row.original.active ? (
                    <Badge variant="success">{t('status.active')}</Badge>
                ) : (
                    <Badge variant="secondary">{t('status.inactive')}</Badge>
                ),
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => (
                <div className="flex justify-end gap-2">
                    {canUpdate && (
                        <Button size="sm" variant="outline" onClick={() => setMembers(row.original)}>
                            {t('teams.members')}
                        </Button>
                    )}
                    {canUpdate && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                setEditing(row.original);
                                setDialogOpen(true);
                            }}
                        >
                            {t('shell:common.edit')}
                        </Button>
                    )}
                    {canDelete && (
                        <Button size="sm" variant="ghost" onClick={() => setDeleting(row.original)}>
                            {t('shell:common.delete')}
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <div className="flex flex-col gap-6">
            <div className="flex items-center justify-between gap-4">
                <h1 className="text-lg font-semibold">{t('teams.title')}</h1>
                {canCreate && (
                    <Button
                        onClick={() => {
                            setEditing(null);
                            setDialogOpen(true);
                        }}
                    >
                        {t('teams.create')}
                    </Button>
                )}
            </div>

            {query.isError && <ApiErrorAlert error={query.error} />}

            <DataTable
                columns={columns}
                data={query.data?.data ?? []}
                meta={query.data?.meta}
                onPageChange={setPage}
                isLoading={query.isLoading}
                emptyMessage={t('teams.empty')}
            />

            {(canCreate || canUpdate) && <TeamDialog open={dialogOpen} onOpenChange={setDialogOpen} team={editing} />}
            {members && <TeamMembersDialog team={members} onClose={() => setMembers(null)} />}

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        deleteTeam.reset();
                        setDeleting(null);
                    }
                }}
                title={t('teams.delete')}
                description={t('teams.deleteConfirm', { name: deleting?.name ?? '' })}
                isPending={deleteTeam.isPending}
                error={deleteTeam.error}
                onConfirm={() => {
                    if (deleting) {
                        deleteTeam.mutate(deleting.id, { onSuccess: () => setDeleting(null) });
                    }
                }}
            />
        </div>
    );
}

function TeamDialog({ open, onOpenChange, team }: { open: boolean; onOpenChange: (open: boolean) => void; team: TeamDto | null }) {
    const { t } = useTranslation('core');
    const boot = useBoot();
    const createTeam = useCreateTeam();
    const updateTeam = useUpdateTeam();

    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [companyId, setCompanyId] = useState<number | null>(null);
    const [active, setActive] = useState(true);

    useEffect(() => {
        if (open) {
            setName(team?.name ?? '');
            setDescription(team?.description ?? '');
            setCompanyId(team?.company_id ?? null);
            setActive(team?.active ?? true);
        }
    }, [open, team]);

    const mutation = team ? updateTeam : createTeam;
    const errors = mutation.error instanceof ApiError ? mutation.error.errors : undefined;

    function submit(event: FormEvent) {
        event.preventDefault();
        const input = { name, description: description || null, company_id: companyId, active };
        if (team) {
            updateTeam.mutate({ id: team.id, ...input }, { onSuccess: () => onOpenChange(false) });
        } else {
            createTeam.mutate(input, { onSuccess: () => onOpenChange(false) });
        }
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    mutation.reset();
                }
                onOpenChange(next);
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{team ? t('teams.edit') : t('teams.create')}</DialogTitle>
                </DialogHeader>
                <form className="flex flex-col gap-4" onSubmit={submit}>
                    <Field label={t('columns.name')} htmlFor="team-name" error={errors?.name}>
                        <Input id="team-name" value={name} onValueChange={setName} />
                    </Field>
                    <Field label={t('columns.description')} htmlFor="team-description" error={errors?.description}>
                        <Input id="team-description" value={description} onValueChange={setDescription} />
                    </Field>
                    <Field label={t('columns.company')} error={errors?.company_id}>
                        <Select
                            value={companyId === null ? SHARED : String(companyId)}
                            items={[
                                { label: t('teams.shared'), value: SHARED },
                                ...boot.companies.map((company) => ({ label: company.name, value: String(company.id) })),
                            ]}
                            onValueChange={(next) => setCompanyId(next === SHARED || next === null ? null : Number(next))}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={SHARED}>{t('teams.shared')}</SelectItem>
                                {boot.companies.map((company) => (
                                    <SelectItem key={company.id} value={String(company.id)}>
                                        {company.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label={t('columns.active')}>
                        <Switch checked={active} onCheckedChange={setActive} />
                    </Field>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={mutation.isPending}>
                            {t('shell:common.cancel')}
                        </Button>
                        <Button type="submit" disabled={mutation.isPending}>
                            {t('shell:common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function TeamMembersDialog({ team, onClose }: { team: TeamDto; onClose: () => void }) {
    const { t } = useTranslation('core');
    const usersQuery = useUsers({ perPage: 100 });
    const sync = useSyncTeamMembers();
    const [selected, setSelected] = useState<Set<number>>(new Set((team.users ?? []).map((user) => user.id)));

    function toggle(id: number, checked: boolean) {
        setSelected((previous) => {
            const next = new Set(previous);
            if (checked) {
                next.add(id);
            } else {
                next.delete(id);
            }
            return next;
        });
    }

    return (
        <Dialog open onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {t('teams.members')} — {team.name}
                    </DialogTitle>
                </DialogHeader>
                {sync.isError && <ApiErrorAlert error={sync.error} />}
                <div className="flex flex-col gap-2">
                    {(usersQuery.data?.data ?? []).map((user) => (
                        <label key={user.id} className="flex cursor-pointer items-center gap-2 text-sm">
                            <Checkbox checked={selected.has(user.id)} onCheckedChange={(checked) => toggle(user.id, checked)} />
                            <span>
                                {user.name} <span className="text-muted-foreground">({user.email})</span>
                            </span>
                        </label>
                    ))}
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose} disabled={sync.isPending}>
                        {t('shell:common.cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={sync.isPending}
                        onClick={() => sync.mutate({ id: team.id, userIds: [...selected] }, { onSuccess: onClose })}
                    >
                        {t('shell:common.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
