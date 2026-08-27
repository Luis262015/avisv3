import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';

const ID_CONTENIDO = 'contenido-principal';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <AppShell variant="sidebar">
            {/*
                Primer tabulador de la página: el menú tiene medio centenar de
                enlaces por delante del contenido, y sin esto llegar a lo que
                importa con teclado exige recorrerlos todos en cada navegación.
                Invisible hasta que recibe el foco.
            */}
            <a
                href={`#${ID_CONTENIDO}`}
                className="bg-primary text-primary-foreground focus-visible:ring-ring sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus-visible:ring-2 focus-visible:outline-none"
            >
                Saltar al contenido
            </a>

            <AppSidebar />

            {/* `tabIndex={-1}` para que el salto mueva el foco de verdad y no solo
                el desplazamiento: un <main> sin él no es enfocable. */}
            <AppContent variant="sidebar" id={ID_CONTENIDO} tabIndex={-1} className="scroll-mt-4 focus:outline-none">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
