import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/react';
import { Banknote, LockOpen, ShoppingCart } from 'lucide-react';

interface CashShift {
    id: number;
    register_name: string;
}

/**
 * Lo que se toca a diario, fuera de la lista.
 *
 * Esto es un punto de venta: vender y abrir caja pasan cien veces al día, y
 * estaban como dos enlaces más entre casi cincuenta, con el mismo peso visual
 * que «Áreas» o «Capacitación». Sacarlos arriba es la diferencia entre un panel
 * de administración y una herramienta de mostrador.
 *
 * El estado de la caja no se distingue solo por el color —eso deja fuera a quien
 * no lo percibe—: cambian el icono, el texto y el destino del enlace.
 */
export function NavQuickActions({ cashShift }: { cashShift: CashShift | null }) {
    const abierta = cashShift !== null;

    return (
        <SidebarMenu className="gap-1.5">
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    tooltip="Nueva Venta"
                    className="bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90 hover:text-sidebar-primary-foreground active:bg-sidebar-primary/90 active:text-sidebar-primary-foreground h-9 font-semibold shadow-sm"
                >
                    <Link href="/admin/sales/create" prefetch>
                        <ShoppingCart aria-hidden="true" />
                        <span>Nueva Venta</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    tooltip={abierta ? `Caja abierta en ${cashShift.register_name}` : 'Abrir caja'}
                    className={
                        abierta
                            ? 'h-9 bg-emerald-500/10 text-emerald-700 hover:bg-emerald-500/15 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-400'
                            : 'border-sidebar-border h-9 border border-dashed'
                    }
                >
                    <Link
                        href={abierta ? `/admin/cash-shifts/${cashShift.id}` : '/admin/cash-shifts/create'}
                        prefetch
                        // El nombre visible es solo el de la caja; el estado tiene que
                        // llegar también a quien no ve el color ni el icono.
                        aria-label={abierta ? `Caja abierta en ${cashShift.register_name}` : 'Abrir caja'}
                    >
                        {abierta ? <Banknote aria-hidden="true" /> : <LockOpen aria-hidden="true" />}
                        <span className="truncate">{abierta ? cashShift.register_name : 'Abrir caja'}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
