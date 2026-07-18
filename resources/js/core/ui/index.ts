/**
 * @zenon/core/ui — the ZenonERP design system (ReUI-derived, Base UI base;
 * CLAUDE.md §2). Modules and addons import UI ONLY from here — never from
 * @base-ui/react directly (ESLint-enforced). Phase 5 grows the kit.
 */
export { Alert, AlertDescription, AlertTitle } from './alert';
export { Badge, badgeVariants } from './badge';
export { Button, buttonVariants } from './button';
export { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from './card';
export { Checkbox } from './checkbox';
export { cn } from './cn';
export { DataTable, type DataTableProps, type PageMeta } from './data-table';
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
    DialogTrigger,
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
