import type { NavItem } from '@zenon/core/moduleTypes';

/** Audit's single admin nav item (CLAUDE.md §7 administration band 500+). */
export const nav: NavItem[] = [
    { id: 'activity', labelKey: 'nav.activity', icon: 'history', to: '/audit', permission: 'audit.activities.view', order: 560 },
];
