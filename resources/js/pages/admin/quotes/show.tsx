import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Link, router, useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';

interface QuoteItem { id: number; quantity: string; price: string; discount: string; subtotal: string; product: { name: string; sku: string | null } }
interface Quote {
    id: number; folio: string; date: string; valid_until: string | null;
    subtotal: string; tax: string; discount: string; total: string; status: string; notes: string | null;
    customer: { id: number; name: string; phone: string | null; email: string | null } | null;
    user: { name: string };
    sale: { id: number; folio: string } | null;
    items: QuoteItem[];
}
interface OpenShift { id: number; cash_register: { name: string; store: { name: string } } }

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600', sent: 'bg-blue-100 text-blue-700',
    accepted: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700',
    expired: 'bg-orange-100 text-orange-700', converted: 'bg-purple-100 text-purple-700',
    cancelled: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = {
    draft: 'Borrador', sent: 'Enviada', accepted: 'Aceptada', rejected: 'Rechazada',
    expired: 'Vencida', converted: 'Convertida', cancelled: 'Cancelada',
};

export default function QuoteShow({ quote, isExpired, openShifts }: { quote: Quote; isExpired: boolean; openShifts: OpenShift[] }) {
    const fmt = (v: string) => `$${parseFloat(v).toFixed(2)}`;
    const [showConvert, setShowConvert] = useState(false);
    const convertForm = useForm({
        cash_shift_id: openShifts[0]?.id?.toString() ?? '',
        payment_method: 'cash',
        amount_paid: quote.total,
    });

    const canEdit = ['draft', 'sent'].includes(quote.status);
    const canSend = quote.status === 'draft';
    const canDecide = ['draft', 'sent'].includes(quote.status);
    const canConvert = ['draft', 'sent', 'accepted'].includes(quote.status);
    const canCancel = !['converted', 'cancelled'].includes(quote.status);

    const act = (url: string, msg: string) => { if (window.confirm(msg)) router.patch(url); };

    const submitConvert = (e: React.FormEvent) => {
        e.preventDefault();
        convertForm.post(`/admin/quotes/${quote.id}/convert`);
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Cotizaciones', href: '/admin/quotes' }, { title: quote.folio, href: '' }]}>
            <FlashMessage />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-bold">Cotización {quote.folio}</h1>
                            <span className={`rounded-full px-3 py-1 text-sm font-semibold ${statusColors[quote.status]}`}>{statusLabels[quote.status]}</span>
                            {isExpired && quote.status !== 'expired' && <span className="rounded-full bg-orange-100 px-3 py-1 text-sm font-semibold text-orange-700">Vencida</span>}
                        </div>
                        <p className="mt-1 text-sm text-gray-500">
                            Creada por {quote.user.name} — {quote.date}
                            {quote.valid_until && ` · Válida hasta: ${quote.valid_until}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canEdit && (
                            <Button variant="outline" asChild>
                                <Link href={`/admin/quotes/${quote.id}/edit`}><Pencil className="mr-2 h-4 w-4" /> Editar</Link>
                            </Button>
                        )}
                        {canSend && <Button onClick={() => act(`/admin/quotes/${quote.id}/send`, '¿Marcar como enviada al cliente?')}>Enviar</Button>}
                        {canDecide && <Button variant="outline" onClick={() => act(`/admin/quotes/${quote.id}/accept`, '¿Marcar como aceptada?')}>Aceptar</Button>}
                        {canDecide && <Button variant="outline" onClick={() => act(`/admin/quotes/${quote.id}/reject`, '¿Marcar como rechazada?')}>Rechazar</Button>}
                        {canConvert && <Button onClick={() => setShowConvert((v) => !v)}>Convertir en venta</Button>}
                        {canCancel && <Button variant="destructive" onClick={() => act(`/admin/quotes/${quote.id}/cancel`, '¿Cancelar cotización?')}>Cancelar</Button>}
                    </div>
                </div>

                {quote.sale && (
                    <div className="rounded-lg border border-purple-200 bg-purple-50 p-4 text-sm">
                        Esta cotización se convirtió en la venta{' '}
                        <Link href={`/admin/sales/${quote.sale.id}`} className="font-mono font-semibold text-purple-700 hover:underline">{quote.sale.folio}</Link>.
                    </div>
                )}

                {showConvert && canConvert && (
                    <form onSubmit={submitConvert} className="rounded-lg border bg-white p-4 shadow-sm">
                        <h2 className="mb-3 font-semibold text-gray-700">Convertir en venta</h2>
                        {openShifts.length === 0 ? (
                            <p className="text-sm text-red-500">No hay turnos de caja abiertos. Abre una caja para registrar la venta.</p>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <Label>Turno de caja</Label>
                                    <select className="w-full rounded-md border px-3 py-2 text-sm" value={convertForm.data.cash_shift_id} onChange={(e) => convertForm.setData('cash_shift_id', e.target.value)}>
                                        {openShifts.map((s) => <option key={s.id} value={s.id}>{s.cash_register.store.name} · {s.cash_register.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <Label>Método de pago</Label>
                                    <select className="w-full rounded-md border px-3 py-2 text-sm" value={convertForm.data.payment_method} onChange={(e) => convertForm.setData('payment_method', e.target.value)}>
                                        <option value="cash">Efectivo</option>
                                        <option value="card">Tarjeta</option>
                                        <option value="transfer">Transferencia</option>
                                        <option value="mixed">Mixto</option>
                                    </select>
                                </div>
                                <div>
                                    <Label>Monto pagado</Label>
                                    <Input type="number" step="0.01" min="0" value={convertForm.data.amount_paid} onChange={(e) => convertForm.setData('amount_paid', e.target.value)} />
                                </div>
                                <div className="md:col-span-3">
                                    <Button type="submit" disabled={convertForm.processing}>Generar venta</Button>
                                </div>
                            </div>
                        )}
                    </form>
                )}

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-1">
                        <h2 className="font-semibold text-gray-700">Cliente</h2>
                        {quote.customer ? (
                            <>
                                <p className="font-medium">
                                    <Link href={`/admin/customers/${quote.customer.id}`} className="text-blue-600 hover:underline">{quote.customer.name}</Link>
                                </p>
                                {quote.customer.phone && <p className="text-sm text-gray-500">{quote.customer.phone}</p>}
                                {quote.customer.email && <p className="text-sm text-gray-500">{quote.customer.email}</p>}
                            </>
                        ) : <p className="text-sm text-gray-400">Sin cliente</p>}
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-1">
                        <h2 className="font-semibold text-gray-700">Detalles</h2>
                        {quote.notes && <p className="text-sm text-gray-600"><span className="font-medium">Notas:</span> {quote.notes}</p>}
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
                            {quote.items.map((item) => (
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
                            <tr><td colSpan={4} className="px-4 py-2 text-right text-gray-500">Subtotal</td><td className="px-4 py-2 text-right font-medium">{fmt(quote.subtotal)}</td></tr>
                            <tr><td colSpan={4} className="px-4 py-2 text-right text-gray-500">Descuento</td><td className="px-4 py-2 text-right font-medium">-{fmt(quote.discount)}</td></tr>
                            <tr><td colSpan={4} className="px-4 py-2 text-right text-gray-500">IVA</td><td className="px-4 py-2 text-right font-medium">{fmt(quote.tax)}</td></tr>
                            <tr><td colSpan={4} className="px-4 py-2 text-right font-bold">Total</td><td className="px-4 py-2 text-right text-lg font-bold">{fmt(quote.total)}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
