import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Link, router, useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';

interface OrderItem { id: number; quantity: string; price: string; discount: string; subtotal: string; product: { name: string; sku: string | null } }
interface Shipment { id: number; carrier: string | null; tracking_number: string | null; status: string; shipped_at: string | null; delivered_at: string | null; address: string | null; cost: string; notes: string | null }
interface Order {
    id: number; folio: string; date: string; expected_date: string | null;
    subtotal: string; tax: string; discount: string; total: string; status: string; payment_status: string;
    shipping_address: string | null; notes: string | null;
    customer: { id: number; name: string; phone: string | null } | null;
    user: { name: string };
    sale: { id: number; folio: string } | null;
    quote: { id: number; folio: string } | null;
    items: OrderItem[];
    shipment: Shipment | null;
}
interface OpenShift { id: number; cash_register: { name: string; store: { name: string } } }

const statusColors: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-600', confirmed: 'bg-blue-100 text-blue-700',
    preparing: 'bg-yellow-100 text-yellow-700', shipped: 'bg-purple-100 text-purple-700',
    delivered: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = {
    pending: 'Pendiente', confirmed: 'Confirmado', preparing: 'En preparación',
    shipped: 'Enviado', delivered: 'Entregado', cancelled: 'Cancelado',
};

export default function SalesOrderShow({ order, openShifts }: { order: Order; openShifts: OpenShift[] }) {
    const fmt = (v: string) => `$${parseFloat(v).toFixed(2)}`;
    const [panel, setPanel] = useState<'none' | 'ship' | 'deliver'>('none');

    const shipForm = useForm({
        carrier: order.shipment?.carrier ?? '', tracking_number: order.shipment?.tracking_number ?? '',
        address: order.shipment?.address ?? order.shipping_address ?? '', cost: order.shipment?.cost ?? '0', notes: '',
    });
    const deliverForm = useForm({
        cash_shift_id: openShifts[0]?.id?.toString() ?? '', payment_method: 'cash', amount_paid: order.total,
    });

    const canEdit = ['pending', 'confirmed'].includes(order.status);
    const canConfirm = order.status === 'pending';
    const canPrepare = order.status === 'confirmed';
    const canShip = ['confirmed', 'preparing'].includes(order.status);
    const canDeliver = ['shipped', 'preparing', 'confirmed'].includes(order.status) && !order.sale;
    const canCancel = !['delivered', 'cancelled'].includes(order.status);

    const act = (url: string, msg: string) => { if (window.confirm(msg)) router.patch(url); };

    return (
        <AppLayout breadcrumbs={[{ title: 'Pedidos y envíos', href: '/admin/sales-orders' }, { title: order.folio, href: '' }]}>
            <FlashMessage />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-bold">Pedido {order.folio}</h1>
                            <span className={`rounded-full px-3 py-1 text-sm font-semibold ${statusColors[order.status]}`}>{statusLabels[order.status]}</span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">
                            Creado por {order.user.name} — {order.date}
                            {order.expected_date && ` · Entrega esperada: ${order.expected_date}`}
                            {order.quote && <> · desde cotización <Link href={`/admin/quotes/${order.quote.id}`} className="text-blue-600 hover:underline">{order.quote.folio}</Link></>}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canEdit && <Button variant="outline" asChild><Link href={`/admin/sales-orders/${order.id}/edit`}><Pencil className="mr-2 h-4 w-4" /> Editar</Link></Button>}
                        {canConfirm && <Button onClick={() => act(`/admin/sales-orders/${order.id}/confirm`, '¿Confirmar pedido?')}>Confirmar</Button>}
                        {canPrepare && <Button variant="outline" onClick={() => act(`/admin/sales-orders/${order.id}/prepare`, '¿Marcar en preparación?')}>Preparar</Button>}
                        {canShip && <Button variant="outline" onClick={() => setPanel(panel === 'ship' ? 'none' : 'ship')}>Registrar envío</Button>}
                        {canDeliver && <Button onClick={() => setPanel(panel === 'deliver' ? 'none' : 'deliver')}>Entregar y facturar</Button>}
                        {canCancel && <Button variant="destructive" onClick={() => act(`/admin/sales-orders/${order.id}/cancel`, '¿Cancelar pedido?')}>Cancelar</Button>}
                    </div>
                </div>

                {order.sale && (
                    <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-sm">
                        Venta generada:{' '}
                        <Link href={`/admin/sales/${order.sale.id}`} className="font-mono font-semibold text-green-700 hover:underline">{order.sale.folio}</Link>
                    </div>
                )}

                {panel === 'ship' && (
                    <form onSubmit={(e) => { e.preventDefault(); shipForm.post(`/admin/sales-orders/${order.id}/ship`); }} className="rounded-lg border bg-white p-4 shadow-sm">
                        <h2 className="mb-3 font-semibold text-gray-700">Registrar envío</h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div><Label>Transportista</Label><Input value={shipForm.data.carrier} onChange={(e) => shipForm.setData('carrier', e.target.value)} /></div>
                            <div><Label>Número de guía / tracking</Label><Input value={shipForm.data.tracking_number} onChange={(e) => shipForm.setData('tracking_number', e.target.value)} /></div>
                            <div className="md:col-span-2"><Label>Dirección</Label><Input value={shipForm.data.address} onChange={(e) => shipForm.setData('address', e.target.value)} /></div>
                            <div><Label>Costo de envío</Label><Input type="number" step="0.01" min="0" value={shipForm.data.cost} onChange={(e) => shipForm.setData('cost', e.target.value)} /></div>
                            <div><Label>Notas</Label><Input value={shipForm.data.notes} onChange={(e) => shipForm.setData('notes', e.target.value)} /></div>
                        </div>
                        <div className="mt-3"><Button type="submit" disabled={shipForm.processing}>Guardar envío</Button></div>
                    </form>
                )}

                {panel === 'deliver' && (
                    <form onSubmit={(e) => { e.preventDefault(); deliverForm.post(`/admin/sales-orders/${order.id}/deliver`); }} className="rounded-lg border bg-white p-4 shadow-sm">
                        <h2 className="mb-3 font-semibold text-gray-700">Entregar y generar venta</h2>
                        {openShifts.length === 0 ? (
                            <p className="text-sm text-red-500">No hay turnos de caja abiertos.</p>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <Label>Turno de caja</Label>
                                    <select className="w-full rounded-md border px-3 py-2 text-sm" value={deliverForm.data.cash_shift_id} onChange={(e) => deliverForm.setData('cash_shift_id', e.target.value)}>
                                        {openShifts.map((s) => <option key={s.id} value={s.id}>{s.cash_register.store.name} · {s.cash_register.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <Label>Método de pago</Label>
                                    <select className="w-full rounded-md border px-3 py-2 text-sm" value={deliverForm.data.payment_method} onChange={(e) => deliverForm.setData('payment_method', e.target.value)}>
                                        <option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="mixed">Mixto</option>
                                    </select>
                                </div>
                                <div><Label>Monto pagado</Label><Input type="number" step="0.01" min="0" value={deliverForm.data.amount_paid} onChange={(e) => deliverForm.setData('amount_paid', e.target.value)} /></div>
                                <div className="md:col-span-3"><Button type="submit" disabled={deliverForm.processing}>Confirmar entrega</Button></div>
                            </div>
                        )}
                    </form>
                )}

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-1">
                        <h2 className="font-semibold text-gray-700">Cliente</h2>
                        {order.customer ? (
                            <>
                                <p className="font-medium"><Link href={`/admin/customers/${order.customer.id}`} className="text-blue-600 hover:underline">{order.customer.name}</Link></p>
                                {order.customer.phone && <p className="text-sm text-gray-500">{order.customer.phone}</p>}
                            </>
                        ) : <p className="text-sm text-gray-400">Sin cliente</p>}
                        {order.shipping_address && <p className="mt-2 text-sm text-gray-600"><span className="font-medium">Envío a:</span> {order.shipping_address}</p>}
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-1">
                        <h2 className="font-semibold text-gray-700">Envío</h2>
                        {order.shipment ? (
                            <div className="text-sm text-gray-600">
                                <p><span className="font-medium">Estado:</span> {order.shipment.status}</p>
                                {order.shipment.carrier && <p><span className="font-medium">Transportista:</span> {order.shipment.carrier}</p>}
                                {order.shipment.tracking_number && <p><span className="font-medium">Guía:</span> {order.shipment.tracking_number}</p>}
                                {order.shipment.shipped_at && <p><span className="font-medium">Enviado:</span> {new Date(order.shipment.shipped_at).toLocaleString()}</p>}
                                {order.shipment.delivered_at && <p><span className="font-medium">Entregado:</span> {new Date(order.shipment.delivered_at).toLocaleString()}</p>}
                            </div>
                        ) : <p className="text-sm text-gray-400">Sin envío registrado</p>}
                    </div>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Artículos</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3 text-right">Cantidad</th>
                                <th className="px-4 py-3 text-right">Precio</th>
                                <th className="px-4 py-3 text-right">Desc.</th>
                                <th className="px-4 py-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {order.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-4 py-3 font-medium">{item.product.name}</td>
                                    <td className="px-4 py-3 text-right">{item.quantity}</td>
                                    <td className="px-4 py-3 text-right">{fmt(item.price)}</td>
                                    <td className="px-4 py-3 text-right">{fmt(item.discount)}</td>
                                    <td className="px-4 py-3 text-right font-medium">{fmt(item.subtotal)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="border-t bg-gray-50 text-sm">
                            <tr><td colSpan={4} className="px-4 py-2 text-right text-gray-500">Subtotal</td><td className="px-4 py-2 text-right font-medium">{fmt(order.subtotal)}</td></tr>
                            <tr><td colSpan={4} className="px-4 py-2 text-right text-gray-500">Descuento</td><td className="px-4 py-2 text-right font-medium">-{fmt(order.discount)}</td></tr>
                            <tr><td colSpan={4} className="px-4 py-2 text-right text-gray-500">IVA</td><td className="px-4 py-2 text-right font-medium">{fmt(order.tax)}</td></tr>
                            <tr><td colSpan={4} className="px-4 py-2 text-right font-bold">Total</td><td className="px-4 py-2 text-right text-lg font-bold">{fmt(order.total)}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
