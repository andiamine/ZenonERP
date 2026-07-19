import type { NavItem } from '@zenon/core/moduleTypes';

/**
 * Demo's single nav item (CLAUDE.md §7 business band 100–499). No `permission` — Demo ships
 * zero permissions (decision D9). `icon` is a string resolved against the host's `@zenon/core/ui`
 * icon registry (`building-2` exists there); remotes never import an icon library directly.
 */
export const nav: NavItem[] = [{ id: 'demo', labelKey: 'nav.demo', icon: 'building-2', to: '/demo', order: 400 }];
