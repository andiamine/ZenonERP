import { redirect } from '@tanstack/react-router';
import type { BootstrapData } from './moduleTypes';

/**
 * Permission checks for the shell and modules (CLAUDE.md §8). `'*'` is the admin-role
 * super-user grant emitted by the backend's `Gate::before` convention (§9.1) — it must
 * short-circuit true, otherwise admins (whose bootstrap `permissions` is exactly `['*']`)
 * would be denied every gated nav item, widget, and route.
 */
export function hasPermission(boot: BootstrapData, permission: string): boolean {
    return boot.permissions.includes('*') || boot.permissions.includes(permission);
}

/**
 * Route-level gate (§8, 403 pattern): call from a module route's `beforeLoad`. The parent
 * app-layout `beforeLoad` returns `{ boot }` into the route context; this reads it back and
 * redirects deep links to /403 when the permission is missing. Nav is permission-filtered by
 * the same `hasPermission`, so the menu and the gate always agree. `context` is typed
 * `unknown` because the dynamically-assembled module route tree erases context inference.
 */
export function requirePermission(context: unknown, permission: string): void {
    const boot = (context as { boot?: BootstrapData }).boot;

    if (boot === undefined || !hasPermission(boot, permission)) {
        throw redirect({ to: '/403' });
    }
}
