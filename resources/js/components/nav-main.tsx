import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { type NavGroup, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

const CLAVE_ALMACEN = 'sidebar-grupos';

/**
 * ¿Esta ruta corresponde a la página actual?
 *
 * No vale `startsWith` a secas: `/admin/sales` es prefijo de
 * `/admin/sales-orders` y de `/admin/sales-reports`, así que entrar en los
 * reportes encendía también «Ventas». Solo cuenta la coincidencia exacta o un
 * subnivel de verdad, es decir, cuando lo que sigue es `/` o el inicio de la
 * query.
 */
function corresponde(url: string, actual: string): boolean {
    return actual === url || actual.startsWith(`${url}/`) || actual.startsWith(`${url}?`);
}

/** Lo que el usuario dejó abierto o cerrado la última vez. */
function leerPreferencias(): Record<string, boolean> {
    if (typeof window === 'undefined') return {};

    try {
        return JSON.parse(window.localStorage.getItem(CLAVE_ALMACEN) ?? '{}');
    } catch {
        // Un valor corrupto no debe dejar el menú sin pintar.
        return {};
    }
}

/**
 * El menú principal, con los grupos plegables.
 *
 * Son nueve grupos y medio centenar de entradas. Desplegados todos a la vez, la
 * lista mide varias pantallas y llegar a «Configuración» obliga a recorrer
 * Recursos Humanos entero; además nada distingue lo que se usa cada día de lo
 * que se abre una vez al mes. Plegar por grupos convierte esa lista en un índice
 * de nueve líneas.
 */
export function NavMain({ items = [] }: { items: NavGroup[] }) {
    const page = usePage();
    const { state, isMobile } = useSidebar();
    const [preferencias, setPreferencias] = useState<Record<string, boolean>>(leerPreferencias);

    /**
     * Gana la ruta más específica.
     *
     * `/admin/inventory/stock` casa con su propia entrada y también con
     * `/admin/inventory`; sin desempatar quedarían las dos encendidas, y
     * `aria-current` dejaría de señalar una sola página.
     */
    const rutaActiva = useMemo(() => {
        const candidatas = items
            .flatMap((grupo) => grupo.items)
            .map((item) => item.url)
            .filter((url) => corresponde(url, page.url));

        return candidatas.sort((a, b) => b.length - a.length)[0] ?? null;
    }, [items, page.url]);

    const recordar = useCallback((titulo: string, abierto: boolean) => {
        setPreferencias((previo) => {
            const siguiente = { ...previo, [titulo]: abierto };

            try {
                window.localStorage.setItem(CLAVE_ALMACEN, JSON.stringify(siguiente));
            } catch {
                // Modo privado o almacenamiento lleno: se pierde la preferencia,
                // pero el menú sigue funcionando.
            }

            return siguiente;
        });
    }, []);

    // Con el menú reducido a iconos no hay títulos de grupo que pulsar, así que
    // plegar dejaría entradas inalcanzables: ahí se muestran todas.
    const plegable = isMobile || state === 'expanded';

    return (
        // El menú no estaba dentro de ningún landmark: shadcn lo monta con divs,
        // así que no había forma de saltar la navegación con lector de pantalla.
        <nav aria-label="Navegación principal">
            {items.map((group) => {
                const contieneLaPagina = group.items.some((item) => item.url === rutaActiva);

                // Donde está la página actual se abre siempre: cerrarlo escondería
                // el único elemento que dice dónde estás.
                const abierto = contieneLaPagina || (preferencias[group.title] ?? group.defaultOpen ?? false);

                const entradas = (
                    <SidebarMenu>
                        {group.items.map((item) => (
                            <EntradaMenu key={item.title} item={item} activo={item.url === rutaActiva} />
                        ))}
                    </SidebarMenu>
                );

                if (!plegable) {
                    return (
                        <SidebarGroup key={group.title} className="px-2 py-0">
                            {entradas}
                        </SidebarGroup>
                    );
                }

                return (
                    <Collapsible
                        key={group.title}
                        open={abierto}
                        onOpenChange={(valor) => recordar(group.title, valor)}
                        // El grupo de la página actual no se puede cerrar; anunciarlo
                        // como pulsable sería mentir.
                        disabled={contieneLaPagina}
                        className="group/grupo"
                    >
                        <SidebarGroup className="px-2 py-0">
                            {/* Micro-etiqueta: más pequeña, en versales y con más aire
                                arriba que abajo, para que se lea como el rótulo del
                                grupo y no como una entrada más. Antes pesaba casi lo
                                mismo que los enlaces y la lista salía plana. */}
                            <SidebarGroupLabel asChild className="h-auto pt-4 pb-1">
                                <CollapsibleTrigger className="text-sidebar-foreground/50 hover:text-sidebar-foreground/80 focus-visible:ring-sidebar-ring w-full cursor-pointer rounded-md text-[11px] font-semibold tracking-wider uppercase focus-visible:ring-2 focus-visible:outline-none disabled:cursor-default">
                                    <span className="truncate">{group.title}</span>
                                    <ChevronRight
                                        aria-hidden="true"
                                        className="ml-auto size-3.5 opacity-60 group-data-[state=open]/grupo:rotate-90 motion-safe:transition-transform motion-safe:duration-200 group-disabled/grupo:opacity-0"
                                    />
                                </CollapsibleTrigger>
                            </SidebarGroupLabel>

                            <CollapsibleContent>{entradas}</CollapsibleContent>
                        </SidebarGroup>
                    </Collapsible>
                );
            })}
        </nav>
    );
}

/**
 * Una entrada del menú.
 *
 * El activo no puede distinguirse solo por el fondo: `hover:bg-sidebar-accent` y
 * `data-[active=true]:bg-sidebar-accent` son el mismo color, así que al pasar el
 * ratón por encima de otra entrada se perdía cuál era la página actual. De ahí la
 * barra lateral, el icono teñido y la negrita, que no dependen del puntero.
 */
function EntradaMenu({ item, activo }: { item: NavItem; activo: boolean }) {
    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={activo}
                // Con el menú colapsado solo se ven los iconos; sin esto queda una
                // columna de dibujos sin nombre.
                tooltip={item.title}
                // `h-9` y no `h-8`: esto se usa de pie y a veces con el dedo, y un
                // objetivo de 32 px se falla. Los iconos inactivos van atenuados
                // para que el activo destaque sin gritar.
                className="relative h-9 [&>svg]:opacity-70 data-[active=true]:font-semibold data-[active=true]:before:bg-sidebar-primary data-[active=true]:before:absolute data-[active=true]:before:top-1.5 data-[active=true]:before:bottom-1.5 data-[active=true]:before:left-0 data-[active=true]:before:w-0.5 data-[active=true]:before:rounded-full data-[active=true]:[&>svg]:text-sidebar-primary data-[active=true]:[&>svg]:opacity-100 group-data-[collapsible=icon]:data-[active=true]:before:hidden"
            >
                <Link href={item.url} prefetch aria-current={activo ? 'page' : undefined}>
                    {/* Decorativo: la etiqueta de al lado ya lo nombra. */}
                    {item.icon && <item.icon aria-hidden="true" />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}
