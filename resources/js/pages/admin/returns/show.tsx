import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';

interface ReturnItem { id: number; quantity: string; unit_price: string; subtotal: string; product: { name: string; sku: string | null } }
interface SaleReturn {
    id: number; folio: string; date: string; reason: string | null; refund_method: string; refund_amount: string;
    status: string; restock: boolean; notes: string | null;
    sale: { id: number; folio: string } | null;
    customer: { id: number; name: string } | null;
    user: { name: string };
    items: ReturnItem[];
}

const statusColors: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-600', approved: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = {
    pending: 'Pendiente', approved: 'Aprobada', completed: 'Completada', rejected: 'Rechazada',
};
const methodLabels: Record<string, string> = {
    cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia', store_credit: 'Crédito en tienda',
};

export default function ReturnShow({ return: ret }: { return: SaleReturn }) {
    const fmt = (v: string) => `$${parseFloat(v).toFixed(2)}`;
    const act = (url: string, msg: string) => { if (window.confirm(msg)) router.patch(url); };

    const canApprove = ret.status === 'pending';
    const canComplete = ['pending', 'approved'].includes(ret.status);
    const canReject = !['completed', 'rejected'].includes(ret.status);

    return (
        <AppLayout breadcrumbs={[{ title: 'Devoluciones', href: '/admin/returns' }, { title: ret.folio, href: '' }]}>
            <FlashMessage />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-bold">Devolución {ret.folio}</h1>
                            <span className={`rounded-full px-3 py-1 text-sm font-semibold ${statusColors[ret.status]}`}>{statusLabels[ret.status]}</span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">
                            Registrada por {ret.user.name} — {ret.date}
                            {ret.sale && <> · venta <Link href={`/admin/sales/${ret.sale.id}`} className="text-blue-600 hover:underline">{ret.sale.folio}</Link></>}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canApprove && <Button onClick={() => act(`/admin/returns/${ret.id}/approve`, '¿Aprobar devolución?')}>Aprobar</Button>}
                        {canComplete && <Button onClick={() => act(`/admin/returns/${ret.id}/complete`, '¿Completar devolución? Se reintegrará el inventario si corresponde.')}>Completar</Button>}
                        {canReject && <Button variant="destructive" onClick={() => act(`/admin/returns/${ret.id}/reject`, '¿Rechazar devolución?')}>Rechazar</Button>}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="rounded-lg border bg-white p-4 shadow-sm"><p className="text-xs uppercase text-gray-400">Reembolso</p><p className="text-2xl font-bold">{fmt(ret.refund_amount)}</p></div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm"><p className="text-xs uppercase text-gray-400">Método</p><p className="text-lg font-semibold">{methodLabels[ret.refund_method]}</p></div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm"><p className="text-xs uppercase text-gray-400">Reintegro de stock</p><p className="text-lg font-semibold">{ret.restock ? 'Sí' : 'No'}</p></div>
                </div>

                <div className="rounded-lg border bg-white p-4 shadow-sm text-sm text-gray-600 space-y-1">
                    <p><span className="text-gray-400">Cliente:</span> {ret.customer?.name ?? '—'}</p>
                    {ret.reason && <p><span className="text-gray-400">Motivo:</span> {ret.reason}</p>}
                    {ret.notes && <p><span className="text-gray-400">Notas:</span> {ret.notes}</p>}
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Productos devueltos</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3 text-right">Cantidad</th>
                                <th className="px-4 py-3 text-right">Precio</th>
                                <th className="px-4 py-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {ret.items.map((it) => (
                                <tr key={it.id}>
                                    <td className="px-4 py-3 font-medium">{it.product.name}</td>
                                    <td className="px-4 py-3 text-right">{it.quantity}</td>
                                    <td className="px-4 py-3 text-right">{fmt(it.unit_price)}</td>
                                    <td className="px-4 py-3 text-right font-medium">{fmt(it.subtotal)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
