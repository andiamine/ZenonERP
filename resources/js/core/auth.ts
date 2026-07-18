import { useMutation } from '@tanstack/react-query';
import { api, primeCsrf } from './apiClient';

/**
 * Login/logout both finish with a full navigation (window.location.replace), not a
 * router navigate: the route tree is fixed at createRouter() and enabled-module
 * routes only exist after an authenticated bootstrap — a hard navigation re-runs
 * boot so the full tree materializes (CLAUDE.md §7 boot order).
 */

/** Only same-origin relative paths survive (open-redirect guard). */
export function safeRedirect(target: string | undefined): string {
    if (target !== undefined && target.startsWith('/') && !target.startsWith('//')) {
        return target;
    }

    return '/';
}

export function useLogin(redirectTo: string | undefined) {
    return useMutation({
        mutationFn: async (credentials: { email: string; password: string }) => {
            await primeCsrf();
            await api.post('/api/v1/auth/login', credentials);
        },
        onSuccess: () => {
            window.location.replace(safeRedirect(redirectTo));
        },
    });
}

export function useLogout() {
    return useMutation({
        mutationFn: () => api.post<void>('/api/v1/auth/logout'),
        onSuccess: () => {
            window.location.replace('/login');
        },
    });
}
