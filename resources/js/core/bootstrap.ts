import { queryOptions } from '@tanstack/react-query';
import { api, ApiError, primeCsrf } from './apiClient';
import type { BootState, BootstrapData } from './moduleTypes';

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

        try {
            const { data } = await api.get<{ data: BootstrapData }>('/api/v1/bootstrap');

            return { kind: 'authenticated', data };
        } catch (error) {
            if (error instanceof ApiError && error.status === 401) {
                return { kind: 'unauthenticated' };
            }

            // Central hosts: the tenant-api group 404s there (PreventAccessFromCentralDomains).
            if (error instanceof ApiError && error.status === 404) {
                return { kind: 'central' };
            }

            throw error;
        }
    },
});
