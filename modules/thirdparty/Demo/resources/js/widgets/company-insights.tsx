import { useTranslation } from 'react-i18next';
import { Skeleton } from '@zenon/core/ui';
import { useDemoCompanies } from '../api';

/**
 * The Demo dashboard widget (the addon's working consumer, mirroring Audit's recent-activity
 * dogfood). Default export — `DashboardWidget.component` in ../index.ts lazy-loads it. Shares
 * the `useDemoCompanies` query with the page, and shows each company mapped to the addon-computed
 * `insight` string; a company with no `extra` entry falls back to a placeholder.
 */
export default function CompanyInsightsWidget() {
    const { t } = useTranslation('demo');
    const query = useDemoCompanies();
    const companies = query.data?.data ?? [];
    const extra = query.data?.extra ?? {};

    if (query.isLoading) {
        return (
            <div className="flex flex-col gap-2">
                {Array.from({ length: 3 }).map((_, index) => (
                    <Skeleton key={index} className="h-5 w-full" />
                ))}
            </div>
        );
    }

    if (companies.length === 0) {
        return <p className="text-sm text-muted-foreground">{t('widgets.companies.empty')}</p>;
    }

    return (
        <ul className="flex flex-col gap-2 text-sm">
            {companies.map((company) => {
                const insight = extra[company.id];

                return (
                    <li key={company.id} className="flex flex-col gap-0.5">
                        <span className="font-medium">{company.name}</span>
                        <span className="text-xs text-muted-foreground">
                            {insight ? insight.insight : t('widgets.companies.noInsight')}
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}
