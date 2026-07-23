/**
 * @zenon/core/ui — Zenon-specific composites ONLY (MUI migration, 2026-07). Primitives
 * come straight from @mui/material, which modules and addons import directly (CLAUDE.md
 * §2); this barrel holds the pieces MUI does not ship: the TanStack-powered DataTable,
 * the ApiError-aware Field/ConfirmDialog/ApiErrorAlert, and the string-keyed icon
 * registry behind the ZenonModule nav contract.
 *
 * NOTE: no `export { ... }` block below may have a trailing comma before `}`.
 * @module-federation/vite 1.18.2's export scanner splits the block on `,`, and a
 * trailing comma yields an empty specifier that trips scanState.complete=false —
 * which silently discards EVERY named export of this barrel when an addon remote
 * generates its `import: false` share proxy (MISSING_EXPORT at addon build time).
 * Keep trailing commas out of these blocks until the upstream parser is fixed.
 */
export { ApiErrorAlert } from './api-error-alert';
export { ConfirmDialog } from './confirm-dialog';
export { DataTable, type DataTableProps } from './data-table';
export { Field, type FieldProps } from './field';
export { icons, NavIcon, type NavIconProps } from './icons';
