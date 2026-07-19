import { create } from 'zustand';
import type { RemoteModuleNotice } from './moduleTypes';

/**
 * UI state ONLY (CLAUDE.md §7) — server state lives in TanStack Query.
 * Theme pairs with the pre-hydration script in app.blade.php ('zenon.theme').
 * localStorage is per-origin = per-tenant subdomain, so persisted keys isolate for free.
 */
type Theme = 'light' | 'dark';

const COMPANY_KEY = 'zenon.companyId';
const NAV_KEY = 'zenon.nav';

interface UiState {
    navCollapsed: boolean;
    toggleNav: () => void;
    theme: Theme;
    setTheme: (theme: Theme) => void;
    /** The company whose data the tenant is currently viewing (sent as X-Company-Id). */
    currentCompanyId: number | null;
    setCompany: (id: number) => void;
    syncCompanyFromBoot: (id: number | null) => void;
    /** Remote addons that failed to mount this boot (Phase 7) — shown to admins as a banner. */
    remoteModuleNotices: RemoteModuleNotice[];
    pushRemoteModuleNotice: (notice: RemoteModuleNotice) => void;
    dismissRemoteModuleNotices: () => void;
}

function currentTheme(): Theme {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

function initialCompanyId(): number | null {
    try {
        const raw = localStorage.getItem(COMPANY_KEY);
        if (raw === null) {
            return null;
        }
        const parsed = Number.parseInt(raw, 10);

        return Number.isNaN(parsed) ? null : parsed;
    } catch {
        return null;
    }
}

function initialNavCollapsed(): boolean {
    try {
        return localStorage.getItem(NAV_KEY) === '1';
    } catch {
        return false;
    }
}

export const useUiStore = create<UiState>()((set) => ({
    navCollapsed: initialNavCollapsed(),
    toggleNav: () =>
        set((state) => {
            const navCollapsed = !state.navCollapsed;
            try {
                localStorage.setItem(NAV_KEY, navCollapsed ? '1' : '0');
            } catch {
                // storage unavailable (private mode) — collapse still applies for the session
            }

            return { navCollapsed };
        }),
    theme: currentTheme(),
    setTheme: (theme) => {
        try {
            localStorage.setItem('zenon.theme', theme);
        } catch {
            // storage unavailable (private mode) — theme still applies for the session
        }
        document.documentElement.classList.toggle('dark', theme === 'dark');
        set({ theme });
    },
    currentCompanyId: initialCompanyId(),
    setCompany: (id) => {
        // Company switch = full page reload, deliberate (design doc §2b). bootstrapQuery is
        // staleTime Infinity and its boot is captured in the app-layout route context, so
        // invalidateQueries would leave company-scoped state (Query cache, route context,
        // effective settings) silently stale. A hard reload re-runs the whole boot with the
        // new X-Company-Id — the same philosophy as login/logout's window.location.replace,
        // and correct for an infrequent action. The store isn't set() here: it dies with the
        // reload, which re-seeds currentCompanyId from localStorage below.
        try {
            localStorage.setItem(COMPANY_KEY, String(id));
        } catch {
            // storage unavailable — the reload can't carry the id, acceptable degradation
        }
        window.location.reload();
    },
    syncCompanyFromBoot: (id) => {
        // Reconcile store + localStorage to the server's authoritative current_company_id
        // WITHOUT reloading. Called on every boot (the id may be the tenant's default the
        // user never explicitly picked) and by the bootstrap 403 lockout guard (id === null
        // clears a stale persisted company).
        try {
            if (id === null) {
                localStorage.removeItem(COMPANY_KEY);
            } else {
                localStorage.setItem(COMPANY_KEY, String(id));
            }
        } catch {
            // storage unavailable — the store value still updates for the session
        }
        set({ currentCompanyId: id });
    },
    remoteModuleNotices: [],
    pushRemoteModuleNotice: (notice) =>
        set((state) => {
            // Dedupe by id+kind — the loader reports each remote at most once per kind, but a
            // reload/re-boot could otherwise stack duplicates behind the same session store.
            const duplicate = state.remoteModuleNotices.some((n) => n.id === notice.id && n.kind === notice.kind);

            return duplicate ? state : { remoteModuleNotices: [...state.remoteModuleNotices, notice] };
        }),
    dismissRemoteModuleNotices: () => set({ remoteModuleNotices: [] }),
}));

/**
 * Reports a remote-module failure: a console.warn for everyone (fires regardless of role)
 * AND a deduped notice pushed into the store, which app-layout surfaces to admins as a
 * dismissible banner. Lives here rather than in moduleLoader/remoteModules because the store
 * must never import the loader — the loader imports this.
 */
export function reportRemoteFailure(id: string, kind: RemoteModuleNotice['kind'], detail: string): void {
    console.warn(`[zenon] remote module "${id}" ${kind}: ${detail}`);
    useUiStore.getState().pushRemoteModuleNotice({ id, kind, detail });
}
