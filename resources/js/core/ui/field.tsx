import FormControl from '@mui/material/FormControl';
import FormHelperText from '@mui/material/FormHelperText';
import FormLabel from '@mui/material/FormLabel';
import type { ReactNode } from 'react';
import type { SxProps, Theme } from '@mui/material/styles';

/**
 * Zenon form-row composite: `<Field label error children>` — the one place the
 * `ApiError.errors[key]: string | string[]` contract is flattened for display. Built on
 * MUI's FormControl context, so an OutlinedInput/Select/Checkbox child picks the error
 * state up automatically; simple standalone inputs may use a bare `<TextField
 * error helperText>` instead — Field earns its keep when the error is the API's
 * string-array shape or the control isn't a TextField.
 */
export interface FieldProps {
    label?: ReactNode;
    htmlFor?: string;
    description?: ReactNode;
    error?: string | string[];
    children: ReactNode;
    sx?: SxProps<Theme>;
    className?: string;
}

function Field({ label, htmlFor, description, error, sx, className, children }: FieldProps) {
    const errors = error === undefined ? [] : Array.isArray(error) ? error : [error];

    return (
        <FormControl fullWidth error={errors.length > 0} sx={sx} className={className}>
            {label && <FormLabel htmlFor={htmlFor} sx={{ mb: 0.5, typography: 'body2', fontWeight: 500 }}>{label}</FormLabel>}
            {children}
            {errors.length > 0 ? (
                <FormHelperText role="alert" component="div" sx={{ mx: 0 }}>
                    {errors.length === 1 ? (
                        errors[0]
                    ) : (
                        <ul style={{ margin: 0, paddingInlineStart: '1.25em' }}>
                            {errors.map((message, index) => (
                                <li key={index}>{message}</li>
                            ))}
                        </ul>
                    )}
                </FormHelperText>
            ) : (
                description && <FormHelperText sx={{ mx: 0 }}>{description}</FormHelperText>
            )}
        </FormControl>
    );
}

export { Field };
