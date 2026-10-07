import { usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheck, X } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Aviso del resultado de la última acción.
 *
 * Un error no se cierra solo: quien lo provoca necesita tiempo para leer qué
 * pasó y cómo seguir, y cuatro segundos no alcanzan con un lector de pantalla.
 * Los avisos de éxito sí se retiran, pero también se pueden cerrar a mano.
 * El icono acompaña al color para quien no lo distingue.
 */
export function FlashMessage() {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const [visible, setVisible] = useState(true);

    const error = flash?.error;
    const message = error ?? flash?.success;

    useEffect(() => {
        setVisible(true);

        if (error) return;

        const timer = setTimeout(() => setVisible(false), 6000);
        return () => clearTimeout(timer);
    }, [flash, error]);

    if (!visible || !message) return null;

    const Icono = error ? CircleAlert : CircleCheck;

    return (
        <div
            // «alert» interrumpe y se reserva al error; el éxito se anuncia sin cortar.
            role={error ? 'alert' : 'status'}
            className={`animate-in fade-in slide-in-from-top-2 fixed top-4 right-4 left-4 z-50 bg-card flex items-start gap-3 rounded-xl border p-4 text-sm font-medium shadow-lg duration-200 sm:left-auto sm:max-w-md ${
                error ? 'border-red-300' : 'border-green-300'
            }`}
        >
            <Icono className={`mt-0.5 size-5 shrink-0 ${error ? 'text-red-600' : 'text-green-600'}`} aria-hidden="true" />
            <p className="text-foreground flex-1 leading-snug">{message}</p>
            <button
                type="button"
                onClick={() => setVisible(false)}
                aria-label="Cerrar aviso"
                className="text-muted-foreground hover:text-foreground -m-1.5 flex size-8 shrink-0 items-center justify-center rounded-md"
            >
                <X className="size-4" aria-hidden="true" />
            </button>
        </div>
    );
}
