import type { QueryClient } from '@tanstack/react-query';
import { createRootRouteWithContext, Link, Outlet } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import type { ZenonModule } from '@zenon/core/moduleTypes';
import { Button } from '@zenon/core/ui';

export interface RouterContext {
    queryClient: QueryClient;
    modules: ZenonModule[];
}

function NotFoundPage() {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
            <p className="text-7xl font-bold tracking-tight text-muted-foreground">404</p>
            <h1 className="text-xl font-semibold">{t('errors.notFoundTitle')}</h1>
            <p className="max-w-md text-sm text-muted-foreground">{t('errors.notFoundBody')}</p>
            <Button render={<Link to="/" />} variant="outline">
                {t('errors.backHome')}
            </Button>
        </div>
    );
}

function RootErrorPage({ error }: { error: Error }) {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
            <h1 className="text-xl font-semibold">{t('errors.bootTitle')}</h1>
            <p className="max-w-md text-sm text-muted-foreground">{error.message}</p>
            <Button onClick={() => window.location.reload()}>{t('errors.retry')}</Button>
        </div>
    );
}

export const rootRoute = createRootRouteWithContext<RouterContext>()({
    component: Outlet,
    notFoundComponent: NotFoundPage,
    errorComponent: RootErrorPage,
});
