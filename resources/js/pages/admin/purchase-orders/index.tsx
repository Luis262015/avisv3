import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';

interface PurchaseOrder {
    id: number; folio: string; date: string; expected_date: string | null;
    total: string; status: string;
    supplier: { name: string } | null;
    user: { name: string };
}

const statusColors: Record<string, string> = {
    draft:     'bg-gray-100 text-gray-600',
    confirmed: 'bg-blue-100 text-blue-700',
    sent:      'bg-purple-100 text-purple-700',
    partial:   'bg-yellow-100 text-yellow-700',
    received:  'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = {
    draft: 'Borrador', confirmed: 'Confirmada', sent: 'Enviada al proveedor',
    partial: 'Recepción parcial', received: 'Recibida', cancelled: 'Cancelada',
};

export default function PurchaseOrdersIndex({ orders }: { orders: PaginatedData<PurchaseOrder> }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Órdenes de compra', href: '/admin/purchase-orders' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Órdenes de Compra</h1>
                    <Button asChild>
                        <Link href="/admin/purchase-orders/create"><Plus className="mr-2 h-4 w-4" /> Nueva Orden</Link>
                    </Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Folio</th>
                                <th className="px-4 py-3">Fecha</th>
                                <th className="px-4 py-3">Entrega esperada</th>
                                <th className="px-4 py-3">Proveedor</th>
                                <th className="px-4 py-3 text-right">Total</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3">Creada por</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {orders.data.map((o) => (
                                <tr key={o.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono font-medium">{o.folio}</td>
                                    <td className="px-4 py-3 text-gray-500">{o.date}</td>
                                    <td className="px-4 py-3 text-gray-500">{o.expected_date ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-600">{o.supplier?.name ?? 'Sin proveedor'}</td>
                                    <td className="px-4 py-3 text-right font-medium">${parseFloat(o.total).toFixed(2)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[o.status]}`}>
                                            {statusLabels[o.status]}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">{o.user.name}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link href={`/admin/purchase-orders/${o.id}`}><Eye className="h-4 w-4" /></Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {orders.data.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Sin órdenes de compra.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
