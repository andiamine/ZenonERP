import { create } from 'zustand';

/**
 * UI state ONLY (CLAUDE.md §7) — server state lives in TanStack Query.
 * Theme pairs with the pre-hydration script in app.blade.php ('zenon.theme').
 */
type Theme = 'light' | 'dark';

interface UiState {
    navCollapsed: boolean;
    toggleNav: () => void;
    theme: Theme;
    setTheme: (theme: Theme) => void;
}

function currentTheme(): Theme {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

export const useUiStore = create<UiState>()((set) => ({
    navCollapsed: false,
    toggleNav: () => set((state) => ({ navCollapsed: !state.navCollapsed })),
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
}));
