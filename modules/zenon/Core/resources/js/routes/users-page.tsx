import type { ColumnDef } from '@tanstack/react-table';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useBoot } from '@zenon/core/bootstrap';
import { hasPermission } from '@zenon/core/permissions';
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
} from '@zenon/core/ui';
import { ApiErrorAlert } from '../components/api-error-alert';
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
                <RouteLink to="/users/$userId" params={{ userId: String(row.original.id) }} className="font-medium text-primary hover:underline">
                    {row.original.name}
                </RouteLink>
            ),
        },
        { accessorKey: 'email', header: t('columns.email') },
        {
            id: 'roles',
            header: t('columns.roles'),
            cell: ({ row }) => {
                const roles = row.original.roles ?? [];
                if (roles.length === 0) {
                    return <span className="text-muted-foreground">{t('shell:common.none')}</span>;
                }
                return (
                    <div className="flex flex-wrap gap-1">
                        {roles.map((role) => (
                            <Badge key={role} variant="secondary">
                                {role}
                            </Badge>
                        ))}
                    </div>
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
        <div className="flex flex-col gap-6">
            <div className="flex items-center justify-between gap-4">
                <h1 className="text-lg font-semibold">{t('users.title')}</h1>
                {canCreate && <Button onClick={() => setCreateOpen(true)}>{t('users.create')}</Button>}
            </div>

            {query.isError && <ApiErrorAlert error={query.error} />}

            <Input
                className="max-w-xs"
                placeholder={t('users.searchPlaceholder')}
                value={search}
                onValueChange={(next) => {
                    setSearch(next);
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
        </div>
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

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    setForm({ name: '', email: '', password: '' });
                    create.reset();
                }
                onOpenChange(next);
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('users.create')}</DialogTitle>
                </DialogHeader>
                <form className="flex flex-col gap-4" onSubmit={submit}>
                    <Field label={t('columns.name')} htmlFor="user-name" error={errors?.name}>
                        <Input id="user-name" value={form.name} onValueChange={(next) => setForm((f) => ({ ...f, name: next }))} />
                    </Field>
                    <Field label={t('columns.email')} htmlFor="user-email" error={errors?.email}>
                        <Input id="user-email" type="email" value={form.email} onValueChange={(next) => setForm((f) => ({ ...f, email: next }))} />
                    </Field>
                    <Field label={t('users.password')} htmlFor="user-password" error={errors?.password}>
                        <Input
                            id="user-password"
                            type="password"
                            value={form.password}
                            onValueChange={(next) => setForm((f) => ({ ...f, password: next }))}
                        />
                    </Field>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={create.isPending}>
                            {t('shell:common.cancel')}
                        </Button>
                        <Button type="submit" disabled={create.isPending}>
                            {t('shell:common.create')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
