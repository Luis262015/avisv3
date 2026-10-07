import AppLogoIcon from '@/components/app-logo-icon';
import { FlashMessage } from '@/components/flash-message';
import { ThemeToggle } from '@/components/theme-toggle';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { Head, Link, usePage } from '@inertiajs/react';
import { Building2, CreditCard, LayoutGrid, LogOut, Settings2, ShieldCheck, Tags, type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

interface PlatformLayoutProps {
    title: string;
    /** Una línea bajo el título: qué se hace en esta pantalla. */
    description?: string;
    /** Acción principal de la pantalla, a la derecha del título. */
    actions?: ReactNode;
    children: ReactNode;
}

const NAV: { title: string; url: string; icon: LucideIcon }[] = [
    { title: 'Resumen', url: '/plataforma', icon: LayoutGrid },
    { title: 'Empresas', url: '/plataforma/empresas', icon: Building2 },
    { title: 'Pagos', url: '/plataforma/pagos', icon: CreditCard },
    { title: 'Planes', url: '/plataforma/planes', icon: Tags },
    { title: 'Ajustes', url: '/plataforma/ajustes', icon: Settings2 },
    { title: 'Administradores', url: '/plataforma/administradores', icon: ShieldCheck },
];

const ID_CONTENIDO = 'contenido-principal';

/**
 * Marco del panel de la plataforma: desde aquí se administra la renta del
 * sistema —empresas, planes y cobros—, no la operación de ninguna empresa.
 */
export default function PlatformLayout({ title, description, actions, children }: PlatformLayoutProps) {
    const { url, props } = usePage<{ platformAdmin: { name: string; email: string } | null }>();
    const ruta = url.split('?')[0];

    const activa = (destino: string) => (destino === '/plataforma' ? ruta === destino : ruta === destino || ruta.startsWith(`${destino}/`));

    return (
        <SidebarProvider>
            <Head title={`${title} · Plataforma`} />

            <a
                href={`#${ID_CONTENIDO}`}
                className="bg-card text-foreground sr-only shadow-lg focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:px-4 focus:py-2 focus:text-sm focus:font-medium"
            >
                Saltar al contenido
            </a>

            <Sidebar collapsible="icon" variant="inset">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link href="/plataforma" aria-label="AVIS Plataforma — ir al resumen">
                                    <span className="flex size-9 shrink-0 items-center justify-center">
                <AppLogoIcon className="size-9 text-white" aria-hidden="true" />
            </span>
                                    <div className="ml-0.5 grid min-w-0 flex-1 text-left">
                                        <span className="font-display truncate text-base leading-tight font-bold text-white" translate="no">
                                            AVIS
                                        </span>
                                        <span className="text-sidebar-primary truncate text-xs leading-tight font-semibold">Plataforma</span>
                                    </div>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>

                <SidebarContent>
                    <nav aria-label="Navegación de la plataforma">
                        <SidebarGroup className="px-2 py-2">
                            <SidebarMenu>
                                {NAV.map((item) => (
                                    <SidebarMenuItem key={item.url}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={activa(item.url)}
                                            tooltip={item.title}
                                            className="data-[active=true]:before:bg-sidebar-primary data-[active=true]:[&>svg]:text-sidebar-primary relative h-10 data-[active=true]:font-semibold data-[active=true]:before:absolute data-[active=true]:before:top-2 data-[active=true]:before:bottom-2 data-[active=true]:before:left-0 data-[active=true]:before:w-[3px] data-[active=true]:before:rounded-full [&>svg]:opacity-80 data-[active=true]:[&>svg]:opacity-100 group-data-[collapsible=icon]:data-[active=true]:before:hidden"
                                        >
                                            <Link href={item.url} prefetch aria-current={activa(item.url) ? 'page' : undefined}>
                                                <item.icon aria-hidden="true" />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroup>
                    </nav>
                </SidebarContent>

                <SidebarFooter>
                    <SidebarMenu>
                        <SidebarMenuItem className="group-data-[collapsible=icon]:hidden px-2 pb-1">
                            <p className="truncate text-sm font-medium text-white">{props.platformAdmin?.name}</p>
                            <p className="text-sidebar-foreground/75 truncate text-xs">{props.platformAdmin?.email}</p>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Cerrar sesión" className="h-10">
                                <Link href="/plataforma/salir" method="post" as="button">
                                    <LogOut aria-hidden="true" />
                                    <span>Cerrar sesión</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
            </Sidebar>

            <SidebarInset id={ID_CONTENIDO} tabIndex={-1} className="focus:outline-none">
                <FlashMessage />

                <header className="flex h-14 shrink-0 items-center gap-2 border-b px-4 md:px-6">
                    <SidebarTrigger className="-ml-2 size-9" aria-label="Mostrar u ocultar el menú" />
                    <span className="text-muted-foreground text-sm">Panel de la plataforma</span>
                    <div className="ml-auto">
                        <ThemeToggle />
                    </div>
                </header>

                <div className="mx-auto w-full max-w-6xl p-4 md:p-8">
                    <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                        <div className="min-w-0">
                            <h1 className="text-2xl font-semibold sm:text-[1.75rem]">{title}</h1>
                            {description && <p className="text-muted-foreground mt-1.5 max-w-2xl text-[0.9375rem] leading-relaxed">{description}</p>}
                        </div>
                        {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                    </div>

                    {children}
                </div>
            </SidebarInset>
        </SidebarProvider>
    );
}
