import '../css/app.css';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { RouterProvider } from '@tanstack/react-router';
import { StrictMode, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { applyLocale, initI18n } from '@zenon/core/i18n';
import { bootstrapQuery } from '@zenon/core/bootstrap';
import { loadEnabledModules } from '@zenon/core/moduleLoader';
import { buildRouter } from '@zenon/core/router';
import { CentralPlaceholder } from './routes/central-placeholder';

// §7 defaults; the bootstrap query overrides staleTime to Infinity itself.
const queryClient = new QueryClient({
    defaultOptions: {
        queries: { staleTime: 30_000, refetchOnWindowFocus: true },
    },
});

function render(node: ReactNode): void {
    createRoot(document.getElementById('root')!).render(<StrictMode>{node}</StrictMode>);
}

/** Boot order (CLAUDE.md §7): i18n → csrf + bootstrap → enabled modules → router. */
async function boot(): Promise<void> {
    await initI18n();

    const state = await queryClient.ensureQueryData(bootstrapQuery);

    if (state.kind === 'central') {
        render(<CentralPlaceholder />);

        return;
    }

    if (state.kind === 'authenticated') {
        await applyLocale(state.data.locale);
    }

    const modules = state.kind === 'authenticated' ? await loadEnabledModules(state.data) : [];
    const router = buildRouter(modules, queryClient);

    render(
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );
}

boot().catch((error: unknown) => {
    // Network failure / 500 during boot — static retry screen (i18n may not be up).
    console.error('[zenon] boot failed', error);

    const screen = document.createElement('div');
    screen.style.cssText =
        'min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;font-family:system-ui,sans-serif';

    const title = document.createElement('h1');
    title.style.cssText = 'font-size:18px;margin:0';
    title.textContent = 'Something went wrong';

    const body = document.createElement('p');
    body.style.cssText = 'color:#666;margin:0';
    body.textContent = 'ZenonERP could not start. Check your connection and try again.';

    const retry = document.createElement('button');
    retry.style.cssText = 'padding:8px 16px;cursor:pointer';
    retry.textContent = 'Retry';
    retry.addEventListener('click', () => window.location.reload());

    screen.append(title, body, retry);
    document.getElementById('root')!.replaceChildren(screen);
});
