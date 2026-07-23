import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { createRoute, Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { rootRoute } from './__root';

function ForbiddenPage() {
    const { t } = useTranslation();

    return (
        <Stack sx={{ minHeight: '100vh', alignItems: 'center', justifyContent: 'center', p: 3, textAlign: 'center' }} spacing={2}>
            <Typography sx={{ fontSize: 72, fontWeight: 700, color: 'text.disabled', lineHeight: 1 }}>403</Typography>
            <Typography variant="h6" component="h1">
                {t('errors.forbiddenTitle')}
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 440 }}>
                {t('errors.forbiddenBody')}
            </Typography>
            <Button component={Link} to="/" variant="outlined" color="inherit">
                {t('errors.backHome')}
            </Button>
        </Stack>
    );
}

export const forbiddenRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: '/403',
    component: ForbiddenPage,
});
