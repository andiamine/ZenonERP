import {
    Box,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    Link,
    OutlinedInput,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import type { ColumnDef } from '@tanstack/react-table';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
import { ApiErrorAlert, DataTable, Field } from '@zenon/core/ui';
import { RouteLink } from '../components/route-link';
import { useCreateUser, useUsers } from '../api/users';
import type { UserDto } from '../api/types';

export function UsersPage() {
    const { t } = useTranslation('core');
    const boot = useBoot();
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [createOpen, setCreateOpen] = useState(false);

    const query = useUsers({ name: search || undefined, page });
    const canCreate = hasPermission(boot, 'core.users.create');

    const columns: ColumnDef<UserDto>[] = [
        {
            accessorKey: 'name',
            header: t('columns.name'),
            cell: ({ row }) => (
                <Link
                    component={RouteLink}
                    to="/users/$userId"
                    params={{ userId: String(row.original.id) }}
                    underline="hover"
                    sx={{ fontWeight: 500 }}
                >
                    {row.original.name}
                </Link>
            ),
        },
        { accessorKey: 'email', header: t('columns.email') },
        {
            id: 'roles',
            header: t('columns.roles'),
            cell: ({ row }) => {
                const roles = row.original.roles ?? [];
                if (roles.length === 0) {
                    return <Box component="span" sx={{ color: 'text.secondary' }}>{t('shell:common.none')}</Box>;
                }
                return (
                    <Stack direction="row" spacing={0.5} useFlexGap sx={{ flexWrap: 'wrap' }}>
                        {roles.map((role) => (
                            <Chip key={role} size="small" label={role} />
                        ))}
                    </Stack>
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: t('columns.createdAt'),
            cell: ({ row }) => new Date(row.original.created_at).toLocaleDateString(),
        },
    ];

    return (
        <Stack spacing={3}>
            <Stack direction="row" spacing={2} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
                <Typography variant="h5" component="h1" sx={{ fontWeight: 600 }}>
                    {t('users.title')}
                </Typography>
                {canCreate && (
                    <Button variant="contained" onClick={() => setCreateOpen(true)}>
                        {t('users.create')}
                    </Button>
                )}
            </Stack>

            {query.isError && <ApiErrorAlert error={query.error} />}

            <TextField
                sx={{ maxWidth: 320 }}
                placeholder={t('users.searchPlaceholder')}
                value={search}
                onChange={(event) => {
                    setSearch(event.target.value);
                    setPage(1);
                }}
            />

            <DataTable
                columns={columns}
                data={query.data?.data ?? []}
                meta={query.data?.meta}
                onPageChange={setPage}
                isLoading={query.isLoading}
                emptyMessage={t('users.empty')}
            />

            <CreateUserDialog open={createOpen} onOpenChange={setCreateOpen} />
        </Stack>
    );
}

function CreateUserDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    const { t } = useTranslation('core');
    const create = useCreateUser();
    const [form, setForm] = useState({ name: '', email: '', password: '' });

    const errors = create.error instanceof ApiError ? create.error.errors : undefined;

    function submit(event: FormEvent) {
        event.preventDefault();
        create.mutate(form, {
            onSuccess: () => {
                setForm({ name: '', email: '', password: '' });
                onOpenChange(false);
            },
        });
    }

    function close() {
        setForm({ name: '', email: '', password: '' });
        create.reset();
        onOpenChange(false);
    }

    return (
        <Dialog open={open} onClose={close} maxWidth="sm" fullWidth slotProps={{ paper: { component: 'form', onSubmit: submit } }}>
            <DialogTitle>{t('users.create')}</DialogTitle>
            <DialogContent>
                <Stack spacing={2.5} sx={{ pt: 0.5 }}>
                    <Field label={t('columns.name')} htmlFor="user-name" error={errors?.name}>
                        <OutlinedInput
                            id="user-name"
                            value={form.name}
                            onChange={(event) => setForm((f) => ({ ...f, name: event.target.value }))}
                        />
                    </Field>
                    <Field label={t('columns.email')} htmlFor="user-email" error={errors?.email}>
                        <OutlinedInput
                            id="user-email"
                            type="email"
                            value={form.email}
                            onChange={(event) => setForm((f) => ({ ...f, email: event.target.value }))}
                        />
                    </Field>
                    <Field label={t('users.password')} htmlFor="user-password" error={errors?.password}>
                        <OutlinedInput
                            id="user-password"
                            type="password"
                            value={form.password}
                            onChange={(event) => setForm((f) => ({ ...f, password: event.target.value }))}
                        />
                    </Field>
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button color="inherit" onClick={close} disabled={create.isPending}>
                    {t('shell:common.cancel')}
                </Button>
                <Button type="submit" variant="contained" disabled={create.isPending}>
                    {t('shell:common.create')}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
