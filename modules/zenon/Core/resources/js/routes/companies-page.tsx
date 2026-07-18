import type { ColumnDef } from '@tanstack/react-table';
import { type FormEvent, useEffect, useState } from 'react';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
    Switch,
} from '@zenon/core/ui';
import { ApiErrorAlert } from '../components/api-error-alert';
import { ConfirmDialog } from '../components/confirm-dialog';
import { useCompanies, useCreateCompany, useDeleteCompany, useUpdateCompany } from '../api/companies';
import { useCurrencies } from '../api/currencies';
import type { CompanyDto } from '../api/types';

export function CompaniesPage() {
    const { t } = useTranslation('core');
    const boot = useBoot();
    const [page, setPage] = useState(1);
    const query = useCompanies({ page });
    const deleteCompany = useDeleteCompany();

    const [editing, setEditing] = useState<CompanyDto | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState<CompanyDto | null>(null);

    const canCreate = hasPermission(boot, 'core.companies.create');
    const canUpdate = hasPermission(boot, 'core.companies.update');
    const canDelete = hasPermission(boot, 'core.companies.delete');

    const columns: ColumnDef<CompanyDto>[] = [
        { accessorKey: 'name', header: t('columns.name') },
        { accessorKey: 'code', header: t('columns.code') },
        { accessorKey: 'currency_code', header: t('columns.currency') },
        {
            accessorKey: 'is_default',
            header: t('columns.default'),
            cell: ({ row }) => (row.original.is_default ? <Badge variant="info">{t('companies.default')}</Badge> : null),
        },
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
                <h1 className="text-lg font-semibold">{t('companies.title')}</h1>
                {canCreate && (
                    <Button
                        onClick={() => {
                            setEditing(null);
                            setDialogOpen(true);
                        }}
                    >
                        {t('companies.create')}
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
                emptyMessage={t('companies.empty')}
            />

            {(canCreate || canUpdate) && <CompanyDialog open={dialogOpen} onOpenChange={setDialogOpen} company={editing} />}

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        deleteCompany.reset();
                        setDeleting(null);
                    }
                }}
                title={t('companies.delete')}
                description={t('companies.deleteConfirm', { name: deleting?.name ?? '' })}
                isPending={deleteCompany.isPending}
                error={deleteCompany.error}
                onConfirm={() => {
                    if (deleting) {
                        deleteCompany.mutate(deleting.id, { onSuccess: () => setDeleting(null) });
                    }
                }}
            />
        </div>
    );
}

function CompanyDialog({ open, onOpenChange, company }: { open: boolean; onOpenChange: (open: boolean) => void; company: CompanyDto | null }) {
    const { t } = useTranslation('core');
    const currenciesQuery = useCurrencies();
    const createCompany = useCreateCompany();
    const updateCompany = useUpdateCompany();

    const [name, setName] = useState('');
    const [code, setCode] = useState('');
    const [currencyCode, setCurrencyCode] = useState('');
    const [legalName, setLegalName] = useState('');
    const [countryCode, setCountryCode] = useState('');
    const [timezone, setTimezone] = useState('');
    const [active, setActive] = useState(true);

    useEffect(() => {
        if (open) {
            setName(company?.name ?? '');
            setCode(company?.code ?? '');
            setCurrencyCode(company?.currency_code ?? '');
            setLegalName(company?.legal_name ?? '');
            setCountryCode(company?.country_code ?? '');
            setTimezone(company?.timezone ?? '');
            setActive(company?.active ?? true);
        }
    }, [open, company]);

    const mutation = company ? updateCompany : createCompany;
    const errors = mutation.error instanceof ApiError ? mutation.error.errors : undefined;
    const currencies = currenciesQuery.data?.data ?? [];

    function submit(event: FormEvent) {
        event.preventDefault();
        const input = {
            name,
            code,
            currency_code: currencyCode,
            legal_name: legalName || null,
            country_code: countryCode || null,
            timezone: timezone || null,
            active,
        };
        if (company) {
            updateCompany.mutate({ id: company.id, ...input }, { onSuccess: () => onOpenChange(false) });
        } else {
            createCompany.mutate(input, { onSuccess: () => onOpenChange(false) });
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
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{company ? t('companies.edit') : t('companies.create')}</DialogTitle>
                </DialogHeader>
                <form className="flex flex-col gap-4" onSubmit={submit}>
                    <Field label={t('columns.name')} htmlFor="company-name" error={errors?.name}>
                        <Input id="company-name" value={name} onValueChange={setName} />
                    </Field>
                    <Field label={t('columns.code')} htmlFor="company-code" error={errors?.code}>
                        <Input id="company-code" value={code} onValueChange={setCode} />
                    </Field>
                    <Field label={t('columns.currency')} error={errors?.currency_code}>
                        <Select
                            value={currencyCode || null}
                            items={currencies.map((currency) => ({ label: `${currency.code} — ${currency.name}`, value: currency.code }))}
                            onValueChange={(next) => setCurrencyCode(next === null ? '' : (next as string))}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder={t('companies.currencyPlaceholder')} />
                            </SelectTrigger>
                            <SelectContent>
                                {currencies.map((currency) => (
                                    <SelectItem key={currency.code} value={currency.code}>
                                        {currency.code} — {currency.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label={t('companies.legalName')} htmlFor="company-legal" error={errors?.legal_name}>
                        <Input id="company-legal" value={legalName} onValueChange={setLegalName} />
                    </Field>
                    <Field label={t('companies.countryCode')} htmlFor="company-country" error={errors?.country_code}>
                        <Input id="company-country" value={countryCode} onValueChange={setCountryCode} />
                    </Field>
                    <Field label={t('companies.timezone')} htmlFor="company-timezone" error={errors?.timezone}>
                        <Input id="company-timezone" value={timezone} onValueChange={setTimezone} />
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
