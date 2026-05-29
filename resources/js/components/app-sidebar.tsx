import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup, type NavItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    Banknote,
    BarChart2,
    BarChart3,
    BookOpen,
    Box,
    Building2,
    CalendarClock,
    CalendarDays,
    ClipboardList,
    FileText,
    Folder,
    GraduationCap,
    LayoutGrid,
    LockOpen,
    MinusCircle,
    Network,
    PackageCheck,
    PackageSearch,
    Percent,
    ReceiptText,
    Settings2,
    ShieldCheck,
    ShoppingCart,
    Store,
    Tag,
    Tags,
    Truck,
    Undo2,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [
    { title: 'Documentación', url: 'https://laravel.com/docs/starter-kits', icon: BookOpen },
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

    const navGroups: NavGroup[] = [
        {
            title: 'General',
            items: [
                { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
            ],
        },
        {
            title: 'Punto de Venta',
            items: [
                activeCashShift
                    ? { title: 'Caja', url: `/admin/cash-shifts/${activeCashShift.id}`, icon: Banknote }
                    : { title: 'Abrir Caja', url: '/admin/cash-shifts/create', icon: LockOpen },
                { title: 'Nueva Venta', url: '/admin/sales/create', icon: ShoppingCart },
                { title: 'Ventas', url: '/admin/sales', icon: BarChart3 },
                { title: 'Turnos de Caja', url: '/admin/cash-shifts', icon: Wallet },
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
                { title: 'Reportes de ventas', url: '/admin/sales-reports', icon: BarChart2 },
            ],
        }] : []),
        ...(canManagePurchases ? [{
            title: 'Compras',
            items: [
                { title: 'Órdenes de compra', url: '/admin/purchase-orders', icon: ClipboardList },
                { title: 'Compras', url: '/admin/purchases', icon: Truck },
                { title: 'Proveedores', url: '/admin/suppliers', icon: Building2 },
                { title: 'Reportes de compras', url: '/admin/purchases-reports', icon: BarChart2 },
            ],
        }] : []),
        ...(canManageFinances ? [{
            title: 'Finanzas',
            items: [
                { title: 'Gastos', url: '/admin/expenses', icon: ArrowDownCircle },
                { title: 'Ingresos', url: '/admin/incomes', icon: ArrowUpCircle },
                { title: 'Retiros', url: '/admin/withdrawals', icon: MinusCircle },
                { title: 'Cuentas por cobrar', url: '/admin/receivables', icon: ReceiptText },
                { title: 'Cuentas por pagar', url: '/admin/payables', icon: ReceiptText },
                { title: 'Reporte financiero', url: '/admin/financial-reports', icon: BarChart2 },
            ],
        }] : []),
        ...(canManageProducts ? [{
            title: 'Catálogo',
            items: [
                { title: 'Productos', url: '/admin/products', icon: Box },
                { title: 'Categorías', url: '/admin/categories', icon: Folder },
                { title: 'Marcas', url: '/admin/brands', icon: Tag },
                { title: 'Etiquetas', url: '/admin/tags', icon: Tags },
                { title: 'Inventario', url: '/admin/inventory', icon: PackageSearch },
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
                { title: 'Reportes RR.HH.', url: '/admin/hr-reports', icon: BarChart2 },
            ],
        }] : []),
        ...(isAdmin ? [{
            title: 'Facturación SIAT',
            items: [
                { title: 'Facturas Electrónicas', url: '/admin/siat/invoices', icon: FileText },
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
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
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
