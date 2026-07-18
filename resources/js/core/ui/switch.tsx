import { Switch as SwitchPrimitive } from '@base-ui/react/switch';
import type { ComponentProps } from 'react';
import { cn } from './cn';

function Switch({
    className,
    ...props
}: Omit<ComponentProps<typeof SwitchPrimitive.Root>, 'className'> & { className?: string }) {
    return (
        <SwitchPrimitive.Root
            data-slot="switch"
            className={cn(
                'peer inline-flex h-5 w-9 shrink-0 items-center rounded-full border border-transparent bg-input shadow-xs transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-checked:bg-primary',
                className,
            )}
            {...props}
        >
            <SwitchPrimitive.Thumb
                data-slot="switch-thumb"
                className="pointer-events-none block size-4 translate-x-0 rounded-full bg-background shadow-lg ring-0 transition-transform data-checked:translate-x-4"
            />
        </SwitchPrimitive.Root>
    );
}

export { Switch };
