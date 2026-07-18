import type { QueryClient } from '@tanstack/react-query';
import { createRouter } from '@tanstack/react-router';
import { appLayoutRoute } from '../routes/app-layout';
import { dashboardRoute } from '../routes/dashboard';
import { forbiddenRoute } from '../routes/forbidden';
import { loginRoute } from '../routes/login';
import { rootRoute } from '../routes/__root';
import type { ZenonModule } from './moduleTypes';

/**
 * Assembles the route tree (CLAUDE.md §7): the shell owns root/login/403/layout,
 * enabled modules contribute children under the protected layout. The tree is
 * fixed per page lifecycle — login/logout hard-navigate so it rebuilds with the
 * right module set.
 */
export function buildRouter(modules: ZenonModule[], queryClient: QueryClient) {
    const routeTree = rootRoute.addChildren([
        loginRoute,
        forbiddenRoute,
        appLayoutRoute.addChildren([dashboardRoute, ...modules.flatMap((module) => module.routes(appLayoutRoute))]),
    ]);

    return createRouter({
        routeTree,
        context: { queryClient, modules },
        defaultPreload: 'intent',
    });
}

declare module '@tanstack/react-router' {
    interface Register {
        router: ReturnType<typeof buildRouter>;
    }
}
