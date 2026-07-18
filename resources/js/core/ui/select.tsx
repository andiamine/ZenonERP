import { Select as SelectPrimitive } from '@base-ui/react/select';
import { Check, ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from './cn';

// Adapted from ReUI's select (registry/bases/base/ui/select.tsx): Base UI Select parts,
// styled trigger/popup/item; IconPlaceholder swapped for lucide-react (allowed inside
// core/ui only — CLAUDE.md §2).
const Select = SelectPrimitive.Root;

function SelectGroup({ ...props }: ComponentProps<typeof SelectPrimitive.Group>) {
    return <SelectPrimitive.Group data-slot="select-group" {...props} />;
}

function SelectValue({ ...props }: ComponentProps<typeof SelectPrimitive.Value>) {
    return <SelectPrimitive.Value data-slot="select-value" {...props} />;
}

function SelectTrigger({
    className,
    size = 'default',
    children,
    ...props
}: Omit<ComponentProps<typeof SelectPrimitive.Trigger>, 'className'> & {
    className?: string;
    size?: 'sm' | 'default';
}) {
    return (
        <SelectPrimitive.Trigger
            data-slot="select-trigger"
            data-size={size}
            className={cn(
                'flex h-9 w-fit items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-1 text-sm whitespace-nowrap shadow-xs outline-none data-[size=sm]:h-8 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
                className,
            )}
            {...props}
        >
            {children}
            <SelectPrimitive.Icon className="text-muted-foreground">
                <ChevronDown className="size-4" />
            </SelectPrimitive.Icon>
        </SelectPrimitive.Trigger>
    );
}

function SelectContent({
    className,
    children,
    side = 'bottom',
    sideOffset = 4,
    align = 'center',
    alignOffset = 0,
    alignItemWithTrigger = true,
    ...props
}: Omit<ComponentProps<typeof SelectPrimitive.Popup>, 'className'> &
    Pick<ComponentProps<typeof SelectPrimitive.Positioner>, 'align' | 'alignOffset' | 'side' | 'sideOffset' | 'alignItemWithTrigger'> & {
        className?: string;
    }) {
    return (
        <SelectPrimitive.Portal>
            <SelectPrimitive.Positioner
                side={side}
                sideOffset={sideOffset}
                align={align}
                alignOffset={alignOffset}
                alignItemWithTrigger={alignItemWithTrigger}
                className="isolate z-50"
            >
                <SelectPrimitive.Popup
                    data-slot="select-content"
                    className={cn(
                        'relative z-50 max-h-(--available-height) w-(--anchor-width) min-w-32 overflow-x-hidden overflow-y-auto rounded-md border border-border bg-popover text-popover-foreground shadow-md',
                        className,
                    )}
                    {...props}
                >
                    <SelectPrimitive.List className="p-1">{children}</SelectPrimitive.List>
                </SelectPrimitive.Popup>
            </SelectPrimitive.Positioner>
        </SelectPrimitive.Portal>
    );
}

function SelectLabel({
    className,
    ...props
}: Omit<ComponentProps<typeof SelectPrimitive.GroupLabel>, 'className'> & { className?: string }) {
    return (
        <SelectPrimitive.GroupLabel
            data-slot="select-label"
            className={cn('px-2 py-1.5 text-xs text-muted-foreground', className)}
            {...props}
        />
    );
}

function SelectItem({
    className,
    children,
    ...props
}: Omit<ComponentProps<typeof SelectPrimitive.Item>, 'className'> & { className?: string }) {
    return (
        <SelectPrimitive.Item
            data-slot="select-item"
            className={cn(
                'relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground data-disabled:pointer-events-none data-disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
            <SelectPrimitive.ItemIndicator className="absolute right-2 flex items-center">
                <Check className="size-4" />
            </SelectPrimitive.ItemIndicator>
        </SelectPrimitive.Item>
    );
}

function SelectSeparator({
    className,
    ...props
}: Omit<ComponentProps<typeof SelectPrimitive.Separator>, 'className'> & { className?: string }) {
    return (
        <SelectPrimitive.Separator
            data-slot="select-separator"
            className={cn('pointer-events-none -mx-1 my-1 h-px bg-border', className)}
            {...props}
        />
    );
}

export { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectSeparator, SelectTrigger, SelectValue };
