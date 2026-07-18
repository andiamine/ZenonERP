import { type AnyRoute, createRoute, lazyRouteComponent } from '@tanstack/react-router';
import { requirePermission } from '@zenon/core/permissions';

/**
 * Audit's code-based route factory (design doc §"route mechanics"): one admin route under
 * `/audit` (path convention: business/service modules own `/{alias}/...`; the kernel core is
 * the sole exception). Gates the view permission in `beforeLoad` (deep links → /403) and
 * lazily code-splits the page.
 */
export function createModuleRoutes(parent: AnyRoute): AnyRoute[] {
    return [
        createRoute({
            getParentRoute: () => parent,
            path: '/audit',
            beforeLoad: ({ context }) => requirePermission(context, 'audit.activities.view'),
            component: lazyRouteComponent(() => import('./activity-page'), 'ActivityPage'),
        }),
    ];
}
