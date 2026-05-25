import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Link, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, FileText, Pencil, Printer } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Sale {
    id: number; folio: string; subtotal: string; tax: string; discount: string; total: string;
    amount_paid: string; change_amount: string; payment_method: string; status: string; notes: string | null; created_at: string;
    user: { name: string };
    cash_shift: { id: number; cash_register: { name: string; store: { name: string } } };
    items: { id: number; quantity: string; price: string; discount: string; subtotal: string; product: { name: string; sku: string | null } }[];
}

interface SiatInvoice {
    id: number;
    numero_factura: number;
    cuf: string;
    estado: string;
    estado_label: string;
    tipo_fact_label: string;
    importe_total: string;
    nombre_razon_social: string;
}

function CancelSaleModal({ sale, siatInvoice, onClose }: {
    sale: Sale;
    siatInvoice: SiatInvoice | null;
    onClose: () => void;
}) {
    const { data, setData, patch, processing, errors } = useForm({ motivo: '' });
    const hasActiveInvoice = siatInvoice && siatInvoice.estado !== 'anulada';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl space-y-4">
                <h3 className="font-semibold text-gray-800">Cancelar Venta {sale.folio}</h3>

                {hasActiveInvoice && (
                    <div className="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3">
                        <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600 mt-0.5" />
                        <p className="text-xs text-amber-800">
                            Esta venta tiene la factura SIAT <strong>#{siatInvoice!.numero_factura}</strong> activa.
                            Al cancelar, la factura también será anulada automáticamente.
                        </p>
                    </div>
                )}

                <div>
                    <Label>Motivo de cancelación</Label>
                    <textarea
                        className="mt-1 w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
                        rows={3}
                        value={data.motivo}
                        onChange={(e) => setData('motivo', e.target.value)}
                        placeholder="Motivo de cancelación…"
                    />
                    {errors.motivo && <p className="mt-1 text-xs text-red-500">{errors.motivo}</p>}
                    {(errors as any).status && <p className="mt-1 text-xs text-red-500">{(errors as any).status}</p>}
                </div>

                <p className="text-xs text-gray-400">El stock de los productos será devuelto al inventario.</p>

                <div className="flex gap-2 justify-end pt-1">
                    <Button variant="outline" size="sm" onClick={onClose}>Cerrar</Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        disabled={processing}
                        onClick={() => patch(`/admin/sales/${sale.id}/cancel`, { onSuccess: onClose })}
                    >
                        Confirmar cancelación
                    </Button>
                </div>
            </div>
        </div>
    );
}

function EmitirFacturaModal({ saleId, onClose }: { saleId: number; onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm({
        nit_ci: '', tipo_doc: '5', nombre: 'Sin Nombre', tipo_factura: '2',
    });
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl space-y-4">
                <h3 className="font-semibold text-gray-800">Emitir Factura SIAT Bolivia</h3>
                <div>
                    <Label>Tipo de Factura</Label>
                    <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.tipo_factura} onChange={(e) => setData('tipo_factura', e.target.value)}>
                        <option value="2">Sin crédito fiscal (consumidor final)</option>
                        <option value="1">Con crédito fiscal (empresa con NIT)</option>
                    </select>
                </div>
                <div>
                    <Label>Tipo de Documento</Label>
                    <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.tipo_doc} onChange={(e) => setData('tipo_doc', e.target.value)}>
                        <option value="5">NIT</option>
                        <option value="1">CI Bolivia</option>
                        <option value="2">Pasaporte</option>
                        <option value="3">Carnet Extranjería</option>
                    </select>
                </div>
                <div>
                    <Label>NIT / CI del Comprador</Label>
                    <Input value={data.nit_ci} onChange={(e) => setData('nit_ci', e.target.value)} placeholder="0 = Sin nombre" />
                    {errors.nit_ci && <p className="text-xs text-red-500">{errors.nit_ci}</p>}
                </div>
                <div>
                    <Label>Nombre / Razón Social</Label>
                    <Input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                    {errors.nombre && <p className="text-xs text-red-500">{errors.nombre}</p>}
                </div>
                {errors.siat && (
                    <div className="rounded-md border border-red-200 bg-red-50 p-3">
                        <p className="text-xs text-red-700">{errors.siat}</p>
                        <a href="/admin/siat/settings" className="mt-1 block text-xs text-red-600 underline">
                            Ir a Configuración SIAT →
                        </a>
                    </div>
                )}
                <div className="flex gap-2 justify-end pt-2">
                    <Button variant="outline" size="sm" onClick={onClose}>Cancelar</Button>
                    <Button size="sm" disabled={processing}
                        onClick={() => post(`/admin/siat/sales/${saleId}/emit-invoice`, { onSuccess: onClose })}>
                        Emitir Factura
                    </Button>
                </div>
            </div>
        </div>
    );
}

