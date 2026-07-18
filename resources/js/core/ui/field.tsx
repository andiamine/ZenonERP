import type { ComponentProps, ReactNode } from 'react';
import { cn } from './cn';
import { Label } from './label';

// Adapted from ReUI's field family (registry/bases/base/ui/field.tsx: FieldSet/FieldLegend/
// FieldGroup/Field/FieldContent/FieldLabel/FieldDescription/FieldError/FieldSeparator) —
// collapsed to the single wrapper the task brief asks for: `<Field label error children>`.
// FieldError's array-flattening idea is kept (ApiError.errors[key] is string | string[]).
export interface FieldProps extends Omit<ComponentProps<'div'>, 'children'> {
    label?: ReactNode;
    htmlFor?: string;
    description?: ReactNode;
    error?: string | string[];
    children: ReactNode;
}

function Field({ label, htmlFor, description, error, className, children, ...props }: FieldProps) {
    const errors = error === undefined ? [] : Array.isArray(error) ? error : [error];

    return (
        <div data-slot="field" className={cn('flex w-full flex-col gap-1.5', className)} {...props}>
            {label && <Label htmlFor={htmlFor}>{label}</Label>}
            {children}
            {errors.length > 0 ? (
                <div data-slot="field-error" role="alert" className="text-sm font-normal text-destructive">
                    {errors.length === 1 ? (
                        errors[0]
                    ) : (
                        <ul className="ml-4 list-disc">
                            {errors.map((message, index) => (
                                <li key={index}>{message}</li>
                            ))}
                        </ul>
                    )}
                </div>
            ) : (
                description && (
                    <p data-slot="field-description" className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )
            )}
        </div>
    );
}

export { Field };
