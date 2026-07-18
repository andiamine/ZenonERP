import { Link } from '@tanstack/react-router';
import type { ReactElement, ReactNode } from 'react';

/**
 * Module route paths are runtime-dynamic (AnyRoute children) and so absent from the
 * statically registered Link `to` union — the same seam nav.tsx wraps. A single honest,
 * localized cast of Link to a plain-string `to`/`params` signature keeps intra-module
 * navigation type-clean without fighting the erased tree.
 */
type RouteLinkProps = {
    to: string;
    params?: Record<string, string>;
    className?: string;
    children?: ReactNode;
};

export const RouteLink = Link as unknown as (props: RouteLinkProps) => ReactElement;
