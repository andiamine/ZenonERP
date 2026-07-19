import type { AnyRoute } from '@tanstack/react-router';
import type { ComponentType } from 'react';

/**
 * The frontend half of a ZenonERP module (CLAUDE.md §7). Every module's
 * `resources/js/index.ts` default-exports one of these; the shell assembles the
 * route tree, nav, and dashboard from the modules enabled for the current tenant.
 * Transport-agnostic: bundled (first-party) and remote (third-party addon) modules
 * satisfy the same contract.
 */
export interface ZenonModule {
    /** Manifest alias (e.g. "sales"). */
    id: string;
    /** Code-based TanStack routes parented on the shell's protected layout route. */
    routes: (parent: AnyRoute) => AnyRoute[];
    nav?: NavItem[];
    widgets?: DashboardWidget[];
    /** i18next resources per language; namespace = module id. */
    locales?: Record<string, () => Promise<object>>;
}

export interface NavItem {
    id: string;
    labelKey: string;
    icon?: string;
    to: string;
    permission?: string;
    /**
     * Sort weight within the flat nav (CLAUDE.md §7 band convention):
     *   0–99   shell (dashboard = 0)
     *   100–499 business verticals
     *   500+   administration
     * Ties break on `id`.
     */
    order: number;
}

export interface DashboardWidget {
    id: string;
    titleKey: string;
    permission?: string;
    /** Lazy component — widgets never load for users who can't see them. */
    component: () => Promise<{ default: ComponentType }>;
}

/**
 * One entry in the generated module registry (resources/js/generated/module-registry.ts).
 * `bundled` = first-party, compiled into this build. `remote` = third-party Module
 * Federation remote, loaded at runtime (Phase 7) — never baked into the registry file;
 * remote refs arrive via /api/v1/bootstrap.
 */
export type RegistryEntry =
    | { source: 'bundled'; load: () => Promise<{ default: ZenonModule }> }
    | { source: 'remote'; url: string; platform: string };

/** Remote module reference as served by /api/v1/bootstrap (Phase 7 consumes). */
export interface RemoteModuleRef {
    id: string;
    url: string;
    platform: string;
}

/**
 * A remote addon the loader could not mount, surfaced to admins via a dismissible
 * banner (Phase 7). `incompatible` = platform-version mismatch (refused before any
 * network fetch); `load_failed` = the remote 404'd, threw, timed out, or exported a
 * non-matching ZenonModule. Non-admins never see these — the console.warn fires for all.
 */
export interface RemoteModuleNotice {
    id: string;
    kind: 'incompatible' | 'load_failed';
    detail: string;
}

/** A tenant company, exactly the backend CompanyData::toArray() shape (§9.1). */
export interface Company {
    id: number;
    name: string;
    code: string;
    currency_code: string;
    is_default: boolean;
}

/** The /api/v1/bootstrap payload after unwrapping the top-level `data` key (§8). */
export interface BootstrapData {
    user: { id: number; name: string; email: string };
    tenant: { id: string; name: string | null };
    companies: Company[];
    current_company_id: number | null;
    enabled_modules: string[];
    remote_modules: RemoteModuleRef[];
    permissions: string[];
    settings: Record<string, unknown>;
    locale: string;
    registryHash: string | null;
    /** Host platform version the remote-addon `platform` constraint is matched against (Task 2). */
    platform_version: string;
}

/** Outcome of the boot-time bootstrap fetch. */
export type BootState =
    | { kind: 'authenticated'; data: BootstrapData }
    | { kind: 'unauthenticated' }
    | { kind: 'central' };
