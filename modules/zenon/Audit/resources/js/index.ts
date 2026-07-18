import type { ZenonModule } from '@zenon/core/moduleTypes';
import { nav } from './nav';
import { createModuleRoutes } from './routes';

/**
 * The `zenon/audit` module frontend (CLAUDE.md §9.2): activity log viewer + the Phase 5
 * dashboard dogfood widget. The widget id `'audit.recent'` is LOAD-BEARING — the dashboard
 * host resolves the i18n namespace via `widget.id.split('.')[0]`
 * (resources/js/routes/dashboard.tsx:10), so the id's prefix must equal this module's own id
 * ('audit') or `t(widget.titleKey)` would look up the wrong namespace.
 *
 * Locale loaders return the parsed JSON object directly (`m.default`) so the resource bundle
 * i18next registers is the flat `{ nav, … }` map regardless of Vite's JSON named-export mode
 * — resources/js/core/i18n.ts:151-164, `addResourceBundle(lang, module.id, await load(), …)`
 * at line 162 hands the loader's resolved value straight through.
 */
const audit: ZenonModule = {
    id: 'audit',
    routes: createModuleRoutes,
    nav,
    widgets: [
        {
            id: 'audit.recent',
            titleKey: 'widgets.recent.title',
            permission: 'audit.activities.view',
            component: () => import('./widgets/recent-activity'),
        },
    ],
    locales: {
        en: () => import('./locales/en.json').then((m) => m.default),
        fr: () => import('./locales/fr.json').then((m) => m.default),
    },
};

export default audit;
