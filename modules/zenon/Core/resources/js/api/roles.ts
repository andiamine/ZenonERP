import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, listQuery, type Paginated } from '@zenon/core/apiClient';
import type { PermissionDto, RoleDto } from './types';

/**
 * Roles are few and needed in full both by the roles page and the user-detail assignment
 * panel, so a single tenant-scoped `['core','roles']` query fetches them all (permissions
 * included for the "count" column). No pagination UI — `per_page` is generous.
 */
export function useRoles() {
    return useQuery({
        queryKey: ['core', 'roles'],
        queryFn: () =>
            api.get<Paginated<RoleDto>>(`/api/v1/core/roles${listQuery({ include: 'permissions', sort: 'name', per_page: 100 })}`),
    });
}

/** The flat catalogue of assignable permissions (`GET /permissions`). */
export function usePermissions() {
    return useQuery({
        queryKey: ['core', 'permissions'],
        queryFn: () => api.get<{ data: PermissionDto[] }>('/api/v1/core/permissions'),
    });
}

export function useCreateRole() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (input: { name: string }) => api.post<{ data: RoleDto }>('/api/v1/core/roles', input),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'roles'] }),
    });
}

export function useUpdateRole() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, name }: { id: number; name: string }) =>
            api.patch<{ data: RoleDto }>(`/api/v1/core/roles/${id}`, { name }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'roles'] }),
    });
}

export function useSyncRolePermissions() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, permissions }: { id: number; permissions: string[] }) =>
            api.put<{ data: RoleDto }>(`/api/v1/core/roles/${id}/permissions`, { permissions }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'roles'] }),
    });
}

export function useDeleteRole() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.delete<void>(`/api/v1/core/roles/${id}`),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['core', 'roles'] }),
    });
}