const statusColors: Record<string, string> = { completed: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700', refunded: 'bg-orange-100 text-orange-700' };
const statusLabels: Record<string, string> = { completed: 'Completada', cancelled: 'Cancelada', refunded: 'Devuelta' };
const paymentLabels: Record<string, string> = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia', mixed: 'Mixto' };

function openReceipt(saleId: number) {
    window.open(`/admin/sales/${saleId}/receipt`, '_blank', 'width=420,height=700,scrollbars=yes');
}

export default function SaleShow({ sale, siatInvoice }: { sale: Sale; siatInvoice: SiatInvoice | null }) {
    const { flash, auth } = usePage<{ flash: { print_receipt?: boolean }; auth: { roles: string[] } }>().props;
    const canEdit = auth.roles.includes('admin') || auth.roles.includes('operador');
    const [showEmitModal, setShowEmitModal] = useState(false);
    const [showCancelModal, setShowCancelModal] = useState(false);

    const fmt = (v: string) => `$${parseFloat(v).toFixed(2)}`;

    useEffect(() => {
        if (flash?.print_receipt) {
            openReceipt(sale.id);
        }
    }, []);

    return (
        <AppLayout breadcrumbs={[{ title: 'Ventas', href: '/admin/sales' }, { title: sale.folio, href: '' }]}>
            <FlashMessage />
            {showEmitModal && <EmitirFacturaModal saleId={sale.id} onClose={() => setShowEmitModal(false)} />}
            {showCancelModal && (
                <CancelSaleModal sale={sale} siatInvoice={siatInvoice} onClose={() => setShowCancelModal(false)} />
            )}
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Venta {sale.folio}</h1>
                        <p className="text-gray-500">{new Date(sale.created_at).toLocaleString('es-BO')} — {sale.user.name}</p>
                        <p className="text-sm text-gray-400">
                            Caja: <Link href={`/admin/cash-shifts/${sale.cash_shift.id}`} className="text-blue-600 hover:underline">{sale.cash_shift.cash_register.name}</Link> — {sale.cash_shift.cash_register.store.name}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <span className={`rounded-full px-3 py-1 text-sm font-semibold ${statusColors[sale.status]}`}>{statusLabels[sale.status]}</span>
                        {canEdit && (
                            <Button variant="outline" asChild className="gap-2">
                                <Link href={`/admin/sales/${sale.id}/edit`}><Pencil className="h-4 w-4" /> Editar</Link>
                            </Button>
                        )}
                        <Button variant="outline" onClick={() => openReceipt(sale.id)} className="gap-2">
                            <Printer className="h-4 w-4" /> Recibo
                        </Button>
                        {!siatInvoice && sale.status === 'completed' && (
                            <Button variant="outline" className="gap-2 border-blue-200 text-blue-600 hover:bg-blue-50" onClick={() => setShowEmitModal(true)}>
                                <FileText className="h-4 w-4" /> Emitir Factura
                            </Button>
                        )}
                        {siatInvoice && (
                            <Link href={`/admin/siat/invoices/${siatInvoice.id}`}>
                                <Button variant="outline" className="gap-2 border-green-200 text-green-700 hover:bg-green-50">
                                    <FileText className="h-4 w-4" /> Ver Factura #{siatInvoice.numero_factura}
                                </Button>
                            </Link>
                        )}
                        {sale.status === 'completed' && (
                            <Button variant="destructive" onClick={() => setShowCancelModal(true)}>Cancelar</Button>
                        )}
                    </div>
                </div>

                {/* Banner factura SIAT */}
                {siatInvoice && (
                    <div className={`mb-4 rounded-lg border px-4 py-3 text-sm flex items-center justify-between ${siatInvoice.estado === 'anulada' ? 'border-red-200 bg-red-50' : siatInvoice.estado === 'enviada' ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'}`}>
                        <div className="flex items-center gap-2">
                            <FileText className="h-4 w-4" />
                            <span className="font-medium">Factura SIAT #{siatInvoice.numero_factura}</span>
                            <span className="text-xs text-gray-500">— {siatInvoice.nombre_razon_social} — {siatInvoice.tipo_fact_label}</span>
                        </div>
                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${siatInvoice.estado === 'anulada' ? 'bg-red-100 text-red-700' : siatInvoice.estado === 'enviada' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}`}>
                            {siatInvoice.estado_label}
                        </span>
                    </div>
                )}

                <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                    {[
                        { label: 'Total', value: fmt(sale.total), bold: true },
                        { label: 'Recibido', value: fmt(sale.amount_paid) },
                        { label: 'Cambio', value: fmt(sale.change_amount) },
                        { label: 'Método', value: paymentLabels[sale.payment_method] },
                    ].map((item) => (
                        <div key={item.label} className="rounded-lg border bg-white p-4 shadow-sm">
                            <p className="text-xs text-gray-500">{item.label}</p>
                            <p className={`mt-1 text-xl ${item.bold ? 'font-bold' : 'font-medium'}`}>{item.value}</p>
                        </div>
                    ))}
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3 text-right">Precio</th>
                                <th className="px-4 py-3 text-right">Descuento</th>
                                <th className="px-4 py-3 text-right">Cantidad</th>
                                <th className="px-4 py-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {sale.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">{item.product.name}</p>
                                        {item.product.sku && <p className="font-mono text-xs text-gray-400">{item.product.sku}</p>}
                                    </td>
                                    <td className="px-4 py-3 text-right">{fmt(item.price)}</td>
                                    <td className="px-4 py-3 text-right">{fmt(item.discount)}</td>
                                    <td className="px-4 py-3 text-right">{item.quantity}</td>
                                    <td className="px-4 py-3 text-right font-medium">{fmt(item.subtotal)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="border-t bg-gray-50">
                            <tr><td colSpan={4} className="px-4 py-2 text-right text-sm text-gray-500">Subtotal</td><td className="px-4 py-2 text-right">{fmt(sale.subtotal)}</td></tr>
                            <tr><td colSpan={4} className="px-4 py-2 text-right text-sm text-gray-500">Descuento</td><td className="px-4 py-2 text-right text-red-600">-{fmt(sale.discount)}</td></tr>
                            <tr><td colSpan={4} className="px-4 py-2 text-right text-sm text-gray-500">IVA</td><td className="px-4 py-2 text-right">{fmt(sale.tax)}</td></tr>
                            <tr><td colSpan={4} className="px-4 py-2 text-right font-bold">Total</td><td className="px-4 py-2 text-right text-lg font-bold">{fmt(sale.total)}</td></tr>
                        </tfoot>
                    </table>
                </div>
                {sale.notes && <div className="mt-4 rounded-lg border bg-white p-4 shadow-sm"><p className="text-sm font-semibold text-gray-600">Notas:</p><p className="text-sm text-gray-500">{sale.notes}</p></div>}
            </div>
        </AppLayout>
    );
}
