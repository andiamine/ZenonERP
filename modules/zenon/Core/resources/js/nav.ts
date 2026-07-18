import type { NavItem } from '@zenon/core/moduleTypes';

/**
 * Core's flat nav (CLAUDE.md §7 administration band 500+). labelKeys resolve in the module's
 * own i18n namespace ('core'); icons resolve to the shell's lucide registry by name; each
 * item is permission-filtered against the view permission its route also gates on, so the
 * menu and the routes always agree.
 */
export const nav: NavItem[] = [
    { id: 'settings', labelKey: 'nav.settings', icon: 'settings', to: '/settings', permission: 'core.settings.view', order: 500 },
    { id: 'users', labelKey: 'nav.users', icon: 'users', to: '/users', permission: 'core.users.view', order: 510 },
    { id: 'roles', labelKey: 'nav.roles', icon: 'shield', to: '/roles', permission: 'core.roles.view', order: 520 },
    { id: 'teams', labelKey: 'nav.teams', icon: 'users-round', to: '/teams', permission: 'core.teams.view', order: 530 },
    { id: 'companies', labelKey: 'nav.companies', icon: 'building-2', to: '/companies', permission: 'core.companies.view', order: 540 },
];
