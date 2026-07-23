import {
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
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
            cell: ({ row }) => (row.original.is_default ? <Chip size="small" color="info" label={t('companies.default')} /> : null),
        },
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
                    {t('companies.title')}
                </Typography>
                {canCreate && (
                    <Button
                        variant="contained"
                        onClick={() => {
                            setEditing(null);
                            setDialogOpen(true);
                        }}
                    >
                        {t('companies.create')}
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
        </Stack>
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

    function close() {
        mutation.reset();
        onOpenChange(false);
    }

    return (
        <Dialog open={open} onClose={close} maxWidth="sm" fullWidth slotProps={{ paper: { component: 'form', onSubmit: submit } }}>
            <DialogTitle>{company ? t('companies.edit') : t('companies.create')}</DialogTitle>
            <DialogContent>
                <Stack spacing={2.5} sx={{ pt: 0.5 }}>
                    <Field label={t('columns.name')} htmlFor="company-name" error={errors?.name}>
                        <OutlinedInput id="company-name" value={name} onChange={(event) => setName(event.target.value)} />
                    </Field>
                    <Field label={t('columns.code')} htmlFor="company-code" error={errors?.code}>
                        <OutlinedInput id="company-code" value={code} onChange={(event) => setCode(event.target.value)} />
                    </Field>
                    <Field label={t('columns.currency')} error={errors?.currency_code}>
                        <Select
                            displayEmpty
                            value={currencyCode}
                            onChange={(event) => setCurrencyCode(event.target.value)}
                        >
                            <MenuItem value="" disabled>
                                <em>{t('companies.currencyPlaceholder')}</em>
                            </MenuItem>
                            {currencies.map((currency) => (
                                <MenuItem key={currency.code} value={currency.code}>
                                    {currency.code} — {currency.name}
                                </MenuItem>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('companies.legalName')} htmlFor="company-legal" error={errors?.legal_name}>
                        <OutlinedInput id="company-legal" value={legalName} onChange={(event) => setLegalName(event.target.value)} />
                    </Field>
                    <Field label={t('companies.countryCode')} htmlFor="company-country" error={errors?.country_code}>
                        <OutlinedInput id="company-country" value={countryCode} onChange={(event) => setCountryCode(event.target.value)} />
                    </Field>
                    <Field label={t('companies.timezone')} htmlFor="company-timezone" error={errors?.timezone}>
                        <OutlinedInput id="company-timezone" value={timezone} onChange={(event) => setTimezone(event.target.value)} />
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
