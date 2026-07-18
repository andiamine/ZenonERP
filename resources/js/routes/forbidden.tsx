import { createRoute, Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { Button } from '@zenon/core/ui';
import { rootRoute } from './__root';

function ForbiddenPage() {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
            <p className="text-7xl font-bold tracking-tight text-muted-foreground">403</p>
            <h1 className="text-xl font-semibold">{t('errors.forbiddenTitle')}</h1>
            <p className="max-w-md text-sm text-muted-foreground">{t('errors.forbiddenBody')}</p>
            <Button render={<Link to="/" />} variant="outline">
                {t('errors.backHome')}
            </Button>
        </div>
    );
}

export const forbiddenRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: '/403',
    component: ForbiddenPage,
});
