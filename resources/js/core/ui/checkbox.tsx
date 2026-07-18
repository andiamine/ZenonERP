import { Checkbox as CheckboxPrimitive } from '@base-ui/react/checkbox';
import { Check } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from './cn';

function Checkbox({
    className,
    ...props
}: Omit<ComponentProps<typeof CheckboxPrimitive.Root>, 'className'> & { className?: string }) {
    return (
        <CheckboxPrimitive.Root
            data-slot="checkbox"
            className={cn(
                'peer flex size-4 shrink-0 items-center justify-center rounded-sm border border-input bg-background text-primary-foreground shadow-xs outline-none transition-shadow focus-visible:ring-2 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-checked:border-primary data-checked:bg-primary data-indeterminate:border-primary data-indeterminate:bg-primary',
                className,
            )}
            {...props}
        >
            <CheckboxPrimitive.Indicator data-slot="checkbox-indicator" className="flex items-center justify-center text-current">
                <Check className="size-3.5" />
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
}

export { Checkbox };
