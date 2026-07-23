import Drawer from '@mui/material/Drawer';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Toolbar from '@mui/material/Toolbar';
import { Link } from '@tanstack/react-router';
import type { ReactElement, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { hasPermission } from './permissions';
import { useUiStore } from './store';
import { NavIcon } from './ui';
import type { BootstrapData, NavItem, ZenonModule } from './moduleTypes';

export const DRAWER_WIDTH = 240;
export const DRAWER_WIDTH_COLLAPSED = 64;

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

/**
 * Permanent mini-variant Drawer (the canonical MUI dashboard sidebar). Collapse state
 * lives in the ui store (persisted); the toggle is the AppBar menu button in app-layout.
 * Active route styling: TanStack Link's activeProps applies MUI's own `Mui-selected`
 * class, so ListItemButton picks up the theme's selected treatment with no router
 * coupling inside MUI.
 */
export function NavSidebar({ modules, boot }: { modules: ZenonModule[]; boot: BootstrapData }) {
    const { t } = useTranslation();
    const navCollapsed = useUiStore((state) => state.navCollapsed);
    const items = buildNav(modules, boot);
    const width = navCollapsed ? DRAWER_WIDTH_COLLAPSED : DRAWER_WIDTH;

    return (
        <Drawer
            variant="permanent"
            sx={{
                width,
                flexShrink: 0,
                whiteSpace: 'nowrap',
                transition: (theme) => theme.transitions.create('width'),
                '& .MuiDrawer-paper': {
                    width,
                    overflowX: 'hidden',
                    boxSizing: 'border-box',
                    transition: (theme) => theme.transitions.create('width'),
                },
            }}
        >
            {/* Spacer matching the fixed AppBar height so the list starts below it. */}
            <Toolbar />
            <List component="nav" sx={{ px: 0.75 }}>
                {items.map((item) => {
                    const label = t(item.labelKey, { ns: item.ns });

                    return (
                        <ListItem key={`${item.ns}:${item.id}`} disablePadding sx={{ display: 'block' }}>
                            <ListItemButton
                                component={NavLink}
                                to={item.to}
                                activeProps={{ className: 'Mui-selected' }}
                                activeOptions={{ exact: item.to === '/' }}
                                title={navCollapsed ? label : undefined}
                                sx={{
                                    minHeight: 44,
                                    borderRadius: 1,
                                    px: navCollapsed ? 0 : 1.5,
                                    justifyContent: navCollapsed ? 'center' : 'initial',
                                }}
                            >
                                <ListItemIcon sx={{ minWidth: 0, mr: navCollapsed ? 0 : 1.5, justifyContent: 'center' }}>
                                    <NavIcon name={item.icon} />
                                </ListItemIcon>
                                {!navCollapsed && <ListItemText primary={label} slotProps={{ primary: { variant: 'body2', noWrap: true } }} />}
                            </ListItemButton>
                        </ListItem>
                    );
                })}
            </List>
        </Drawer>
    );
}
