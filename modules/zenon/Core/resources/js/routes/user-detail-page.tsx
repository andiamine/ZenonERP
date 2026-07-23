import {
    Box,
    Button,
    Card,
    CardContent,
    CardHeader,
    Checkbox,
    Chip,
    Divider,
    FormControlLabel,
    Link,
    Skeleton,
    Stack,
    Typography,
} from '@mui/material';
import { useNavigate, useParams } from '@tanstack/react-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
import { ApiErrorAlert, ConfirmDialog } from '@zenon/core/ui';
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
        return <Skeleton variant="rounded" height={192} sx={{ maxWidth: 672 }} />;
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
        <Stack spacing={3} sx={{ maxWidth: 672 }}>
            <Box>
                <Link component={RouteLink} to="/users" underline="hover" variant="body2">
                    ← {t('users.title')}
                </Link>
            </Box>

            <Card variant="outlined">
                <CardHeader title={user.name} />
                <CardContent>
                    <Stack spacing={1}>
                        <Stack direction="row" spacing={1}>
                            <Typography variant="body2" color="text.secondary">
                                {t('columns.email')}:
                            </Typography>
                            <Typography variant="body2">{user.email}</Typography>
                        </Stack>
                        <Stack direction="row" spacing={1}>
                            <Typography variant="body2" color="text.secondary">
                                {t('columns.createdAt')}:
                            </Typography>
                            <Typography variant="body2">{new Date(user.created_at).toLocaleString()}</Typography>
                        </Stack>
                    </Stack>
                </CardContent>
            </Card>

            <Card variant="outlined">
                <CardHeader title={t('users.roles.title')} slotProps={{ title: { variant: 'subtitle2' } }} />
                <CardContent>
                    <Stack spacing={1.5}>
                        {syncRoles.isError && <ApiErrorAlert error={syncRoles.error} />}

                        {canAssign ? (
                            allRoles.length === 0 ? (
                                <Typography variant="body2" color="text.secondary">
                                    {t('roles.empty')}
                                </Typography>
                            ) : (
                                <Stack spacing={0.5}>
                                    {allRoles.map((role) => (
                                        <FormControlLabel
                                            key={role.id}
                                            control={
                                                <Checkbox
                                                    size="small"
                                                    checked={assigned.includes(role.name)}
                                                    onChange={(event) => toggleRole(role.name, event.target.checked)}
                                                    disabled={syncRoles.isPending}
                                                />
                                            }
                                            label={<Typography variant="body2">{role.name}</Typography>}
                                        />
                                    ))}
                                </Stack>
                            )
                        ) : assigned.length === 0 ? (
                            <Typography variant="body2" color="text.secondary">
                                {t('shell:common.none')}
                            </Typography>
                        ) : (
                            <Stack direction="row" spacing={0.5} useFlexGap sx={{ flexWrap: 'wrap' }}>
                                {assigned.map((role) => (
                                    <Chip key={role} size="small" label={role} />
                                ))}
                            </Stack>
                        )}
                    </Stack>
                </CardContent>
            </Card>

            {canDelete && (
                <>
                    <Divider />
                    <Box>
                        <Button variant="contained" color="error" onClick={() => setDeleteOpen(true)}>
                            {t('users.delete')}
                        </Button>
                    </Box>
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
        </Stack>
    );
}
