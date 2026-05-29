import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';

interface OrderItem {
    id: number; quantity: string; quantity_received: string; cost: string; subtotal: string;
    product: { name: string; sku: string | null };
}
interface RelatedPurchase { id: number; folio: string; date: string; status: string; total: string }
interface PurchaseOrder {
    id: number; folio: string; date: string; expected_date: string | null;
    subtotal: string; tax: string; total: string; status: string; notes: string | null;
    supplier: { id: number; name: string; contact_name: string | null; phone: string | null; payment_terms: string | null } | null;
    store: { name: string } | null;
    user: { name: string };
    items: OrderItem[];
    purchases: RelatedPurchase[];
}

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600', confirmed: 'bg-blue-100 text-blue-700',
    sent: 'bg-purple-100 text-purple-700', partial: 'bg-yellow-100 text-yellow-700',
    received: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = {
    draft: 'Borrador', confirmed: 'Confirmada', sent: 'Enviada al proveedor',
    partial: 'Recepción parcial', received: 'Recibida', cancelled: 'Cancelada',
};
const purchaseStatusLabels: Record<string, string> = {
    pending: 'Pendiente', partial: 'Parcial', received: 'Recibida', cancelled: 'Cancelada',
};

export default function PurchaseOrderShow({ order }: { order: PurchaseOrder }) {
    const fmt = (v: string) => `$${parseFloat(v).toFixed(2)}`;

    const handleConfirm = () => {
        if (window.confirm('¿Confirmar esta orden?')) router.patch(`/admin/purchase-orders/${order.id}/confirm`);
    };
    const handleMarkSent = () => {
        if (window.confirm('¿Marcar como enviada al proveedor?')) router.patch(`/admin/purchase-orders/${order.id}/send`);
    };
    const handleConvert = () => {
        if (window.confirm('¿Convertir esta orden en una compra? Se generará una nueva compra pendiente de recepción.')) {
            router.post(`/admin/purchase-orders/${order.id}/convert`);
        }
    };
    const handleCancel = () => {
        if (window.confirm('¿Cancelar esta orden de compra?')) router.patch(`/admin/purchase-orders/${order.id}/cancel`);
    };

    const canEdit = ['draft', 'confirmed'].includes(order.status);
    const canConfirm = order.status === 'draft';
    const canSend = order.status === 'confirmed';
    const canConvert = ['confirmed', 'sent'].includes(order.status);
    const canCancel = order.status !== 'cancelled' && order.status !== 'received';

    return (
        <AppLayout breadcrumbs={[{ title: 'Órdenes de compra', href: '/admin/purchase-orders' }, { title: order.folio, href: '' }]}>
            <FlashMessage />
            <div className="p-6 space-y-6">

                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="text-2xl font-bold">Orden {order.folio}</h1>
                            <span className={`rounded-full px-3 py-1 text-sm font-semibold ${statusColors[order.status]}`}>{statusLabels[order.status]}</span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">
                            Creada por {order.user.name} — {order.date}
                            {order.expected_date && ` · Entrega esperada: ${order.expected_date}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canEdit && (
                            <Button variant="outline" asChild>
                                <Link href={`/admin/purchase-orders/${order.id}/edit`}><Pencil className="mr-2 h-4 w-4" /> Editar</Link>
                            </Button>
                        )}
                        {canConfirm && <Button onClick={handleConfirm}>Confirmar orden</Button>}
                        {canSend && <Button variant="outline" onClick={handleMarkSent}>Marcar como enviada</Button>}
                        {canConvert && <Button onClick={handleConvert}>Convertir en compra</Button>}
                        {canCancel && <Button variant="destructive" onClick={handleCancel}>Cancelar</Button>}
                    </div>
                </div>

                {/* Info */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {order.supplier && (
                        <div className="rounded-lg border bg-white p-4 shadow-sm space-y-1">
                            <h2 className="font-semibold text-gray-700">Proveedor</h2>
                            <p className="font-medium">
                                <Link href={`/admin/suppliers/${order.supplier.id}`} className="text-blue-600 hover:underline">{order.supplier.name}</Link>
                            </p>
                            {order.supplier.contact_name && <p className="text-sm text-gray-500">{order.supplier.contact_name}</p>}
                            {order.supplier.phone && <p className="text-sm text-gray-500">{order.supplier.phone}</p>}
                            {order.supplier.payment_terms && <p className="text-sm text-gray-500">Plazo: {order.supplier.payment_terms}</p>}
                        </div>
                    )}
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-1">
                        <h2 className="font-semibold text-gray-700">Detalles</h2>
                        {order.store && <p className="text-sm text-gray-600"><span className="font-medium">Tienda destino:</span> {order.store.name}</p>}
                        {order.expected_date && <p className="text-sm text-gray-600"><span className="font-medium">Fecha estimada:</span> {order.expected_date}</p>}
                        {order.notes && <p className="text-sm text-gray-600"><span className="font-medium">Notas:</span> {order.notes}</p>}
                    </div>
                </div>

                {/* Items */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Artículos</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3">SKU</th>
                                <th className="px-4 py-3 text-right">Cantidad</th>
                                <th className="px-4 py-3 text-right">Recibido</th>
                                <th className="px-4 py-3 text-right">Costo unit.</th>
                                <th className="px-4 py-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {order.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-4 py-3 font-medium">{item.product.name}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-400">{item.product.sku ?? '—'}</td>
                                    <td className="px-4 py-3 text-right">{item.quantity}</td>
                                    <td className="px-4 py-3 text-right text-blue-600">{item.quantity_received}</td>
                                    <td className="px-4 py-3 text-right">{fmt(item.cost)}</td>
                                    <td className="px-4 py-3 text-right font-medium">{fmt(item.subtotal)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="border-t bg-gray-50 text-sm">
                            <tr>
                                <td colSpan={5} className="px-4 py-2 text-right text-gray-500">Subtotal</td>
                                <td className="px-4 py-2 text-right font-medium">{fmt(order.subtotal)}</td>
                            </tr>
                            <tr>
                                <td colSpan={5} className="px-4 py-2 text-right text-gray-500">IVA</td>
                                <td className="px-4 py-2 text-right font-medium">{fmt(order.tax)}</td>
                            </tr>
                            <tr>
                                <td colSpan={5} className="px-4 py-2 text-right font-bold">Total</td>
                                <td className="px-4 py-2 text-right text-lg font-bold">{fmt(order.total)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {/* Related purchases */}
                {order.purchases.length > 0 && (
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-4 py-3 font-semibold text-gray-700">Compras generadas</div>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-4 py-3">Folio</th>
                                    <th className="px-4 py-3">Fecha</th>
                                    <th className="px-4 py-3">Estado</th>
                                    <th className="px-4 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {order.purchases.map((p) => (
                                    <tr key={p.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <Link href={`/admin/purchases/${p.id}`} className="font-mono font-medium text-blue-600 hover:underline">{p.folio}</Link>
                                        </td>
                                        <td className="px-4 py-3 text-gray-500">{p.date}</td>
                                        <td className="px-4 py-3 text-gray-600">{purchaseStatusLabels[p.status] ?? p.status}</td>
                                        <td className="px-4 py-3 text-right font-medium">{fmt(p.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
