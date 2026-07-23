import { Chip, Link, Skeleton, Stack, Typography } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { ApiErrorAlert } from '@zenon/core/ui';
import { RouteLink } from '../components/route-link';
import { useActivity } from '../api/activity';
import { eventBadgeVariant, relativeTime, type EventBadgeVariant } from '../lib/format';

/** Old Badge-variant vocabulary (lib/format.ts) → MUI Chip color. */
const CHIP_COLOR: Record<EventBadgeVariant, 'success' | 'info' | 'error' | 'default'> = {
    success: 'success',
    info: 'info',
    destructive: 'error',
    secondary: 'default',
};

/**
 * The Phase 5 dashboard dogfood widget (CLAUDE.md §9.2 anti-gold-plating rule: every core
 * service ships only with a working consumer). Default export — `DashboardWidget.component`
 * in ../index.ts lazy-loads this file (resources/js/routes/dashboard.tsx `lazy(widget.component)`).
 * Renders inside the dashboard's WidgetSlot Card, so it stays lean — no Card of its own.
 */
export default function RecentActivityWidget() {
    const { t } = useTranslation('audit');
    const query = useActivity({ per_page: 5 });
    const activities = query.data?.data ?? [];

    if (query.isLoading) {
        return (
            <Stack spacing={1}>
                {Array.from({ length: 3 }).map((_, index) => (
                    <Skeleton key={index} variant="rounded" height={20} />
                ))}
            </Stack>
        );
    }

    if (query.isError) {
        return <ApiErrorAlert error={query.error} />;
    }

    return (
        <Stack spacing={1.5}>
            {activities.length === 0 ? (
                <Typography variant="body2" color="text.secondary">
                    {t('widgets.recent.empty')}
                </Typography>
            ) : (
                <Stack component="ul" spacing={1} sx={{ m: 0, p: 0, listStyle: 'none' }}>
                    {activities.map((activity) => (
                        <Stack component="li" key={activity.id} spacing={0.25}>
                            <Stack direction="row" spacing={1} sx={{ alignItems: 'center', minWidth: 0 }}>
                                {activity.event && (
                                    <Chip
                                        size="small"
                                        color={CHIP_COLOR[eventBadgeVariant(activity.event)]}
                                        label={t(`events.${activity.event}`, { defaultValue: activity.event })}
                                    />
                                )}
                                <Typography variant="body2" noWrap>
                                    {activity.description}
                                </Typography>
                            </Stack>
                            <Typography variant="caption" color="text.secondary">
                                {relativeTime(activity.created_at, t)}
                            </Typography>
                        </Stack>
                    ))}
                </Stack>
            )}
            <Link component={RouteLink} to="/audit" variant="caption" underline="hover">
                {t('widgets.recent.viewAll')}
            </Link>
        </Stack>
    );
}
