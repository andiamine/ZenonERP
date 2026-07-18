import { useTranslation } from 'react-i18next';
import { useUiStore } from './store';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from './ui';
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
        <Select
            value={value}
            // Feeding `items` lets <SelectValue> render the company name (not the raw id).
            items={boot.companies.map((company) => ({ label: company.name, value: company.id }))}
            onValueChange={(next) => {
                if (next !== null) {
                    setCompany(next);
                }
            }}
        >
            <SelectTrigger size="sm" aria-label={t('company.switcher')}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent align="end">
                {boot.companies.map((company) => (
                    <SelectItem key={company.id} value={company.id}>
                        {company.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
