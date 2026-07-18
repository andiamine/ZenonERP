import { Link } from '@tanstack/react-router';
import type { ReactElement, ReactNode } from 'react';

/**
 * Module route paths are runtime-dynamic (AnyRoute children) and so absent from the
 * statically registered Link `to` union. Replicated locally per module — the eslint
 * cross-module boundary (CLAUDE.md §2) forbids importing Core's copy from
 * `@modules/Core/...`; see modules/zenon/Core/resources/js/components/route-link.tsx for the
 * original.
 */
type RouteLinkProps = {
    to: string;
    params?: Record<string, string>;
    className?: string;
    children?: ReactNode;
};

export const RouteLink = Link as unknown as (props: RouteLinkProps) => ReactElement;
