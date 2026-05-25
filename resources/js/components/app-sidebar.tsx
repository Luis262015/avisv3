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
    BarChart3,
    BookOpen,
    Box,
    Building2,
    FileText,
    Folder,
    LayoutGrid,
    MinusCircle,
    PackageSearch,
    ReceiptText,
    Settings2,
    ShoppingCart,
    Store,
    Tag,
    Tags,
    Truck,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [
    { title: 'Documentación', url: 'https://laravel.com/docs/starter-kits', icon: BookOpen },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: { roles: string[] } }>().props;
    const roles = auth?.roles ?? [];

    const isAdmin = roles.includes('admin');
    const isOperador = roles.includes('operador');
    const canManageProducts = isAdmin || isOperador;
    const canManagePurchases = isAdmin || isOperador;
    const canManageFinances = isAdmin || isOperador;

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
                { title: 'Nueva Venta', url: '/admin/sales/create', icon: ShoppingCart },
                { title: 'Ventas', url: '/admin/sales', icon: BarChart3 },
                { title: 'Turnos de Caja', url: '/admin/cash-shifts', icon: Wallet },
            ],
        },
        ...(canManagePurchases ? [{
            title: 'Compras',
            items: [
                { title: 'Compras', url: '/admin/purchases', icon: Truck },
                { title: 'Proveedores', url: '/admin/suppliers', icon: Building2 },
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
