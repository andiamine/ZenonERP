import {
    Box,
    Button,
    Checkbox,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    MenuItem,
    OutlinedInput,
    Select,
    Stack,
    Switch,
    Typography,
} from '@mui/material';
import type { ColumnDef } from '@tanstack/react-table';
import { type FormEvent, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
import { ApiErrorAlert, ConfirmDialog, DataTable, Field } from '@zenon/core/ui';
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
            cell: ({ row }) => row.original.description ?? <Box component="span" sx={{ color: 'text.secondary' }}>—</Box>,
        },
        { id: 'company', header: t('columns.company'), cell: ({ row }) => companyName(row.original.company_id) },
        {
            accessorKey: 'active',
            header: t('columns.active'),
            cell: ({ row }) =>
                row.original.active ? (
                    <Chip size="small" color="success" label={t('status.active')} />
                ) : (
                    <Chip size="small" label={t('status.inactive')} />
                ),
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => (
                <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
                    {canUpdate && (
                        <Button size="small" variant="outlined" color="inherit" onClick={() => setMembers(row.original)}>
                            {t('teams.members')}
                        </Button>
                    )}
                    {canUpdate && (
                        <Button
                            size="small"
                            variant="outlined"
                            color="inherit"
                            onClick={() => {
                                setEditing(row.original);
                                setDialogOpen(true);
                            }}
                        >
                            {t('shell:common.edit')}
                        </Button>
                    )}
                    {canDelete && (
                        <Button size="small" color="inherit" onClick={() => setDeleting(row.original)}>
                            {t('shell:common.delete')}
                        </Button>
                    )}
                </Stack>
            ),
        },
    ];

    return (
        <Stack spacing={3}>
            <Stack direction="row" spacing={2} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
                <Typography variant="h5" component="h1" sx={{ fontWeight: 600 }}>
                    {t('teams.title')}
                </Typography>
                {canCreate && (
                    <Button
                        variant="contained"
                        onClick={() => {
                            setEditing(null);
                            setDialogOpen(true);
                        }}
                    >
                        {t('teams.create')}
                    </Button>
                )}
            </Stack>

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
        </Stack>
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

    function close() {
        mutation.reset();
        onOpenChange(false);
    }

    return (
        <Dialog open={open} onClose={close} maxWidth="sm" fullWidth slotProps={{ paper: { component: 'form', onSubmit: submit } }}>
            <DialogTitle>{team ? t('teams.edit') : t('teams.create')}</DialogTitle>
            <DialogContent>
                <Stack spacing={2.5} sx={{ pt: 0.5 }}>
                    <Field label={t('columns.name')} htmlFor="team-name" error={errors?.name}>
                        <OutlinedInput id="team-name" value={name} onChange={(event) => setName(event.target.value)} />
                    </Field>
                    <Field label={t('columns.description')} htmlFor="team-description" error={errors?.description}>
                        <OutlinedInput id="team-description" value={description} onChange={(event) => setDescription(event.target.value)} />
                    </Field>
                    <Field label={t('columns.company')} error={errors?.company_id}>
                        <Select
                            value={companyId === null ? SHARED : String(companyId)}
                            onChange={(event) => {
                                const next = event.target.value;
                                setCompanyId(next === SHARED ? null : Number(next));
                            }}
                        >
                            <MenuItem value={SHARED}>{t('teams.shared')}</MenuItem>
                            {boot.companies.map((company) => (
                                <MenuItem key={company.id} value={String(company.id)}>
                                    {company.name}
                                </MenuItem>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('columns.active')}>
                        <Switch
                            checked={active}
                            onChange={(event) => setActive(event.target.checked)}
                            sx={{ alignSelf: 'flex-start' }}
                        />
                    </Field>
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button color="inherit" onClick={close} disabled={mutation.isPending}>
                    {t('shell:common.cancel')}
                </Button>
                <Button type="submit" variant="contained" disabled={mutation.isPending}>
                    {t('shell:common.save')}
                </Button>
            </DialogActions>
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
        <Dialog open onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>
                {t('teams.members')} — {team.name}
            </DialogTitle>
            <DialogContent>
                <Stack spacing={2} sx={{ pt: 0.5 }}>
                    {sync.isError && <ApiErrorAlert error={sync.error} />}
                    <Stack spacing={0.5}>
                        {(usersQuery.data?.data ?? []).map((user) => (
                            <FormControlLabel
                                key={user.id}
                                control={
                                    <Checkbox
                                        size="small"
                                        checked={selected.has(user.id)}
                                        onChange={(event) => toggle(user.id, event.target.checked)}
                                    />
                                }
                                label={
                                    <Typography variant="body2" component="span">
                                        {user.name}{' '}
                                        <Box component="span" sx={{ color: 'text.secondary' }}>
                                            ({user.email})
                                        </Box>
                                    </Typography>
                                }
                            />
                        ))}
                    </Stack>
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button color="inherit" onClick={onClose} disabled={sync.isPending}>
                    {t('shell:common.cancel')}
                </Button>
                <Button
                    variant="contained"
                    disabled={sync.isPending}
                    onClick={() => sync.mutate({ id: team.id, userIds: [...selected] }, { onSuccess: onClose })}
                >
                    {t('shell:common.save')}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
