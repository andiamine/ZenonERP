import { Input as BaseInput } from '@base-ui/react/input';
import type { ComponentProps } from 'react';
import { cn } from './cn';

function Input({
    className,
    ...props
}: Omit<ComponentProps<typeof BaseInput>, 'className'> & { className?: string }) {
    return (
        <BaseInput
            data-slot="input"
            className={cn(
                'flex h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50 aria-invalid:border-destructive',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
