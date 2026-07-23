import { createTheme } from '@mui/material/styles';

/**
 * The ZenonERP MUI theme — THE platform token contract (CLAUDE.md §7). It supersedes the
 * Phase 4–8 CSS-variable/@theme token set: addons receive it at mount time through the shared
 * @mui/material/@emotion singletons and the host ThemeProvider, and must never create their
 * own theme. Palette/shape/density semantic changes are platform-major.
 *
 * Deliberately Material defaults (user decision 2026-07-23 — "redo the design per MUI best
 * practices", not a port of the old ReUI look):
 *
 * - `colorSchemes: { light, dark }` = MUI's default palettes. A future brand color is a
 *   one-line `palette.primary` override per scheme.
 * - `cssVariables.colorSchemeSelector: 'class'` emits dark-scheme variables under the `.dark`
 *   class on <html> — the exact class the pre-hydration script in app.blade.php and
 *   store.ts's setTheme already toggle. Neither knows MUI exists; do not adopt
 *   useColorScheme/InitColorSchemeScript.
 * - Typography stays MUI's Roboto-first stack WITHOUT shipping the Roboto webfont — it falls
 *   back to Helvetica/Arial/system. Adding @fontsource/roboto later is a deliberate decision,
 *   not a default.
 * - ERP density: small inputs by default (data-dense forms); everything else Material default.
 */
export const theme = createTheme({
    cssVariables: { colorSchemeSelector: 'class' },
    colorSchemes: { light: true, dark: true },
    components: {
        MuiTextField: { defaultProps: { size: 'small' } },
        MuiFormControl: { defaultProps: { size: 'small' } },
    },
});
