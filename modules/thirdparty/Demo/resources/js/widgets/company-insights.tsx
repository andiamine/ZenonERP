import { Skeleton, Stack, Typography } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { useDemoCompanies } from '../api';

/**
 * The Demo dashboard widget (the addon's working consumer, mirroring Audit's recent-activity
 * dogfood). Default export — `DashboardWidget.component` in ../index.ts lazy-loads it. Shares
 * the `useDemoCompanies` query with the page, and shows each company mapped to the addon-computed
 * `insight` string; a company with no `extra` entry falls back to a placeholder. Renders inside
 * the dashboard's WidgetSlot Card, so it stays lean (no Card of its own); UI comes from
 * `@mui/material` (the host's shared singleton — root barrel ONLY, the addon platform contract).
 */
export default function CompanyInsightsWidget() {
    const { t } = useTranslation('demo');
    const query = useDemoCompanies();
    const companies = query.data?.data ?? [];
    const extra = query.data?.extra ?? {};

    if (query.isLoading) {
        return (
            <Stack spacing={1}>
                {Array.from({ length: 3 }).map((_, index) => (
                    <Skeleton key={index} variant="rounded" height={20} />
                ))}
            </Stack>
        );
    }

    if (companies.length === 0) {
        return (
            <Typography variant="body2" color="text.secondary">
                {t('widgets.companies.empty')}
            </Typography>
        );
    }

    return (
        <Stack component="ul" spacing={1} sx={{ m: 0, p: 0, listStyle: 'none' }}>
            {companies.map((company) => {
                const insight = extra[company.id];

                return (
                    <Stack component="li" key={company.id} spacing={0.25}>
                        <Typography variant="body2" sx={{ fontWeight: 500 }}>
                            {company.name}
                        </Typography>
                        <Typography variant="caption" color="text.secondary">
                            {insight ? insight.insight : t('widgets.companies.noInsight')}
                        </Typography>
                    </Stack>
                );
            })}
        </Stack>
    );
}
