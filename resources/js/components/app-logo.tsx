import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

/**
 * Marca en la cabecera del menú: el monograma, «AVIS» y debajo la empresa en
 * la que se está trabajando, que es lo que distingue una pestaña de otra
 * cuando alguien lleva varias.
 */
export default function AppLogo() {
    const { tenant } = usePage<SharedData>().props;

    return (
        <>
            <span className="flex size-9 shrink-0 items-center justify-center">
                <AppLogoIcon className="size-9 text-white" aria-hidden="true" />
            </span>
            {/* `min-w-0` para que el truncado funcione: sin él un nombre largo
                estira el contenedor en vez de recortarse. */}
            <div className="ml-0.5 grid min-w-0 flex-1 text-left">
                {/* `translate="no"`: es un nombre propio, no debe traducirse. */}
                <span className="font-display truncate text-base leading-tight font-bold text-white" translate="no">
                    AVIS
                </span>
                <span className="text-sidebar-foreground/80 truncate text-xs leading-tight">{tenant?.name ?? 'Inventarios y facturación'}</span>
            </div>
        </>
    );
}
