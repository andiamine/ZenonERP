import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import { useTranslation } from 'react-i18next';
import { useUiStore } from './store';
import type { BootstrapData } from './moduleTypes';

/**
 * Header company switcher (CLAUDE.md §9.1). Renders nothing for single-company tenants, so
 * they never see the concept. Selecting a company calls setCompany, which persists the id
 * and hard-reloads (design doc §2b) so the whole boot re-runs with the new X-Company-Id.
 * The controlled value prefers the store (the user's live choice) and falls back to the
 * server's authoritative current_company_id from bootstrap.
 */
export function CompanySwitcher({ boot }: { boot: BootstrapData }) {
    const { t } = useTranslation();
    const currentCompanyId = useUiStore((state) => state.currentCompanyId);
    const setCompany = useUiStore((state) => state.setCompany);

    if (boot.companies.length <= 1) {
        return null;
    }

    const value = currentCompanyId ?? boot.current_company_id;

    return (
        <TextField
            select
            value={value ?? ''}
            onChange={(event) => {
                const next = Number(event.target.value);
                if (!Number.isNaN(next)) {
                    setCompany(next);
                }
            }}
            sx={{ minWidth: 160 }}
            slotProps={{ htmlInput: { 'aria-label': t('company.switcher') } }}
        >
            {boot.companies.map((company) => (
                <MenuItem key={company.id} value={company.id}>
                    {company.name}
                </MenuItem>
            ))}
        </TextField>
    );
}
