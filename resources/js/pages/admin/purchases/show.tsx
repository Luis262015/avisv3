import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Link, router, useForm } from '@inertiajs/react';
import { FileText, Pencil } from 'lucide-react';
import { useState } from 'react';

interface PurchaseItem {
    id: number; quantity: string; received_quantity: string | null; cost: string; subtotal: string;
    product: { name: string; sku: string | null };
}
interface AuditLog { id: number; action: string; description: string; created_at: string; user: { name: string } }
interface Payment { id: number; amount: string; payment_method: string; date: string; user: { name: string } }
interface Purchase {
    id: number; folio: string; date: string; subtotal: string; tax: string; total: string;
    status: string; payment_status: string; received_at: string | null;
    invoice_number: string | null; invoice_date: string | null;
    document_path: string | null; notes: string | null; audit_notes: string | null;
    supplier: { id: number; name: string; contact_name: string | null; phone: string | null; payment_terms: string | null } | null;
    store: { name: string } | null;
    user: { name: string };
    items: PurchaseItem[];
    payable: { id: number; amount: string; amount_paid: string; balance: string; due_date: string; status: string; payments: Payment[] } | null;
    auditLogs: AuditLog[];
    purchaseOrder: { id: number; folio: string } | null;
}

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700', partial: 'bg-blue-100 text-blue-700',
    received: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = {
    pending: 'Pendiente', partial: 'Parcial recibida', received: 'Recibida', cancelled: 'Cancelada',
};
const paymentColors: Record<string, string> = {
    unpaid: 'bg-red-100 text-red-700', partial: 'bg-yellow-100 text-yellow-700', paid: 'bg-green-100 text-green-700',
};
const paymentLabels: Record<string, string> = { unpaid: 'No pagada', partial: 'Pago parcial', paid: 'Pagada' };
const paymentMethodLabels: Record<string, string> = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia' };

