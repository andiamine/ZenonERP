import { createRoute, Outlet, redirect } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import type { BootstrapData, ZenonModule } from '@zenon/core/moduleTypes';
import { useLogout } from '@zenon/core/auth';
import { bootstrapQuery } from '@zenon/core/bootstrap';
import { CompanySwitcher } from '@zenon/core/companySwitcher';
import { NavSidebar } from '@zenon/core/nav';
import { hasPermission } from '@zenon/core/permissions';
import { useUiStore } from '@zenon/core/store';
import { Alert, AlertDescription, AlertTitle, Button } from '@zenon/core/ui';
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
                    <RemoteModuleNotices boot={boot} />
                    <Outlet />
                </main>
            </div>
        </div>
    );
}

/**
 * Non-blocking admin banner for remote addons that failed to mount this boot (Phase 7).
 * Only settings admins (core.settings.update) see it — the console.warn already fired for
 * everyone. Renders nothing when there are no notices or the user can't act on them.
 */
function RemoteModuleNotices({ boot }: { boot: BootstrapData }) {
    const { t } = useTranslation();
    const notices = useUiStore((state) => state.remoteModuleNotices);
    const dismiss = useUiStore((state) => state.dismissRemoteModuleNotices);

    if (notices.length === 0 || !hasPermission(boot, 'core.settings.update')) {
        return null;
    }

    return (
        <Alert variant="warning" className="mb-6">
            <AlertTitle>{t('remoteModules.noticeTitle')}</AlertTitle>
            <AlertDescription>
                <ul className="grid gap-1">
                    {notices.map((notice) => (
                        <li key={`${notice.id}:${notice.kind}`}>
                            {t(`remoteModules.${notice.kind}`, { id: notice.id })}
                            <span className="text-muted-foreground"> — {notice.detail}</span>
                        </li>
                    ))}
                </ul>
                <div className="mt-2">
                    <Button variant="ghost" size="sm" onClick={() => dismiss()}>
                        {t('remoteModules.dismiss')}
                    </Button>
                </div>
            </AlertDescription>
        </Alert>
    );
}
