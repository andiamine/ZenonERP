import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';

export default tseslint.config(
    {
        ignores: [
            'public/**',
            'vendor/**',
            'node_modules/**',
            'storage/**',
            'resources/js/generated/**',
            // Third-party addon prebuilt remotes (Module Federation build output) are committed
            // artifacts, not authored source — like resources/js/generated above, never linted.
            'modules/thirdparty/*/dist/**',
            '.claude/**',
            '.remember/**',
            // @zenon/module-kit is a standalone Node package (not part of the SPA build,
            // not an npm workspace member) — Node-globals CLI code, own toolchain/lint
            // story if/when it grows one (Task 4, Phase 7).
            'packages/**',
        ],
    },
    js.configs.recommended,
    // Recommended (non-type-checked) only: type-aware linting needs the TS compiler API,
    // which typescript-eslint does not yet support beyond TS 6.0 (see package.json pin).
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx}', 'modules/**/resources/js/**/*.{ts,tsx}'],
        plugins: { 'react-hooks': reactHooks },
        rules: { ...reactHooks.configs.recommended.rules },
        languageOptions: { globals: { ...globals.browser } },
    },
    {
        // CLAUDE.md §2 boundary, CI-fatal: module frontends import @zenon/core, @mui/material,
        // @mui/icons-material subpaths and their own files — never other modules, the Emotion
        // engine, or MUI internals.
        files: ['modules/**/resources/js/**/*.{ts,tsx}'],
        rules: {
            'no-restricted-imports': [
                'error',
                {
                    patterns: [
                        {
                            group: ['@modules/*'],
                            message: 'Cross-module import — depend on @zenon/core or your own files (CLAUDE.md §2).',
                        },
                        {
                            group: ['@generated/*'],
                            message: 'The generated module registry is shell-internal.',
                        },
                        {
                            group: ['@emotion/react', '@emotion/react/*', '@emotion/styled', '@emotion/styled/*', '@mui/system', '@mui/system/*', '@mui/styled-engine', '@mui/styled-engine/*'],
                            message: 'Style via @mui/material (sx/styled/theme re-exports) — the Emotion engine is host-internal (CLAUDE.md §2).',
                        },
                        {
                            // regex, not group: gitignore-style groups can't re-include children
                            // of an excluded parent, and only the BARE barrel is banned here.
                            regex: '^@mui/icons-material$',
                            message: 'Import icons by subpath (@mui/icons-material/IconName) — the root barrel pulls 2,600+ modules.',
                        },
                    ],
                },
            ],
        },
    },
    {
        // Third-party addons only: the @mui/material ROOT BARREL is the addon contract —
        // subpath share entries only exist for paths the HOST value-imports, so a subpath
        // import here would bundle a private MUI copy or throw MISSING at mount
        // (module-kit README, CLAUDE.md §7).
        files: ['modules/thirdparty/*/resources/js/**/*.{ts,tsx}'],
        rules: {
            'no-restricted-imports': [
                'error',
                {
                    patterns: [
                        {
                            group: ['@mui/material/*'],
                            message: 'Addons import from the @mui/material root barrel only — subpath share entries are not part of the platform contract (module-kit README).',
                        },
                    ],
                },
            ],
        },
    },
    {
        // The Emotion/MUI-internals ban also applies to shell code — only @zenon/core
        // (theme.ts, the ui/ composites) touches the engine surface.
        files: ['resources/js/**/*.{ts,tsx}'],
        ignores: ['resources/js/core/**'],
        rules: {
            'no-restricted-imports': [
                'error',
                {
                    patterns: [
                        {
                            group: ['@emotion/react', '@emotion/react/*', '@emotion/styled', '@emotion/styled/*', '@mui/system', '@mui/system/*', '@mui/styled-engine', '@mui/styled-engine/*'],
                            message: 'Style via @mui/material (sx/styled/theme re-exports) — the Emotion engine is host-internal (CLAUDE.md §2).',
                        },
                        {
                            // regex, not group: gitignore-style groups can't re-include children
                            // of an excluded parent, and only the BARE barrel is banned here.
                            regex: '^@mui/icons-material$',
                            message: 'Import icons by subpath (@mui/icons-material/IconName) — the root barrel pulls 2,600+ modules.',
                        },
                    ],
                },
            ],
        },
    },
    {
        // The shared core barrel's re-export list must stay free of trailing commas: the
        // @module-federation/vite 1.18.2 remote export scanner (used to build the
        // `import: false` re-export proxy for shared modules — see packages/module-kit's
        // README "Build-time @zenon/core") breaks on a trailing comma in this file.
        files: ['resources/js/core/ui/index.ts'],
        rules: { 'comma-dangle': ['error', 'never'] },
    },
);
