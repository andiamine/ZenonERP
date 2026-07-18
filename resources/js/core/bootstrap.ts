import { queryOptions, useQuery } from '@tanstack/react-query';
import { api, ApiError, primeCsrf } from './apiClient';
import { useUiStore } from './store';
import type { BootState, BootstrapData } from './moduleTypes';

/**
 * Fetches /api/v1/bootstrap and classifies the outcome. `retried` guards the single
 * stale-company recovery below so it can never loop.
 */
async function fetchBootstrap(retried: boolean): Promise<BootState> {
    try {
        const { data } = await api.get<{ data: BootstrapData }>('/api/v1/bootstrap');

        // Reconcile the persisted company id to the server's authoritative value (no reload).
        useUiStore.getState().syncCompanyFromBoot(data.current_company_id);

        return { kind: 'authenticated', data };
    } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
            return { kind: 'unauthenticated' };
        }

        // Central hosts: the tenant-api group 404s there (PreventAccessFromCentralDomains).
        if (error instanceof ApiError && error.status === 404) {
            return { kind: 'central' };
        }

        // Stale X-Company-Id lockout guard: a persisted company the user can no longer
        // access makes SetCurrentCompany reject the entire bootstrap with 403. Clear the id
        // and retry once with no company header — the server then resolves the tenant's
        // default company. Prevents a hard lockout after a company is removed mid-session.
        if (
            error instanceof ApiError &&
            error.status === 403 &&
            !retried &&
            useUiStore.getState().currentCompanyId !== null
        ) {
            useUiStore.getState().syncCompanyFromBoot(null);

            return fetchBootstrap(true);
        }

        throw error;
    }
}

/**
 * The one boot-time bootstrap fetch (CLAUDE.md §7): csrf-cookie → /api/v1/bootstrap,
 * unwrapping the top-level `data` key once. staleTime Infinity — one bootstrap per
 * page lifecycle; login/logout trigger a full navigation which re-runs boot.
 */
export const bootstrapQuery = queryOptions({
    queryKey: ['bootstrap'],
    staleTime: Infinity,
    gcTime: Infinity,
    retry: false,
    queryFn: async (): Promise<BootState> => {
        await primeCsrf();

        return fetchBootstrap(false);
    },
});

/**
 * Reads the bootstrap payload from the Query cache for use INSIDE protected routes only
 * (pages/widgets under the app layout). It is a guaranteed cache hit — main.tsx ensured
 * the query and the app-layout beforeLoad re-checks it — and only mounts when authenticated,
 * so any other state here is a programmer error and throws rather than degrades. This is how
 * pages/widgets read permissions/companies without fighting AnyRoute-erased route-context
 * typing.
 */
export function useBoot(): BootstrapData {
    const { data } = useQuery(bootstrapQuery);

    if (data === undefined || data.kind !== 'authenticated') {
        throw new Error('useBoot() must be called inside an authenticated route');
    }

    return data.data;
}
