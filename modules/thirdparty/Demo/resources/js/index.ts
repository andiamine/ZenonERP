import type { ZenonModule } from '@zenon/core/moduleTypes';
import { nav } from './nav';
import { createModuleRoutes } from './routes';

/**
 * The Demo addon frontend (Phase 7 extension proof) — a REAL `ZenonModule` shipped as a
 * prebuilt Module Federation remote (dist/remoteEntry.js), not compiled into the host build.
 * Consumes the addon-computed fields the Demo PHP hook (AddCompanyInsights) appends to the
 * Core `GET /api/v1/core/companies` response `extra` map, proving a runtime remote reaches
 * both the API and the design system.
 *
 * The addon ships NO CSS (module-kit 2.0): all styling arrives at mount time through the
 * host's MUI theme via the shared @mui/material/@emotion singletons.
 *
 * Widget id `'demo.companies'` — the prefix MUST equal this module's id ('demo'): the
 * dashboard host resolves the i18n namespace via `widget.id.split('.')[0]`. Demo ships zero
 * permissions (decision D9), so neither the nav item, the route, nor the widget is gated.
 *
 * Locale loaders return the parsed JSON object directly (`m.default`), mirroring Audit — the
 * value i18next's `addResourceBundle` receives is the flat `{ nav, page, ... }` map.
 */
const demo: ZenonModule = {
    id: 'demo',
    routes: createModuleRoutes,
    nav,
    widgets: [
        {
            id: 'demo.companies',
            titleKey: 'widgets.companies.title',
            component: () => import('./widgets/company-insights'),
        },
    ],
    locales: {
        en: () => import('./locales/en.json').then((m) => m.default),
        fr: () => import('./locales/fr.json').then((m) => m.default),
    },
};

export default demo;
