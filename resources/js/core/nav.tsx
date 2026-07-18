import { Link } from '@tanstack/react-router';
import type { ReactElement, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { hasPermission } from './permissions';
import { useUiStore } from './store';
import { Button, cn, NavIcon } from './ui';
import type { BootstrapData, NavItem, ZenonModule } from './moduleTypes';

/**
 * Module route paths are runtime-dynamic (AnyRoute children) and therefore absent from the
 * statically registered Link `to` union; `item.to` is produced by the module route
 * factories and nav is permission-filtered to agree with each route's gate. Wrap Link once
 * with a plain-string `to` signature — an honest, localized cast of the dynamic-tree seam
 * (the same reason dashboard.tsx annotates its route context by hand).
 */
type NavLinkProps = {
    to: string;
    className?: string;
    activeProps?: { className?: string };
    activeOptions?: { exact?: boolean };
    title?: string;
    children?: ReactNode;
};

const NavLink = Link as unknown as (props: NavLinkProps) => ReactElement;

/** The shell's own top item; every other item comes from an enabled module's nav. */
const DASHBOARD_ITEM: NavItem & { ns: string } = {
    id: 'dashboard',
    labelKey: 'nav.dashboard',
    icon: 'layout-dashboard',
    to: '/',
    order: 0,
    ns: 'shell',
};

/**
 * Pure, testable nav assembly (CLAUDE.md §7): the shell dashboard item plus every enabled
 * module's nav items (tagged with the module id as their i18n namespace), permission-filtered
 * via hasPermission, then sorted by `order` with `id` as the tiebreak.
 */
export function buildNav(modules: ZenonModule[], boot: BootstrapData): Array<NavItem & { ns: string }> {
    const items: Array<NavItem & { ns: string }> = [
        DASHBOARD_ITEM,
        ...modules.flatMap((module) => (module.nav ?? []).map((item) => ({ ...item, ns: module.id }))),
    ];

    return items
        .filter((item) => item.permission === undefined || hasPermission(boot, item.permission))
        .sort((a, b) => a.order - b.order || a.id.localeCompare(b.id));
}

export function NavSidebar({ modules, boot }: { modules: ZenonModule[]; boot: BootstrapData }) {
    const { t } = useTranslation();
    const navCollapsed = useUiStore((state) => state.navCollapsed);
    const toggleNav = useUiStore((state) => state.toggleNav);
    const items = buildNav(modules, boot);

    return (
        <aside
            className={cn(
                'flex h-full flex-col border-r border-border bg-background transition-[width]',
                navCollapsed ? 'w-14' : 'w-60',
            )}
        >
            <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-2">
                {items.map((item) => {
                    const label = t(item.labelKey, { ns: item.ns });

                    return (
                        <NavLink
                            key={`${item.ns}:${item.id}`}
                            to={item.to}
                            title={navCollapsed ? label : undefined}
                            className={cn(
                                'flex items-center gap-3 rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground',
                                navCollapsed && 'justify-center px-0',
                            )}
                            activeProps={{ className: 'bg-accent text-accent-foreground' }}
                            activeOptions={{ exact: item.to === '/' }}
                        >
                            <NavIcon name={item.icon} />
                            {!navCollapsed && <span className="truncate">{label}</span>}
                        </NavLink>
                    );
                })}
            </nav>
            <div className="border-t border-border p-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={toggleNav}
                    aria-label={t(navCollapsed ? 'nav.expand' : 'nav.collapse')}
                    className={cn('w-full', navCollapsed ? 'justify-center px-0' : 'justify-start')}
                >
                    <NavIcon name={navCollapsed ? 'chevrons-right' : 'chevrons-left'} />
                    {!navCollapsed && <span>{t('nav.collapse')}</span>}
                </Button>
            </div>
        </aside>
    );
}
