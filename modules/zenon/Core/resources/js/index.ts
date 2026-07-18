import type { ZenonModule } from '@zenon/core/moduleTypes';
import { nav } from './nav';
import { createModuleRoutes } from './routes';

/**
 * The `zenon/core` module frontend (CLAUDE.md §7/§9.1): kernel admin pages (settings, users,
 * roles, teams, companies). No dashboard widgets by deliberate choice — the kernel is admin
 * surface, not a dashboard contributor (the Audit module ships the Phase 5 dogfood widget).
 *
 * Locale loaders return the parsed JSON object directly (`m.default`) so the resource bundle
 * i18next registers is the flat `{ nav, … }` map regardless of Vite's JSON named-export mode.
 */
const core: ZenonModule = {
    id: 'core',
    routes: createModuleRoutes,
    nav,
    widgets: [],
    locales: {
        en: () => import('./locales/en.json').then((m) => m.default),
        fr: () => import('./locales/fr.json').then((m) => m.default),
    },
};

export default core;
