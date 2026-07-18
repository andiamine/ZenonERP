import { createRoute, redirect } from '@tanstack/react-router';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { ApiError } from '@zenon/core/apiClient';
import { useLogin } from '@zenon/core/auth';
import { bootstrapQuery } from '@zenon/core/bootstrap';
import {
    Alert,
    AlertDescription,
    AlertTitle,
    Button,
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    Input,
    Label,
} from '@zenon/core/ui';
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
        <div className="flex min-h-screen items-center justify-center bg-background p-6">
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle className="text-lg">{t('login.title')}</CardTitle>
                    <CardDescription>{t('login.description')}</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        {error !== null && (
                            <Alert variant="destructive">
                                <AlertTitle>{t('login.failed')}</AlertTitle>
                                <AlertDescription>
                                    {fieldErrors.length > 0
                                        ? fieldErrors.map((message) => <p key={message}>{message}</p>)
                                        : error.message}
                                </AlertDescription>
                            </Alert>
                        )}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="email">{t('login.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                value={email}
                                onChange={(event) => setEmail(event.target.value)}
                                autoComplete="email"
                                required
                                autoFocus
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="password">{t('login.password')}</Label>
                            <Input
                                id="password"
                                type="password"
                                value={password}
                                onChange={(event) => setPassword(event.target.value)}
                                autoComplete="current-password"
                                required
                            />
                        </div>
                        <Button type="submit" disabled={login.isPending}>
                            {login.isPending ? t('login.submitting') : t('login.submit')}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
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
