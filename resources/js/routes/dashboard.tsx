import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardHeader from '@mui/material/CardHeader';
import CircularProgress from '@mui/material/CircularProgress';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { createRoute } from '@tanstack/react-router';
import { lazy, Suspense, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { BootstrapData, DashboardWidget, ZenonModule } from '@zenon/core/moduleTypes';
import { hasPermission } from '@zenon/core/permissions';
import { appLayoutRoute } from './app-layout';

function WidgetSlot({ widget }: { widget: DashboardWidget }) {
    const { t } = useTranslation(widget.id.split('.')[0]);
    const Component = useMemo(() => lazy(widget.component), [widget]);

    return (
        <Card variant="outlined" sx={{ height: '100%' }}>
            <CardHeader title={t(widget.titleKey)} slotProps={{ title: { variant: 'subtitle2' } }} sx={{ pb: 0 }} />
            <CardContent>
                <Suspense fallback={<CircularProgress size={20} />}>
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
        .filter((widget) => widget.permission === undefined || hasPermission(boot, widget.permission));

    return (
        <Stack spacing={3}>
            <Typography variant="h5" component="h1" sx={{ fontWeight: 600 }}>
                {t('dashboard.title')}
            </Typography>
            {widgets.length === 0 ? (
                <Typography variant="body2" color="text.secondary">
                    {t('dashboard.empty')}
                </Typography>
            ) : (
                <Grid container spacing={2}>
                    {widgets.map((widget) => (
                        <Grid key={widget.id} size={{ xs: 12, sm: 6, lg: 4 }}>
                            <WidgetSlot widget={widget} />
                        </Grid>
                    ))}
                </Grid>
            )}
        </Stack>
    );
}

export const dashboardRoute = createRoute({
    getParentRoute: () => appLayoutRoute,
    path: '/',
    component: DashboardPage,
});
