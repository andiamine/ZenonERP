import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, listQuery, type Paginated } from '@zenon/core/apiClient';
import type { TeamDto } from './types';

/**
 * Teams are filtered by the X-Company-Id header server-side (shared NULL rows are visible
 * everywhere). The key stays tenant-scoped per the design doc's enumeration — a company
 * switch hard-reloads the whole app, wiping the Query cache, so the header and the cache
 * never disagree.
 */
export interface TeamListParams {
    name?: string;
    page?: number;
}

export function useTeams(params: TeamListParams) {
    const query = listQuery({
        filter: params.name ? { name: params.name } : undefined,
        include: 'users',
        page: params.page,
    });

    return useQuery({
        queryKey: ['core', 'teams', params],
        queryFn: () => api.get<Paginated<TeamDto>>(`/api/v1/core/teams${query}`),
        placeholderData: keepPreviousData,
    });
}

export interface TeamInput {
    name: string;
    description: string | null;
    company_id: number | null;
    active: boolean;
}

export function useCreateTeam() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (input: TeamInput) => api.post<{ data: TeamDto }>('/api/v1/core/teams', input),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'teams'] }),
    });
}

export function useUpdateTeam() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, ...input }: TeamInput & { id: number }) =>
            api.patch<{ data: TeamDto }>(`/api/v1/core/teams/${id}`, input),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'teams'] }),
    });
}

export function useDeleteTeam() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.delete<void>(`/api/v1/core/teams/${id}`),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'teams'] }),
    });
}

export function useSyncTeamMembers() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, userIds }: { id: number; userIds: number[] }) =>
            api.put<{ data: TeamDto }>(`/api/v1/core/teams/${id}/users`, { user_ids: userIds }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'teams'] }),
    });
}
