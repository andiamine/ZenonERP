import type { ColumnDef } from '@tanstack/react-table';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
import {
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
} from '@zenon/core/ui';
import { ApiErrorAlert } from '../components/api-error-alert';
import { ConfirmDialog } from '../components/confirm-dialog';
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
                <div className="flex justify-end gap-2">
                    {canUpdate && (
                        <Button size="sm" variant="outline" onClick={() => openEdit(row.original)}>
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
                <h1 className="text-lg font-semibold">{t('roles.title')}</h1>
                {canCreate && <Button onClick={openCreate}>{t('roles.create')}</Button>}
            </div>

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
        </div>
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
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{role ? t('roles.edit') : t('roles.create')}</DialogTitle>
                </DialogHeader>
                <form className="flex flex-col gap-4" onSubmit={submit}>
                    <Field label={t('columns.name')} htmlFor="role-name" error={nameError}>
                        <Input id="role-name" value={name} onValueChange={setName} />
                    </Field>

                    {saveError instanceof ApiError && saveError.type !== 'validation_error' && <ApiErrorAlert error={saveError} />}

                    <div className="flex flex-col gap-4">
                        <span className="text-sm font-medium">{t('roles.permissions')}</span>
                        {Object.entries(grouped).map(([segment, permissions]) => (
                            <div key={segment} className="flex flex-col gap-2">
                                <span className="text-xs font-semibold text-muted-foreground uppercase">{segment}</span>
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    {permissions.map((permission) => (
                                        <label key={permission.id} className="flex cursor-pointer items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={selected.has(permission.name)}
                                                onCheckedChange={(checked) => toggle(permission.name, checked)}
                                            />
                                            <span>{permission.name}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={pending}>
                            {t('shell:common.cancel')}
                        </Button>
                        <Button type="submit" disabled={pending}>
                            {t('shell:common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
