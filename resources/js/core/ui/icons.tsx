import { Building2, History, LayoutDashboard, ListOrdered, Settings, Shield, Users, UsersRound } from 'lucide-react';
import type { ComponentType } from 'react';
import { cn } from './cn';

/**
 * Curated lucide-react registry — the NavItem.icon string -> component seam
 * (CLAUDE.md §7). Modules and remotes never import an icon library directly
 * (ESLint-banned outside core/ui); they reference icons by name, which keeps
 * the ZenonModule contract string-only for future MF remotes.
 */
export const icons: Record<string, ComponentType<{ className?: string }>> = {
    'layout-dashboard': LayoutDashboard,
    settings: Settings,
    users: Users,
    shield: Shield,
    'users-round': UsersRound,
    'building-2': Building2,
    'list-ordered': ListOrdered,
    history: History,
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
    return <Icon className={cn('size-4', className)} />;
}

export { NavIcon };
