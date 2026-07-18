import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@zenon/core/apiClient';
import type { SettingDefinitionDto, SettingsValues } from './types';

/**
 * Setting DEFINITIONS are tenant-scoped (module-registered, identical for every company),
 * so their key omits the company id. Effective VALUES are company-scoped — the key carries
 * the current company id even though a switch hard-reloads today, so the cache stays
 * correct if a later phase soft-switches companies (design doc §"Query-key convention").
 */
export function useSettingDefinitions() {
    return useQuery({
        queryKey: ['core', 'settings', 'definitions'],
        queryFn: () => api.get<{ data: SettingDefinitionDto[] }>('/api/v1/core/settings/definitions'),
    });
}

export function useSettings(companyId: number | null) {
    return useQuery({
        queryKey: ['core', 'settings', companyId],
        queryFn: () => api.get<{ data: SettingsValues }>('/api/v1/core/settings'),
    });
}

/**
 * Optimistic settings save (CLAUDE.md §7): the effective-values cache updates immediately,
 * rolls back on error (e.g. a per-key 422), and settles by invalidating — the server
 * returns the fresh effective map, which the invalidate re-fetches.
 */
export function useSaveSettings(companyId: number | null) {
    const queryClient = useQueryClient();
    const key = ['core', 'settings', companyId];

    return useMutation({
        mutationFn: (values: SettingsValues) => api.put<{ data: SettingsValues }>('/api/v1/core/settings', { values }),
        onMutate: async (values) => {
            await queryClient.cancelQueries({ queryKey: key });
            const previous = queryClient.getQueryData<{ data: SettingsValues }>(key);
            if (previous) {
                queryClient.setQueryData<{ data: SettingsValues }>(key, { data: { ...previous.data, ...values } });
            }
            return { previous };
        },
        onError: (_error, _values, context) => {
            if (context?.previous) {
                queryClient.setQueryData(key, context.previous);
            }
        },
        onSettled: () => {
            void queryClient.invalidateQueries({ queryKey: key });
        },
    });
}
