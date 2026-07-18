import type { NavItem } from '@zenon/core/moduleTypes';

/** Sequence's single admin nav item (CLAUDE.md §7 administration band 500+). */
export const nav: NavItem[] = [
    { id: 'sequences', labelKey: 'nav.sequences', icon: 'list-ordered', to: '/sequence', permission: 'sequence.sequences.view', order: 550 },
];
