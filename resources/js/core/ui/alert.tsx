import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from './cn';

// Phase 5 token expansion: info/success/warning added on the new status tokens (ReUI's
// real variant names — registry-reui/bases/base/reui/alert.tsx), following the same
// border/{color}/50 + bg-card + text-{color} pattern already established by `destructive`.
const alertVariants = cva(
    'relative grid w-full grid-cols-[0_1fr] items-start gap-y-0.5 rounded-lg border border-border px-4 py-3 text-sm has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] has-[>svg]:gap-x-3 [&>svg]:size-4 [&>svg]:translate-y-0.5',
    {
        variants: {
            variant: {
                default: 'bg-card text-card-foreground',
                destructive: 'border-destructive/50 bg-card text-destructive [&>svg]:text-destructive',
                info: 'border-info/50 bg-card text-info [&>svg]:text-info',
                success: 'border-success/50 bg-card text-success [&>svg]:text-success',
                warning: 'border-warning/50 bg-card text-warning [&>svg]:text-warning',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

function Alert({ className, variant, ...props }: ComponentProps<'div'> & VariantProps<typeof alertVariants>) {
    return <div data-slot="alert" role="alert" className={cn(alertVariants({ variant, className }))} {...props} />;
}

function AlertTitle({ className, ...props }: ComponentProps<'div'>) {
    return <div data-slot="alert-title" className={cn('col-start-2 min-h-4 font-medium tracking-tight', className)} {...props} />;
}

function AlertDescription({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="alert-description"
            className={cn('col-start-2 grid gap-1 text-sm text-muted-foreground [&_p]:leading-relaxed', className)}
            {...props}
        />
    );
}

export { Alert, AlertTitle, AlertDescription };
