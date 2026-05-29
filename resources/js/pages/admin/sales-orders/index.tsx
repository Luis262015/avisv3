import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link } from '@inertiajs/react';
import { Eye, Plus, Truck } from 'lucide-react';

interface SalesOrder {
    id: number; folio: string; date: string; expected_date: string | null;
    total: string; status: string; payment_status: string;
    customer: { name: string } | null;
    user: { name: string };
    shipment: { tracking_number: string | null; status: string } | null;
}

const statusColors: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-600', confirmed: 'bg-blue-100 text-blue-700',
    preparing: 'bg-yellow-100 text-yellow-700', shipped: 'bg-purple-100 text-purple-700',
    delivered: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = {
    pending: 'Pendiente', confirmed: 'Confirmado', preparing: 'En preparación',
    shipped: 'Enviado', delivered: 'Entregado', cancelled: 'Cancelado',
};

export default function SalesOrdersIndex({ orders }: { orders: PaginatedData<SalesOrder> }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Pedidos y envíos', href: '/admin/sales-orders' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Pedidos y Envíos</h1>
                    <Button asChild>
                        <Link href="/admin/sales-orders/create"><Plus className="mr-2 h-4 w-4" /> Nuevo Pedido</Link>
                    </Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Folio</th>
                                <th className="px-4 py-3">Fecha</th>
                                <th className="px-4 py-3">Cliente</th>
                                <th className="px-4 py-3 text-right">Total</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3">Envío</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {orders.data.map((o) => (
                                <tr key={o.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono font-medium">{o.folio}</td>
                                    <td className="px-4 py-3 text-gray-500">{o.date}</td>
                                    <td className="px-4 py-3 text-gray-600">{o.customer?.name ?? 'Sin cliente'}</td>
                                    <td className="px-4 py-3 text-right font-medium">${parseFloat(o.total).toFixed(2)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[o.status]}`}>
                                            {statusLabels[o.status]}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">
                                        {o.shipment ? (
                                            <span className="flex items-center gap-1"><Truck className="h-3.5 w-3.5" /> {o.shipment.tracking_number ?? 'Sin guía'}</span>
                                        ) : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link href={`/admin/sales-orders/${o.id}`}><Eye className="h-4 w-4" /></Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {orders.data.length === 0 && (
                                <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">Sin pedidos.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
