import { type AnyRoute, createRoute, lazyRouteComponent } from '@tanstack/react-router';
import { requirePermission } from '@zenon/core/permissions';

/**
 * Core's code-based route factory (design doc §"route mechanics"). The kernel is the one
 * exception to the `/{alias}/...` path convention — being non-disableable, it owns the
 * friendly top-level paths (/settings, /users, …). Each route gates its VIEW permission in
 * beforeLoad (deep links → /403) and lazily code-splits its page.
 */
export function createModuleRoutes(parent: AnyRoute): AnyRoute[] {
    return [
        createRoute({
            getParentRoute: () => parent,
            path: '/settings',
            beforeLoad: ({ context }) => requirePermission(context, 'core.settings.view'),
            component: lazyRouteComponent(() => import('./settings-page'), 'SettingsPage'),
        }),
        createRoute({
            getParentRoute: () => parent,
            path: '/users',
            beforeLoad: ({ context }) => requirePermission(context, 'core.users.view'),
            component: lazyRouteComponent(() => import('./users-page'), 'UsersPage'),
        }),
        createRoute({
            getParentRoute: () => parent,
            path: '/users/$userId',
            beforeLoad: ({ context }) => requirePermission(context, 'core.users.view'),
            component: lazyRouteComponent(() => import('./user-detail-page'), 'UserDetailPage'),
        }),
        createRoute({
            getParentRoute: () => parent,
            path: '/roles',
            beforeLoad: ({ context }) => requirePermission(context, 'core.roles.view'),
            component: lazyRouteComponent(() => import('./roles-page'), 'RolesPage'),
        }),
        createRoute({
            getParentRoute: () => parent,
            path: '/teams',
            beforeLoad: ({ context }) => requirePermission(context, 'core.teams.view'),
            component: lazyRouteComponent(() => import('./teams-page'), 'TeamsPage'),
        }),
        createRoute({
            getParentRoute: () => parent,
            path: '/companies',
            beforeLoad: ({ context }) => requirePermission(context, 'core.companies.view'),
            component: lazyRouteComponent(() => import('./companies-page'), 'CompaniesPage'),
        }),
    ];
}
