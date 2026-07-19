import { type AnyRoute, createRoute, lazyRouteComponent } from '@tanstack/react-router';

/**
 * Demo's code-based route factory (mirrors Audit's shape): one route under `/demo`. No
 * `beforeLoad` permission gate — Demo ships zero permissions (decision D9), so the page is
 * reachable by any authenticated user of a tenant with the addon enabled. The page is lazily
 * code-split into the remote's own chunk.
 */
export function createModuleRoutes(parent: AnyRoute): AnyRoute[] {
    return [
        createRoute({
            getParentRoute: () => parent,
            path: '/demo',
            component: lazyRouteComponent(() => import('./demo-page'), 'DemoPage'),
        }),
    ];
}
