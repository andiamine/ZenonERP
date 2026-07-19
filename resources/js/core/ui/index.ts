/**
 * @zenon/core/ui — the ZenonERP design system (ReUI-derived, Base UI base;
 * CLAUDE.md §2). Modules and addons import UI ONLY from here — never from
 * @base-ui/react directly (ESLint-enforced). Phase 5 grows the kit.
 *
 * NOTE: no `export { ... }` block below may have a trailing comma before `}`.
 * @module-federation/vite 1.18.2's export scanner splits the block on `,`, and a
 * trailing comma yields an empty specifier that trips scanState.complete=false —
 * which silently discards EVERY named export of this barrel when an addon remote
 * generates its `import: false` share proxy (MISSING_EXPORT at addon build time).
 * Keep trailing commas out of these blocks until the upstream parser is fixed.
 */
export { Alert, AlertDescription, AlertTitle } from './alert';
export { Badge, badgeVariants } from './badge';
export { Button, buttonVariants } from './button';
export { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from './card';
export { Checkbox } from './checkbox';
export { cn } from './cn';
export { DataTable, type DataTableProps } from './data-table';
export {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
    DialogTrigger
} from './dialog';
export { Field, type FieldProps } from './field';
export { icons, NavIcon, type NavIconProps } from './icons';
export { Input } from './input';
export { Label } from './label';
export { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectSeparator, SelectTrigger, SelectValue } from './select';
export { Separator } from './separator';
export { Skeleton } from './skeleton';
export { Spinner } from './spinner';
export { Switch } from './switch';
export { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from './table';
