import { useNavigate, useParams } from '@tanstack/react-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Checkbox, Separator, Skeleton } from '@zenon/core/ui';
import { ApiErrorAlert } from '../components/api-error-alert';
import { ConfirmDialog } from '../components/confirm-dialog';
import { RouteLink } from '../components/route-link';
import { useDeleteUser, useSyncUserRoles, useUser } from '../api/users';
import { useRoles } from '../api/roles';

export function UserDetailPage() {
    const { t } = useTranslation('core');
    const boot = useBoot();
    const params = useParams({ strict: false }) as { userId: string };
    const userId = Number(params.userId);
    // Dynamic module path — not in the statically registered navigate union (see route-link.tsx).
    const navigate = useNavigate() as unknown as (options: { to: string }) => Promise<void>;

    const userQuery = useUser(userId);
    const rolesQuery = useRoles();
    const syncRoles = useSyncUserRoles(userId);
    const deleteUser = useDeleteUser();
    const [deleteOpen, setDeleteOpen] = useState(false);

    const canAssign = hasPermission(boot, 'core.roles.assign');
    const canDelete = hasPermission(boot, 'core.users.delete');

    if (userQuery.isLoading) {
        return <Skeleton className="h-48 w-full max-w-2xl" />;
    }

    if (userQuery.isError || !userQuery.data) {
        return <ApiErrorAlert error={userQuery.error} />;
    }

    const user = userQuery.data.data;
    const assigned = user.roles ?? [];
    const allRoles = rolesQuery.data?.data ?? [];

    function toggleRole(name: string, checked: boolean) {
        const next = checked ? [...assigned, name] : assigned.filter((role) => role !== name);
        syncRoles.mutate(next);
    }

    return (
        <div className="flex max-w-2xl flex-col gap-6">
            <div className="flex items-center gap-3">
                <RouteLink to="/users" className="text-sm text-primary hover:underline">
                    ← {t('users.title')}
                </RouteLink>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{user.name}</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-2 text-sm">
                    <div className="flex gap-2">
                        <span className="text-muted-foreground">{t('columns.email')}:</span>
                        <span>{user.email}</span>
                    </div>
                    <div className="flex gap-2">
                        <span className="text-muted-foreground">{t('columns.createdAt')}:</span>
                        <span>{new Date(user.created_at).toLocaleString()}</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-sm">{t('users.roles.title')}</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    {syncRoles.isError && <ApiErrorAlert error={syncRoles.error} />}

                    {canAssign ? (
                        allRoles.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('roles.empty')}</p>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {allRoles.map((role) => (
                                    <label key={role.id} className="flex cursor-pointer items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={assigned.includes(role.name)}
                                            onCheckedChange={(checked) => toggleRole(role.name, checked)}
                                            disabled={syncRoles.isPending}
                                        />
                                        <span>{role.name}</span>
                                    </label>
                                ))}
                            </div>
                        )
                    ) : assigned.length === 0 ? (
                        <span className="text-sm text-muted-foreground">{t('shell:common.none')}</span>
                    ) : (
                        <div className="flex flex-wrap gap-1">
                            {assigned.map((role) => (
                                <Badge key={role} variant="secondary">
                                    {role}
                                </Badge>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {canDelete && (
                <>
                    <Separator />
                    <div>
                        <Button variant="destructive" onClick={() => setDeleteOpen(true)}>
                            {t('users.delete')}
                        </Button>
                    </div>
                    <ConfirmDialog
                        open={deleteOpen}
                        onOpenChange={(next) => {
                            if (!next) {
                                deleteUser.reset();
                            }
                            setDeleteOpen(next);
                        }}
                        title={t('users.delete')}
                        description={t('users.deleteConfirm', { name: user.name })}
                        isPending={deleteUser.isPending}
                        error={deleteUser.error}
                        onConfirm={() =>
                            deleteUser.mutate(userId, {
                                onSuccess: () => {
                                    setDeleteOpen(false);
                                    void navigate({ to: '/users' });
                                },
                            })
                        }
                    />
                </>
            )}
        </div>
    );
}
