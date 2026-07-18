import type { ZenonModule } from '@zenon/core/moduleTypes';
import { nav } from './nav';
import { createModuleRoutes } from './routes';

/**
 * The `zenon/sequence` module frontend (CLAUDE.md §9.2): numbering-mask administration. No
 * dashboard widgets by deliberate choice (anti-gold-plating; the Audit module ships the
 * Phase 5 dogfood widget).
 *
 * Locale loaders return the parsed JSON object directly (`m.default`) so the resource bundle
 * i18next registers is the flat `{ nav, … }` map regardless of Vite's JSON named-export mode
 * — resources/js/core/i18n.ts:151-164, `addResourceBundle(lang, module.id, await load(), …)`
 * at line 162 hands the loader's resolved value straight through.
 */
const sequence: ZenonModule = {
    id: 'sequence',
    routes: createModuleRoutes,
    nav,
    widgets: [],
    locales: {
        en: () => import('./locales/en.json').then((m) => m.default),
        fr: () => import('./locales/fr.json').then((m) => m.default),
    },
};

export default sequence;
