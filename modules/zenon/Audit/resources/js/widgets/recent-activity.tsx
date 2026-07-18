import { useTranslation } from 'react-i18next';
import { Badge, Skeleton } from '@zenon/core/ui';
import { ApiErrorAlert } from '../components/api-error-alert';
import { RouteLink } from '../components/route-link';
import { useActivity } from '../api/activity';
import { eventBadgeVariant, relativeTime } from '../lib/format';

/**
 * The Phase 5 dashboard dogfood widget (CLAUDE.md §9.2 anti-gold-plating rule: every core
 * service ships only with a working consumer). Default export — `DashboardWidget.component`
 * in ../index.ts lazy-loads this file (resources/js/routes/dashboard.tsx `lazy(widget.component)`).
 */
export default function RecentActivityWidget() {
    const { t } = useTranslation('audit');
    const query = useActivity({ per_page: 5 });
    const activities = query.data?.data ?? [];

    if (query.isLoading) {
        return (
            <div className="flex flex-col gap-2">
                {Array.from({ length: 3 }).map((_, index) => (
                    <Skeleton key={index} className="h-5 w-full" />
                ))}
            </div>
        );
    }

    if (query.isError) {
        return <ApiErrorAlert error={query.error} />;
    }

    return (
        <div className="flex flex-col gap-3 text-sm">
            {activities.length === 0 ? (
                <p className="text-muted-foreground">{t('widgets.recent.empty')}</p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {activities.map((activity) => (
                        <li key={activity.id} className="flex flex-col gap-0.5">
                            <div className="flex items-center gap-2">
                                {activity.event && (
                                    <Badge variant={eventBadgeVariant(activity.event)}>
                                        {t(`events.${activity.event}`, { defaultValue: activity.event })}
                                    </Badge>
                                )}
                                <span className="truncate">{activity.description}</span>
                            </div>
                            <span className="text-xs text-muted-foreground">{relativeTime(activity.created_at, t)}</span>
                        </li>
                    ))}
                </ul>
            )}
            <RouteLink to="/audit" className="text-xs text-primary hover:underline">
                {t('widgets.recent.viewAll')}
            </RouteLink>
        </div>
    );
}