export default function PurchaseShow({ purchase }: { purchase: Purchase }) {
    const { auth } = (window as any).__inertia_page?.props ?? {};
    const fmt = (v: string) => `$${parseFloat(v).toFixed(2)}`;
    const [showPartial, setShowPartial] = useState(false);
    const [partialQtys, setPartialQtys] = useState<Record<number, string>>({});

    const receive = () => {
        if (confirm('¿Confirmar recepción completa? Se actualizará el inventario.')) {
            router.patch(`/admin/purchases/${purchase.id}/receive`);
        }
    };
    const cancel = () => {
        if (confirm('¿Cancelar esta compra? Se revertirá el stock si ya fue recibida.')) {
            router.patch(`/admin/purchases/${purchase.id}/cancel`);
        }
    };
    const receivePartial = () => {
        const items = purchase.items
            .filter((item) => partialQtys[item.id] && parseFloat(partialQtys[item.id]) > 0)
            .map((item) => ({ id: item.id, received_quantity: partialQtys[item.id] }));
        if (items.length === 0) return alert('Ingresa al menos una cantidad recibida.');
        router.patch(`/admin/purchases/${purchase.id}/receive-partial`, { items });
    };

    const { data: docData, setData: setDocData, post: postDoc, processing: postingDoc } = useForm<{ document: File | null }>({ document: null });
    const uploadDoc = (e: React.FormEvent) => {
        e.preventDefault();
        postDoc(`/admin/purchases/${purchase.id}/document`, { forceFormData: true });
    };

    const canAct = ['pending', 'partial'].includes(purchase.status);

    return (
        <AppLayout breadcrumbs={[{ title: 'Compras', href: '/admin/purchases' }, { title: purchase.folio, href: '' }]}>
            <FlashMessage />
            <div className="p-6 space-y-6">

                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="text-2xl font-bold">Compra {purchase.folio}</h1>
                            <span className={`rounded-full px-3 py-1 text-sm font-semibold ${statusColors[purchase.status]}`}>{statusLabels[purchase.status]}</span>
                            <span className={`rounded-full px-3 py-1 text-sm font-semibold ${paymentColors[purchase.payment_status]}`}>{paymentLabels[purchase.payment_status]}</span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">
                            Registrada por {purchase.user.name} — {purchase.date}
                            {purchase.received_at && ` · Recibida: ${purchase.received_at}`}
                        </p>
                        {purchase.purchaseOrder && (
                            <p className="text-sm text-gray-500">
                                Orden de compra: <Link href={`/admin/purchase-orders/${purchase.purchaseOrder.id}`} className="text-blue-600 hover:underline font-mono">{purchase.purchaseOrder.folio}</Link>
                            </p>
                        )}
                    </div>
                    <div className="flex gap-2 flex-wrap">
                        <Button variant="outline" asChild>
                            <Link href={`/admin/purchases/${purchase.id}/edit`}><Pencil className="mr-2 h-4 w-4" /> Editar</Link>
                        </Button>
                        {canAct && (
                            <>
                                <Button onClick={receive}>Recibir completo</Button>
                                <Button variant="outline" onClick={() => setShowPartial(!showPartial)}>Recepción parcial</Button>
                                <Button variant="destructive" onClick={cancel}>Cancelar</Button>
                            </>
                        )}
                    </div>
                </div>

                {/* Info grid */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    {purchase.supplier && (
                        <div className="rounded-lg border bg-white p-4 shadow-sm space-y-1">
                            <h2 className="font-semibold text-gray-700">Proveedor</h2>
                            <p className="font-medium">
                                <Link href={`/admin/suppliers/${purchase.supplier.id}`} className="text-blue-600 hover:underline">{purchase.supplier.name}</Link>
                            </p>
                            {purchase.supplier.contact_name && <p className="text-sm text-gray-500">{purchase.supplier.contact_name}</p>}
                            {purchase.supplier.phone && <p className="text-sm text-gray-500">{purchase.supplier.phone}</p>}
                            {purchase.supplier.payment_terms && <p className="text-sm text-gray-500">Plazo: {purchase.supplier.payment_terms}</p>}
                        </div>
                    )}
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-1">
                        <h2 className="font-semibold text-gray-700">Factura</h2>
                        {purchase.invoice_number ? (
                            <>
                                <p className="font-mono font-medium">{purchase.invoice_number}</p>
                                {purchase.invoice_date && <p className="text-sm text-gray-500">Fecha: {purchase.invoice_date}</p>}
                            </>
                        ) : <p className="text-sm text-gray-400">Sin número de factura.</p>}
                        {purchase.store && <p className="mt-2 text-sm text-gray-500">Tienda: {purchase.store.name}</p>}
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <h2 className="mb-2 font-semibold text-gray-700">Documento adjunto</h2>
                        {purchase.document_path ? (
                            <p className="flex items-center gap-2 text-sm text-green-600"><FileText className="h-4 w-4" /> Documento cargado</p>
                        ) : (
                            <form onSubmit={uploadDoc} className="space-y-2">
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png" className="text-sm" onChange={(e) => setDocData('document', e.target.files?.[0] ?? null)} />
                                <Button type="submit" size="sm" disabled={postingDoc || !docData.document}>Subir documento</Button>
                            </form>
                        )}
                    </div>
                </div>

                {/* Partial receive panel */}
                {showPartial && (
                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-5 shadow-sm space-y-3">
                        <h2 className="font-semibold text-blue-800">Recepción parcial — ingresa las cantidades recibidas</h2>
                        <div className="space-y-2">
                            {purchase.items.map((item) => {
                                const pending = Math.max(0, parseFloat(item.quantity) - parseFloat(item.received_quantity ?? '0'));
                                return (
                                    <div key={item.id} className="grid grid-cols-12 items-center gap-3">
                                        <div className="col-span-5 text-sm font-medium">{item.product.name}</div>
                                        <div className="col-span-2 text-sm text-gray-500">Pedido: {item.quantity}</div>
                                        <div className="col-span-2 text-sm text-gray-500">Recibido: {item.received_quantity ?? '0'}</div>
                                        <div className="col-span-2 text-sm text-gray-500">Pendiente: {pending.toFixed(2)}</div>
                                        <div className="col-span-1">
                                            <Input
                                                type="number" min="0" max={pending} step="0.01"
                                                placeholder="0"
                                                value={partialQtys[item.id] ?? ''}
                                                onChange={(e) => setPartialQtys((prev) => ({ ...prev, [item.id]: e.target.value }))}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="flex gap-2">
                            <Button onClick={receivePartial}>Confirmar recepción parcial</Button>
                            <Button variant="outline" onClick={() => setShowPartial(false)}>Cancelar</Button>
                        </div>
                    </div>
                )}

                {/* Items table */}
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
                            {purchase.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-4 py-3 font-medium">{item.product.name}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-400">{item.product.sku ?? '—'}</td>
                                    <td className="px-4 py-3 text-right">{item.quantity}</td>
                                    <td className="px-4 py-3 text-right text-blue-600">{item.received_quantity ?? '—'}</td>
                                    <td className="px-4 py-3 text-right">{fmt(item.cost)}</td>
                                    <td className="px-4 py-3 text-right font-medium">{fmt(item.subtotal)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="border-t bg-gray-50 text-sm">
                            <tr>
                                <td colSpan={5} className="px-4 py-2 text-right text-gray-500">Subtotal</td>
                                <td className="px-4 py-2 text-right font-medium">{fmt(purchase.subtotal)}</td>
                            </tr>
                            <tr>
                                <td colSpan={5} className="px-4 py-2 text-right text-gray-500">IVA</td>
                                <td className="px-4 py-2 text-right font-medium">{fmt(purchase.tax)}</td>
                            </tr>
                            <tr>
                                <td colSpan={5} className="px-4 py-2 text-right font-bold">Total</td>
                                <td className="px-4 py-2 text-right text-lg font-bold">{fmt(purchase.total)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {/* Payable */}
                {purchase.payable && (
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b px-4 py-3">
                            <h2 className="font-semibold text-gray-700">Cuenta por pagar</h2>
                            <Link href={`/admin/payables/${purchase.payable.id}`} className="text-sm text-blue-600 hover:underline">Ver detalle →</Link>
                        </div>
                        <div className="grid grid-cols-3 divide-x text-center">
                            {[
                                { label: 'Total', value: fmt(purchase.payable.amount) },
                                { label: 'Pagado', value: fmt(purchase.payable.amount_paid) },
                                { label: 'Saldo', value: fmt(purchase.payable.balance) },
                            ].map((s) => (
                                <div key={s.label} className="px-4 py-3">
                                    <p className="text-xs uppercase text-gray-500">{s.label}</p>
                                    <p className="mt-1 text-lg font-semibold">{s.value}</p>
                                </div>
                            ))}
                        </div>
                        {purchase.payable.payments.length > 0 && (
                            <div className="border-t">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                        <tr>
                                            <th className="px-4 py-2">Fecha</th>
                                            <th className="px-4 py-2">Método</th>
                                            <th className="px-4 py-2">Registrado por</th>
                                            <th className="px-4 py-2 text-right">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {purchase.payable.payments.map((p) => (
                                            <tr key={p.id} className="hover:bg-gray-50">
                                                <td className="px-4 py-2 text-gray-500">{p.date}</td>
                                                <td className="px-4 py-2">{paymentMethodLabels[p.payment_method] ?? p.payment_method}</td>
                                                <td className="px-4 py-2 text-gray-500">{p.user.name}</td>
                                                <td className="px-4 py-2 text-right font-semibold text-green-600">{fmt(p.amount)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                {/* Notes + audit */}
                {(purchase.notes || purchase.audit_notes) && (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {purchase.notes && (
                            <div className="rounded-lg border bg-white p-4 shadow-sm">
                                <h2 className="mb-2 font-semibold text-gray-700">Notas</h2>
                                <p className="text-sm text-gray-600">{purchase.notes}</p>
                            </div>
                        )}
                        {purchase.audit_notes && (
                            <div className="rounded-lg border bg-white p-4 shadow-sm">
                                <h2 className="mb-2 font-semibold text-gray-700">Notas de auditoría</h2>
                                <p className="text-sm text-gray-600">{purchase.audit_notes}</p>
                            </div>
                        )}
                    </div>
                )}

                {/* Audit log */}
                {purchase.auditLogs.length > 0 && (
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-4 py-3 font-semibold text-gray-700">Historial de auditoría</div>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-4 py-3">Fecha</th>
                                    <th className="px-4 py-3">Acción</th>
                                    <th className="px-4 py-3">Descripción</th>
                                    <th className="px-4 py-3">Usuario</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {purchase.auditLogs.map((log) => (
                                    <tr key={log.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{log.created_at}</td>
                                        <td className="px-4 py-3"><span className="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs">{log.action}</span></td>
                                        <td className="px-4 py-3 text-gray-600">{log.description}</td>
                                        <td className="px-4 py-3 text-gray-500">{log.user.name}</td>
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
