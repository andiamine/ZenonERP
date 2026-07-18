import { createRoute } from '@tanstack/react-router';
import { lazy, Suspense, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { BootstrapData, DashboardWidget, ZenonModule } from '@zenon/core/moduleTypes';
import { Card, CardContent, CardHeader, CardTitle, Spinner } from '@zenon/core/ui';
import { appLayoutRoute } from './app-layout';

function WidgetSlot({ widget }: { widget: DashboardWidget }) {
    const { t } = useTranslation(widget.id.split('.')[0]);
    const Component = useMemo(() => lazy(widget.component), [widget]);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">{t(widget.titleKey)}</CardTitle>
            </CardHeader>
            <CardContent>
                <Suspense fallback={<Spinner />}>
                    <Component />
                </Suspense>
            </CardContent>
        </Card>
    );
}

/** The §7 dashboard host: permission-filtered widgets from enabled modules. */
function DashboardPage() {
    const { t } = useTranslation();
    // Explicit annotation: the dynamically-assembled route tree (module routes are
    // AnyRoute[]) erases context inference, so name the shape we registered.
    const { modules, boot }: { modules: ZenonModule[]; boot: BootstrapData } = appLayoutRoute.useRouteContext();

    const widgets = modules
        .flatMap((module) => module.widgets ?? [])
        .filter((widget) => widget.permission === undefined || boot.permissions.includes(widget.permission));

    return (
        <div className="flex flex-col gap-6">
            <h1 className="text-lg font-semibold">{t('dashboard.title')}</h1>
            {widgets.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('dashboard.empty')}</p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {widgets.map((widget) => (
                        <WidgetSlot key={widget.id} widget={widget} />
                    ))}
                </div>
            )}
        </div>
    );
}

export const dashboardRoute = createRoute({
    getParentRoute: () => appLayoutRoute,
    path: '/',
    component: DashboardPage,
});
