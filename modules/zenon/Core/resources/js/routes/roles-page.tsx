import {
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    Grid,
    OutlinedInput,
    Stack,
    Typography,
} from '@mui/material';
import type { ColumnDef } from '@tanstack/react-table';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
import { ApiErrorAlert, ConfirmDialog, DataTable, Field } from '@zenon/core/ui';
import { useCreateRole, useDeleteRole, usePermissions, useRoles, useSyncRolePermissions, useUpdateRole } from '../api/roles';
import type { PermissionDto, RoleDto } from '../api/types';

export function RolesPage() {
    const { t } = useTranslation('core');
    const boot = useBoot();
    const rolesQuery = useRoles();
    const deleteRole = useDeleteRole();

    const [editing, setEditing] = useState<RoleDto | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState<RoleDto | null>(null);

    const canCreate = hasPermission(boot, 'core.roles.create');
    const canUpdate = hasPermission(boot, 'core.roles.update');
    const canDelete = hasPermission(boot, 'core.roles.delete');

    function openCreate() {
        setEditing(null);
        setDialogOpen(true);
    }

    function openEdit(role: RoleDto) {
        setEditing(role);
        setDialogOpen(true);
    }

    const columns: ColumnDef<RoleDto>[] = [
        { accessorKey: 'name', header: t('columns.name') },
        {
            id: 'permissions',
            header: t('columns.permissionCount'),
            cell: ({ row }) => row.original.permissions?.length ?? 0,
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => (
                <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
                    {canUpdate && (
                        <Button size="small" variant="outlined" color="inherit" onClick={() => openEdit(row.original)}>
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
                    {t('roles.title')}
                </Typography>
                {canCreate && (
                    <Button variant="contained" onClick={openCreate}>
                        {t('roles.create')}
                    </Button>
                )}
            </Stack>

            {rolesQuery.isError && <ApiErrorAlert error={rolesQuery.error} />}

            <DataTable columns={columns} data={rolesQuery.data?.data ?? []} isLoading={rolesQuery.isLoading} emptyMessage={t('roles.empty')} />

            {(canCreate || canUpdate) && <RoleDialog open={dialogOpen} onOpenChange={setDialogOpen} role={editing} />}

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        deleteRole.reset();
                        setDeleting(null);
                    }
                }}
                title={t('roles.delete')}
                description={t('roles.deleteConfirm', { name: deleting?.name ?? '' })}
                isPending={deleteRole.isPending}
                error={deleteRole.error}
                onConfirm={() => {
                    if (deleting) {
                        deleteRole.mutate(deleting.id, { onSuccess: () => setDeleting(null) });
                    }
                }}
            />
        </Stack>
    );
}

function RoleDialog({ open, onOpenChange, role }: { open: boolean; onOpenChange: (open: boolean) => void; role: RoleDto | null }) {
    const { t } = useTranslation('core');
    const permissionsQuery = usePermissions();
    const createRole = useCreateRole();
    const updateRole = useUpdateRole();
    const syncPermissions = useSyncRolePermissions();

    const [name, setName] = useState('');
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [saveError, setSaveError] = useState<unknown>(null);

    useEffect(() => {
        if (open) {
            setName(role?.name ?? '');
            setSelected(new Set(role?.permissions ?? []));
            setSaveError(null);
        }
    }, [open, role]);

    const grouped = useMemo(() => {
        const groups: Record<string, PermissionDto[]> = {};
        for (const permission of permissionsQuery.data?.data ?? []) {
            const segment = permission.name.split('.')[0] ?? permission.name;
            (groups[segment] ??= []).push(permission);
        }
        return groups;
    }, [permissionsQuery.data]);

    const nameError = saveError instanceof ApiError ? saveError.errors?.name : undefined;
    const pending = createRole.isPending || updateRole.isPending || syncPermissions.isPending;

    function toggle(permissionName: string, checked: boolean) {
        setSelected((previous) => {
            const next = new Set(previous);
            if (checked) {
                next.add(permissionName);
            } else {
                next.delete(permissionName);
            }
            return next;
        });
    }

    async function submit(event: FormEvent) {
        event.preventDefault();
        setSaveError(null);
        try {
            let roleId = role?.id;
            if (role) {
                if (name !== role.name) {
                    await updateRole.mutateAsync({ id: role.id, name });
                }
            } else {
                const created = await createRole.mutateAsync({ name });
                roleId = created.data.id;
            }
            if (roleId !== undefined) {
                await syncPermissions.mutateAsync({ id: roleId, permissions: [...selected] });
            }
            onOpenChange(false);
        } catch (error) {
            setSaveError(error);
        }
    }

    return (
        <Dialog
            open={open}
            onClose={() => onOpenChange(false)}
            maxWidth="sm"
            fullWidth
            slotProps={{ paper: { component: 'form', onSubmit: submit } }}
        >
            <DialogTitle>{role ? t('roles.edit') : t('roles.create')}</DialogTitle>
            <DialogContent>
                <Stack spacing={2.5} sx={{ pt: 0.5 }}>
                    <Field label={t('columns.name')} htmlFor="role-name" error={nameError}>
                        <OutlinedInput id="role-name" value={name} onChange={(event) => setName(event.target.value)} />
                    </Field>

                    {saveError instanceof ApiError && saveError.type !== 'validation_error' && <ApiErrorAlert error={saveError} />}

                    <Stack spacing={2}>
                        <Typography variant="body2" sx={{ fontWeight: 500 }}>
                            {t('roles.permissions')}
                        </Typography>
                        {Object.entries(grouped).map(([segment, permissions]) => (
                            <Stack key={segment} spacing={1}>
                                <Typography
                                    variant="caption"
                                    sx={{ fontWeight: 600, color: 'text.secondary', textTransform: 'uppercase' }}
                                >
                                    {segment}
                                </Typography>
                                <Grid container spacing={0.5}>
                                    {permissions.map((permission) => (
                                        <Grid key={permission.id} size={{ xs: 12, sm: 6 }}>
                                            <FormControlLabel
                                                control={
                                                    <Checkbox
                                                        size="small"
                                                        checked={selected.has(permission.name)}
                                                        onChange={(event) => toggle(permission.name, event.target.checked)}
                                                    />
                                                }
                                                label={<Typography variant="body2">{permission.name}</Typography>}
                                            />
                                        </Grid>
                                    ))}
                                </Grid>
                            </Stack>
                        ))}
                    </Stack>
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button color="inherit" onClick={() => onOpenChange(false)} disabled={pending}>
                    {t('shell:common.cancel')}
                </Button>
                <Button type="submit" variant="contained" disabled={pending}>
                    {t('shell:common.save')}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
