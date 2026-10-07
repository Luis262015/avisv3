import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Eye, EyeOff } from 'lucide-react';
import { forwardRef, useState, type ComponentProps } from 'react';

/**
 * Campo de contraseña que se puede mostrar.
 *
 * Escribir a ciegas una contraseña larga en un teléfono es la causa más común
 * de un acceso fallido. El botón va fuera del orden de escritura —no se
 * envía el formulario con él— y su nombre dice lo que hará.
 */
export const PasswordInput = forwardRef<HTMLInputElement, Omit<ComponentProps<typeof Input>, 'type'>>(({ className, ...props }, ref) => {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <Input ref={ref} type={visible ? 'text' : 'password'} className={cn('pr-11', className)} {...props} />
            <button
                type="button"
                onClick={() => setVisible((v) => !v)}
                aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                aria-pressed={visible}
                className="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-md"
            >
                {visible ? <EyeOff className="size-4" aria-hidden="true" /> : <Eye className="size-4" aria-hidden="true" />}
            </button>
        </div>
    );
});

PasswordInput.displayName = 'PasswordInput';
