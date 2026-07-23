import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import AppBar from '@mui/material/AppBar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import IconButton from '@mui/material/IconButton';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import DarkModeOutlined from '@mui/icons-material/DarkModeOutlined';
import LightModeOutlined from '@mui/icons-material/LightModeOutlined';
import MenuOutlined from '@mui/icons-material/MenuOutlined';
import { createRoute, Outlet, redirect } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import type { BootstrapData, ZenonModule } from '@zenon/core/moduleTypes';
import { useLogout } from '@zenon/core/auth';
import { bootstrapQuery } from '@zenon/core/bootstrap';
import { CompanySwitcher } from '@zenon/core/companySwitcher';
import { NavSidebar } from '@zenon/core/nav';
import { hasPermission } from '@zenon/core/permissions';
import { useUiStore } from '@zenon/core/store';
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

/**
 * The canonical MUI dashboard shell (MUI migration, 2026-07): fixed neutral AppBar over a
 * permanent mini-variant Drawer. The AppBar menu button drives the drawer's collapse
 * (store-persisted); the dark toggle flips the `.dark` class via store.setTheme, which the
 * theme's cssVariables colorSchemeSelector picks up.
 */
function AppShell() {
    const { t } = useTranslation();
    // Explicit annotation — see dashboard.tsx: the dynamic tree erases inference here.
    const { modules, boot }: { modules: ZenonModule[]; boot: BootstrapData } = appLayoutRoute.useRouteContext();
    const logout = useLogout();
    const navCollapsed = useUiStore((state) => state.navCollapsed);
    const toggleNav = useUiStore((state) => state.toggleNav);
    const theme = useUiStore((state) => state.theme);
    const setTheme = useUiStore((state) => state.setTheme);

    return (
        <Box sx={{ display: 'flex', minHeight: '100vh' }}>
            <AppBar
                position="fixed"
                color="inherit"
                elevation={0}
                sx={{
                    zIndex: (muiTheme) => muiTheme.zIndex.drawer + 1,
                    bgcolor: 'background.paper',
                    borderBottom: 1,
                    borderColor: 'divider',
                }}
            >
                <Toolbar sx={{ gap: 1.5 }}>
                    <IconButton
                        edge="start"
                        onClick={toggleNav}
                        aria-label={t(navCollapsed ? 'nav.expand' : 'nav.collapse')}
                    >
                        <MenuOutlined />
                    </IconButton>
                    <Typography variant="h6" component="span" sx={{ fontWeight: 600, letterSpacing: '-0.01em' }}>
                        {t('appName')}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        {boot.tenant.name ?? boot.tenant.id}
                    </Typography>
                    <Box sx={{ flexGrow: 1 }} />
                    <CompanySwitcher boot={boot} />
                    <IconButton
                        onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
                        aria-label={t('nav.toggleTheme')}
                    >
                        {theme === 'dark' ? <LightModeOutlined /> : <DarkModeOutlined />}
                    </IconButton>
                    <Typography variant="body2" color="text.secondary">
                        {boot.user.name}
                    </Typography>
                    <Button color="inherit" onClick={() => logout.mutate()} disabled={logout.isPending}>
                        {t('nav.logout')}
                    </Button>
                </Toolbar>
            </AppBar>
            <NavSidebar modules={modules} boot={boot} />
            <Box component="main" sx={{ flexGrow: 1, minWidth: 0, p: 3 }}>
                {/* Spacer matching the fixed AppBar height. */}
                <Toolbar />
                <RemoteModuleNotices boot={boot} />
                <Outlet />
            </Box>
        </Box>
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
        <Alert
            severity="warning"
            sx={{ mb: 3 }}
            action={
                <Button color="inherit" size="small" onClick={() => dismiss()}>
                    {t('remoteModules.dismiss')}
                </Button>
            }
        >
            <AlertTitle>{t('remoteModules.noticeTitle')}</AlertTitle>
            <Box component="ul" sx={{ m: 0, pl: 2.5, display: 'grid', gap: 0.5 }}>
                {notices.map((notice) => (
                    <li key={`${notice.id}:${notice.kind}`}>
                        {t(`remoteModules.${notice.kind}`, { id: notice.id })}
                        <Box component="span" sx={{ color: 'text.secondary' }}>
                            {' '}
                            — {notice.detail}
                        </Box>
                    </li>
                ))}
            </Box>
        </Alert>
    );
}
