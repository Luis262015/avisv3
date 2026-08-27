import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavQuickActions } from '@/components/nav-quick-actions';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup, type NavItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import {
    ArrowDownCircle,
    ArrowLeftRight,
    ArrowUpCircle,
    Banknote,
    BookOpen,
    Box,
    Building2,
    CalendarClock,
    CalendarDays,
    ChartColumn,
    ChartLine,
    ChartPie,
    ClipboardList,
    CloudOff,
    Coins,
    CreditCard,
    FileText,
    Folder,
    GraduationCap,
    History,
    LayoutGrid,
    ListChecks,
    MinusCircle,
    Network,
    PackageCheck,
    PackageSearch,
    Percent,
    Receipt,
    ReceiptText,
    Settings2,
    ShieldCheck,
    Store,
    Tag,
    Tags,
    TrendingUp,
    Truck,
    Undo2,
    UserCog,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-react';
import AppLogo from './app-logo';

// La documentación del starter kit no le sirve a nadie que use esto; la del SIN
// sí, que es la que rige la facturación.
const footerNavItems: NavItem[] = [
    {
        title: 'Documentación SIAT',
        url: 'https://siatinfo.impuestos.gob.bo/index.php/facturacion-en-linea/factura-electronica',
        icon: BookOpen,
    },
];

interface ActiveCashShift {
    id: number;
    register_name: string;
}

export function AppSidebar() {
    const { auth, activeCashShift } = usePage<{
        auth: { roles: string[] };
        activeCashShift: ActiveCashShift | null;
    }>().props;
    const roles = auth?.roles ?? [];

    const isAdmin = roles.includes('admin');
    const isOperador = roles.includes('operador');
    const canManageProducts = isAdmin || isOperador;
    const canManagePurchases = isAdmin || isOperador;
    const canManageFinances = isAdmin || isOperador;
    const canManageSales = isAdmin || isOperador;

    // Solo nacen abiertos los dos grupos que se usan a diario; los demás son
    // consulta ocasional y desplegados todos a la vez tapaban el menú entero.
    const navGroups: NavGroup[] = [
        {
            title: 'General',
            defaultOpen: true,
            items: [
                { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
            ],
        },
        {
            // «Nueva Venta» y la caja ya viven arriba, fuera de la lista: son las
            // dos cosas que se tocan a diario y no deben competir con otras
            // cuarenta y ocho entradas.
            title: 'Punto de Venta',
            defaultOpen: true,
            items: [
                { title: 'Ventas', url: '/admin/sales', icon: Receipt },
                { title: 'Turnos de Caja', url: '/admin/cash-shifts', icon: History },
            ],
        },
        ...(canManageSales ? [{
            title: 'Ventas',
            items: [
                { title: 'Clientes', url: '/admin/customers', icon: Users },
                { title: 'Cotizaciones', url: '/admin/quotes', icon: FileText },
                { title: 'Pedidos y envíos', url: '/admin/sales-orders', icon: PackageCheck },
                { title: 'Promociones', url: '/admin/promotions', icon: Percent },
                { title: 'Devoluciones', url: '/admin/returns', icon: Undo2 },
                { title: 'Garantías', url: '/admin/warranties', icon: ShieldCheck },
                { title: 'Reportes de ventas', url: '/admin/sales-reports', icon: TrendingUp },
            ],
        }] : []),
        ...(canManagePurchases ? [{
            title: 'Compras',
            items: [
                { title: 'Órdenes de compra', url: '/admin/purchase-orders', icon: ClipboardList },
                { title: 'Compras', url: '/admin/purchases', icon: Truck },
                { title: 'Proveedores', url: '/admin/suppliers', icon: Building2 },
                { title: 'Reportes de compras', url: '/admin/purchases-reports', icon: ChartPie },
            ],
        }] : []),
        ...(canManageFinances ? [{
            title: 'Finanzas',
            items: [
                { title: 'Gastos', url: '/admin/expenses', icon: ArrowDownCircle },
                { title: 'Ingresos', url: '/admin/incomes', icon: ArrowUpCircle },
                { title: 'Retiros', url: '/admin/withdrawals', icon: MinusCircle },
                { title: 'Cuentas por cobrar', url: '/admin/receivables', icon: Coins },
                { title: 'Cuentas por pagar', url: '/admin/payables', icon: CreditCard },
                { title: 'Reporte financiero', url: '/admin/financial-reports', icon: ChartLine },
            ],
        }] : []),
        ...(canManageProducts ? [{
            title: 'Catálogo',
            items: [
                { title: 'Productos', url: '/admin/products', icon: Box },
                { title: 'Categorías', url: '/admin/categories', icon: Folder },
                { title: 'Marcas', url: '/admin/brands', icon: Tag },
                { title: 'Etiquetas', url: '/admin/tags', icon: Tags },
                { title: 'Existencias por tienda', url: '/admin/inventory/stock', icon: Warehouse },
                { title: 'Inventario', url: '/admin/inventory', icon: PackageSearch },
                { title: 'Transferencias', url: '/admin/stock-transfers', icon: ArrowLeftRight },
            ],
        }] : []),
        ...(isAdmin ? [{
            title: 'Recursos Humanos',
            items: [
                { title: 'Empleados', url: '/admin/employees', icon: UserCog },
                { title: 'Áreas', url: '/admin/departments', icon: Network },
                { title: 'Asistencia', url: '/admin/attendances', icon: CalendarClock },
                { title: 'Ausencias', url: '/admin/leave-requests', icon: CalendarDays },
                { title: 'Nómina', url: '/admin/payrolls', icon: Banknote },
                { title: 'Capacitación', url: '/admin/trainings', icon: GraduationCap },
                { title: 'Reportes RR.HH.', url: '/admin/hr-reports', icon: ChartColumn },
            ],
        }] : []),
        ...(isAdmin ? [{
            title: 'Facturación SIAT',
            items: [
                { title: 'Facturas Electrónicas', url: '/admin/siat/invoices', icon: FileText },
                { title: 'Homologación SIN', url: '/admin/siat/homologation', icon: ListChecks },
                { title: 'Contingencia', url: '/admin/siat/contingency', icon: CloudOff },
                { title: 'Registro de Compras', url: '/admin/siat/purchase-registry', icon: ReceiptText },
                { title: 'Configuración SIAT', url: '/admin/siat/settings', icon: Settings2 },
            ],
        }] : []),
        ...(isAdmin ? [{
            title: 'Configuración',
            items: [
                { title: 'Tiendas', url: '/admin/stores', icon: Store },
                { title: 'Cajas', url: '/admin/cash-registers', icon: Wallet },
            ],
        }] : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            {/* Con el menú colapsado el nombre se oculta y el enlace se
                                quedaría sin nombre accesible: solo el logo, que es
                                decorativo. */}
                            <Link href="/dashboard" prefetch aria-label="AvisV3 — ir al panel">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                <NavQuickActions cashShift={activeCashShift} />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
