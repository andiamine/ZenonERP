import { createRoute, Outlet, redirect } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import type { BootstrapData, ZenonModule } from '@zenon/core/moduleTypes';
import { useLogout } from '@zenon/core/auth';
import { bootstrapQuery } from '@zenon/core/bootstrap';
import { CompanySwitcher } from '@zenon/core/companySwitcher';
import { NavSidebar } from '@zenon/core/nav';
import { Button } from '@zenon/core/ui';
import { rootRoute } from './__root';

/**
 * Pathless protected layout: every authenticated page — the dashboard and all
 * module-contributed routes — parents here. beforeLoad gates on the bootstrap
 * state (cache hit after main.tsx) and redirects guests to /login, preserving
 * the intended path for the post-login hard navigation.
 */
export const appLayoutRoute = createRoute({
    getParentRoute: () => rootRoute,
    id: 'app',
    beforeLoad: async ({ context, location }) => {
        const boot = await context.queryClient.ensureQueryData(bootstrapQuery);

        if (boot.kind !== 'authenticated') {
            throw redirect({ to: '/login', search: { redirect: location.href } });
        }

        return { boot: boot.data };
    },
    component: AppShell,
});

function AppShell() {
    const { t } = useTranslation();
    // Explicit annotation — see dashboard.tsx: the dynamic tree erases inference here.
    const { modules, boot }: { modules: ZenonModule[]; boot: BootstrapData } = appLayoutRoute.useRouteContext();
    const logout = useLogout();

    return (
        <div className="flex h-screen bg-background">
            <NavSidebar modules={modules} boot={boot} />
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-14 items-center justify-between border-b border-border px-6">
                    <div className="flex items-center gap-3">
                        <span className="font-semibold tracking-tight">{t('appName')}</span>
                        <span className="text-sm text-muted-foreground">{boot.tenant.name ?? boot.tenant.id}</span>
                    </div>
                    <div className="flex items-center gap-3">
                        <CompanySwitcher boot={boot} />
                        <span className="text-sm text-muted-foreground">{boot.user.name}</span>
                        <Button variant="ghost" size="sm" onClick={() => logout.mutate()} disabled={logout.isPending}>
                            {t('nav.logout')}
                        </Button>
                    </div>
                </header>
                <main className="min-w-0 flex-1 overflow-y-auto p-6">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
