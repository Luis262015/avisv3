import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { cloneElement, isValidElement, useId, type ReactElement, type ReactNode } from 'react';

interface FieldProps {
    label: string;
    /** Ayuda permanente bajo el campo: formato esperado, para qué se usa. */
    hint?: ReactNode;
    error?: string;
    optional?: boolean;
    className?: string;
    /** Un solo control. Recibe `id`, `aria-invalid` y `aria-describedby`. */
    children: ReactElement<Record<string, unknown>>;
}

/**
 * Etiqueta, control, ayuda y error, ya enlazados entre sí.
 *
 * Quien escribe un formulario no tiene que acordarse de emparejar `htmlFor`
 * con `id` ni de apuntar el error con `aria-describedby`: un lector de
 * pantalla lee la etiqueta, la ayuda y el error al entrar al campo.
 */
export function Field({ label, hint, error, optional = false, className, children }: FieldProps) {
    const id = useId();
    const hintId = `${id}-ayuda`;
    const errorId = `${id}-error`;

    const describedBy = [hint ? hintId : null, error ? errorId : null].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cn('grid content-start gap-2', className)}>
            <Label htmlFor={id}>
                {label}
                {optional && <span className="text-muted-foreground font-normal"> (opcional)</span>}
            </Label>
            {isValidElement(children) &&
                cloneElement(children, {
                    id,
                    'aria-invalid': error ? true : undefined,
                    'aria-describedby': describedBy,
                })}
            {hint && (
                <p id={hintId} className="text-muted-foreground text-xs leading-relaxed">
                    {hint}
                </p>
            )}
            {error && (
                <p id={errorId} role="alert" className="text-sm font-medium text-red-700">
                    {error}
                </p>
            )}
        </div>
    );
}

/** `<select>` nativo con el mismo aspecto que los campos de texto. */
export function NativeSelect({ className, ...props }: React.ComponentProps<'select'>) {
    return (
        <select
            className={cn(
                'border-input bg-card focus-visible:border-ring focus-visible:ring-ring/35 h-10 w-full rounded-md border px-3 text-base focus-visible:ring-2 focus-visible:outline-hidden aria-invalid:border-red-600 md:text-sm',
                className,
            )}
            {...props}
        />
    );
}

/** `<textarea>` con el mismo aspecto que los campos de texto. */
export function TextArea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(
                'border-input bg-card focus-visible:border-ring focus-visible:ring-ring/35 min-h-24 w-full rounded-md border px-3 py-2 text-base focus-visible:ring-2 focus-visible:outline-hidden md:text-sm',
                className,
            )}
            {...props}
        />
    );
}
