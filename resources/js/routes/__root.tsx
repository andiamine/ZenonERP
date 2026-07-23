import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { QueryClient } from '@tanstack/react-query';
import { createRootRouteWithContext, Link, Outlet } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import type { ZenonModule } from '@zenon/core/moduleTypes';

export interface RouterContext {
    queryClient: QueryClient;
    modules: ZenonModule[];
}

function NotFoundPage() {
    const { t } = useTranslation();

    return (
        <Stack sx={{ minHeight: '100vh', alignItems: 'center', justifyContent: 'center', p: 3, textAlign: 'center' }} spacing={2}>
            <Typography sx={{ fontSize: 72, fontWeight: 700, color: 'text.disabled', lineHeight: 1 }}>404</Typography>
            <Typography variant="h6" component="h1">
                {t('errors.notFoundTitle')}
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 440 }}>
                {t('errors.notFoundBody')}
            </Typography>
            <Button component={Link} to="/" variant="outlined" color="inherit">
                {t('errors.backHome')}
            </Button>
        </Stack>
    );
}

function RootErrorPage({ error }: { error: Error }) {
    const { t } = useTranslation();

    return (
        <Stack sx={{ minHeight: '100vh', alignItems: 'center', justifyContent: 'center', p: 3, textAlign: 'center' }} spacing={2}>
            <Typography variant="h6" component="h1">
                {t('errors.bootTitle')}
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 440 }}>
                {error.message}
            </Typography>
            <Button variant="contained" onClick={() => window.location.reload()}>{t('errors.retry')}</Button>
        </Stack>
    );
}

export const rootRoute = createRootRouteWithContext<RouterContext>()({
    component: Outlet,
    notFoundComponent: NotFoundPage,
    errorComponent: RootErrorPage,
});
