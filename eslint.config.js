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
            '.claude/**',
            '.remember/**',
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
        // CLAUDE.md §2 boundary, CI-fatal: module frontends import only @zenon/core + their own files.
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
                            group: ['@base-ui/react', '@base-ui/react/*'],
                            message: 'UI components come only from @zenon/core/ui (CLAUDE.md §2).',
                        },
                        {
                            group: ['lucide-react', 'lucide-react/*'],
                            message: 'Icons come only from @zenon/core/ui (NavIcon/icons registry) (CLAUDE.md §2).',
                        },
                    ],
                },
            ],
        },
    },
    {
        // The Base UI ban also applies to shell code — only the design-system kit touches it.
        files: ['resources/js/**/*.{ts,tsx}'],
        ignores: ['resources/js/core/ui/**'],
        rules: {
            'no-restricted-imports': [
                'error',
                {
                    patterns: [
                        {
                            group: ['@base-ui/react', '@base-ui/react/*'],
                            message: 'Use @zenon/core/ui (CLAUDE.md §2).',
                        },
                    ],
                },
            ],
        },
    },
);
