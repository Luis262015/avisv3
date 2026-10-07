import { cn } from '@/lib/utils';
import AppLogoIcon from './app-logo-icon';

interface BrandProps {
    /** Muestra «Inventarios y facturación» bajo el nombre. */
    descriptor?: boolean;
    className?: string;
    iconClassName?: string;
}

/**
 * Logotipo completo: monograma y nombre.
 *
 * El color del nombre y de la «A» lo da el texto del contenedor, así que
 * sobre fondo marino basta con ponerle `text-white`.
 */
export function Brand({ descriptor = false, className, iconClassName }: BrandProps) {
    return (
        <span className={cn('text-brand-navy inline-flex items-center gap-2.5 dark:text-white', className)}>
            <AppLogoIcon className={cn('size-9 shrink-0', iconClassName)} aria-hidden="true" />
            <span className="grid leading-none">
                {/* `translate="no"`: es un nombre propio, no debe traducirse. */}
                <span className="font-display text-[1.375rem] font-bold tracking-[-0.01em]" translate="no">
                    AVIS
                </span>
                {descriptor && <span className="mt-1 text-[0.6875rem] font-medium opacity-75">Inventarios y facturación</span>}
            </span>
        </span>
    );
}
