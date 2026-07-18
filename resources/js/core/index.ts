/**
 * Type-only barrel for the `@zenon/core` package alias (which points at this directory).
 * It exists solely so bare `import type { ZenonModule } from '@zenon/core'` resolves —
 * the shape modules import to type their default export.
 *
 * RUNTIME imports deliberately stay on subpaths (`@zenon/core/ui`, `@zenon/core/apiClient`,
 * `@zenon/core/permissions`, …): re-exporting runtime modules from one barrel would collapse
 * them into a single chunk and defeat the per-area code-splitting. `export type *` is erased
 * under verbatimModuleSyntax, so this file emits no JavaScript.
 */
export type * from './moduleTypes';
