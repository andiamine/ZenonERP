import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { createRoute, redirect } from '@tanstack/react-router';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useLogin } from '@zenon/core/auth';
import { bootstrapQuery } from '@zenon/core/bootstrap';
import { rootRoute } from './__root';

function LoginPage() {
    const { t } = useTranslation();
    const { redirect: redirectTo } = loginRoute.useSearch();
    const login = useLogin(redirectTo);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');

    const error = login.error instanceof ApiError ? login.error : null;
    const fieldErrors = error?.errors !== undefined ? Object.values(error.errors).flat() : [];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        login.mutate({ email, password });
    };

    return (
        <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', p: 3, bgcolor: 'background.default' }}>
            <Card variant="outlined" sx={{ width: '100%', maxWidth: 400 }}>
                <CardContent sx={{ p: 4 }}>
                    <Stack component="form" onSubmit={submit} spacing={3}>
                        <Stack spacing={0.5}>
                            <Typography variant="h5" component="h1" sx={{ fontWeight: 600 }}>
                                {t('login.title')}
                            </Typography>
                            <Typography variant="body2" color="text.secondary">
                                {t('login.description')}
                            </Typography>
                        </Stack>
                        {error !== null && (
                            <Alert severity="error">
                                <AlertTitle>{t('login.failed')}</AlertTitle>
                                {fieldErrors.length > 0
                                    ? fieldErrors.map((message) => <div key={message}>{message}</div>)
                                    : error.message}
                            </Alert>
                        )}
                        <TextField
                            id="email"
                            type="email"
                            label={t('login.email')}
                            value={email}
                            onChange={(event) => setEmail(event.target.value)}
                            autoComplete="email"
                            required
                            autoFocus
                            fullWidth
                        />
                        <TextField
                            id="password"
                            type="password"
                            label={t('login.password')}
                            value={password}
                            onChange={(event) => setPassword(event.target.value)}
                            autoComplete="current-password"
                            required
                            fullWidth
                        />
                        <Button type="submit" variant="contained" fullWidth disabled={login.isPending}>
                            {login.isPending ? t('login.submitting') : t('login.submit')}
                        </Button>
                    </Stack>
                </CardContent>
            </Card>
        </Box>
    );
}

export const loginRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: '/login',
    validateSearch: (search: Record<string, unknown>): { redirect?: string } =>
        typeof search.redirect === 'string' ? { redirect: search.redirect } : {},
    beforeLoad: async ({ context }) => {
        const boot = await context.queryClient.ensureQueryData(bootstrapQuery);

        if (boot.kind === 'authenticated') {
            throw redirect({ to: '/' });
        }
    },
    component: LoginPage,
});
