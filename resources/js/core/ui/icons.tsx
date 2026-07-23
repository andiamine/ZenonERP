import ApartmentOutlined from '@mui/icons-material/ApartmentOutlined';
import FormatListNumberedOutlined from '@mui/icons-material/FormatListNumberedOutlined';
import GroupOutlined from '@mui/icons-material/GroupOutlined';
import GroupsOutlined from '@mui/icons-material/GroupsOutlined';
import HistoryOutlined from '@mui/icons-material/HistoryOutlined';
import KeyboardDoubleArrowLeftOutlined from '@mui/icons-material/KeyboardDoubleArrowLeftOutlined';
import KeyboardDoubleArrowRightOutlined from '@mui/icons-material/KeyboardDoubleArrowRightOutlined';
import SettingsOutlined from '@mui/icons-material/SettingsOutlined';
import ShieldOutlined from '@mui/icons-material/ShieldOutlined';
import SpaceDashboardOutlined from '@mui/icons-material/SpaceDashboardOutlined';
import type { ComponentType } from 'react';

/**
 * Curated icon registry — the NavItem.icon string -> component seam (CLAUDE.md §7).
 * The KEYS are the addon-facing contract (unchanged across the MUI migration: remotes
 * keep declaring `icon: 'building-2'`); the values are @mui/icons-material components,
 * imported by subpath so only these ten ride the host bundle. Module pages may import
 * their own icons by subpath directly — this registry exists for the string-only
 * ZenonModule nav contract, not as the sole icon source.
 */
export const icons: Record<string, ComponentType<{ className?: string; fontSize?: 'inherit' | 'small' | 'medium' | 'large' }>> = {
    'layout-dashboard': SpaceDashboardOutlined,
    settings: SettingsOutlined,
    users: GroupOutlined,
    shield: ShieldOutlined,
    'users-round': GroupsOutlined,
    'building-2': ApartmentOutlined,
    'list-ordered': FormatListNumberedOutlined,
    history: HistoryOutlined,
    'chevrons-left': KeyboardDoubleArrowLeftOutlined,
    'chevrons-right': KeyboardDoubleArrowRightOutlined,
};

export interface NavIconProps {
    name?: string;
    className?: string;
}

function NavIcon({ name, className }: NavIconProps) {
    const Icon = name ? icons[name] : undefined;
    if (!Icon) {
        return null;
    }
    return <Icon fontSize="small" className={className} />;
}

export { NavIcon };
