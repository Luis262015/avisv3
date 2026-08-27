import AppLogoIcon from './app-logo-icon';

/**
 * Marca de la aplicación en la cabecera del menú.
 *
 * El nombre sale de `APP_NAME`, no escrito a mano: así el menú, el título de la
 * pestaña y el remitente de los correos no pueden discrepar. Antes decía
 * «Laravel Starter Kit», que era lo que traía la plantilla.
 */
export default function AppLogo() {
    const appName = import.meta.env.VITE_APP_NAME || 'AvisV3';

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 shrink-0 items-center justify-center rounded-md">
                <AppLogoIcon className="size-5" aria-hidden="true" />
            </div>
            {/* `min-w-0` para que el truncado funcione: sin él un nombre largo
                estira el contenedor en vez de recortarse. */}
            <div className="ml-1 grid min-w-0 flex-1 text-left">
                {/* `translate="no"`: es un nombre propio, no debe traducirse. */}
                <span className="truncate text-sm leading-tight font-semibold" translate="no">
                    {appName}
                </span>
                <span className="text-sidebar-foreground/60 truncate text-xs leading-tight">
                    Punto de venta
                </span>
            </div>
        </>
    );
}
