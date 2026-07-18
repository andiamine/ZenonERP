import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, listQuery, type Paginated } from '@zenon/core/apiClient';
import type { UserDto } from './types';

/**
 * Query keys follow the `[module, resource, ...scope]` convention (design doc §"Query-key
 * convention"). Users are tenant-scoped (not company-scoped), so the current company id is
 * deliberately absent from these keys — only `settings` includes it.
 */
export interface UserListParams {
    name?: string;
    page?: number;
    /** Override the default page size — used by member pickers that need the full list. */
    perPage?: number;
}

export function useUsers(params: UserListParams) {
    const query = listQuery({
        filter: params.name ? { name: params.name } : undefined,
        include: 'roles',
        page: params.page,
        per_page: params.perPage,
    });

    return useQuery({
        queryKey: ['core', 'users', params],
        queryFn: () => api.get<Paginated<UserDto>>(`/api/v1/core/users${query}`),
        placeholderData: keepPreviousData,
    });
}

export function useUser(id: number) {
    return useQuery({
        queryKey: ['core', 'users', 'detail', id],
        queryFn: () => api.get<{ data: UserDto }>(`/api/v1/core/users/${id}?include=roles`),
    });
}

export interface CreateUserInput {
    name: string;
    email: string;
    password: string;
}

export function useCreateUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (input: CreateUserInput) => api.post<{ data: UserDto }>('/api/v1/core/users', input),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'users'] }),
    });
}

export function useDeleteUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.delete<void>(`/api/v1/core/users/${id}`),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'users'] }),
    });
}

/**
 * Optimistic role assignment (CLAUDE.md §7): the detail cache flips immediately, rolls back
 * on error, and settles with an invalidate of both the detail and the list (list badges
 * reflect the new roles). This is the Phase 5 role-toggle verify flow.
 */
export function useSyncUserRoles(userId: number) {
    const queryClient = useQueryClient();
    const key = ['core', 'users', 'detail', userId];

    return useMutation({
        mutationFn: (roles: string[]) => api.put<{ data: UserDto }>(`/api/v1/core/users/${userId}/roles`, { roles }),
        onMutate: async (roles) => {
            await queryClient.cancelQueries({ queryKey: key });
            const previous = queryClient.getQueryData<{ data: UserDto }>(key);
            if (previous) {
                queryClient.setQueryData<{ data: UserDto }>(key, { data: { ...previous.data, roles } });
            }
            return { previous };
        },
        onError: (_error, _roles, context) => {
            if (context?.previous) {
                queryClient.setQueryData(key, context.previous);
            }
        },
        onSettled: () => {
            void queryClient.invalidateQueries({ queryKey: key });
            void queryClient.invalidateQueries({ queryKey: ['core', 'users'] });
        },
    });
}
