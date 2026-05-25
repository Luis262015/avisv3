import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Link, router, useForm } from '@inertiajs/react';
import { Ban, ExternalLink, Printer, RefreshCw, ShoppingCart } from 'lucide-react';
import { useState } from 'react';

interface SiatInvoice {
    id: number;
    numero_factura: number;
    cuf: string;
    cufd: string;
    nit_ci: string;
    tipo_doc_identidad: number;
    tipo_doc_label: string;
    nombre_razon_social: string;
    importe_total: string;
    importe_base_cf: string;
    descuento: string;
    tipo_factura: number;
    tipo_fact_label: string;
    tipo_emision: number;
    metodo_pago_label: string;
    estado: string;
    estado_label: string;
    codigo_recepcion: string | null;
    codigo_qr: string | null;
    mensaje_error: string | null;
    enviado_at: string | null;
    anulado_at: string | null;
    motivo_anulacion: string | null;
    created_at: string;
    sale: {
        id: number;
        folio: string;
        status: string;
        items: { id: number; product: { name: string; sku: string | null }; quantity: string; price: string; subtotal: string }[];
        user: { name: string };
    };
    store: { id: number; name: string };
}

const estadoColors: Record<string, string> = {
    pendiente: 'bg-amber-100 text-amber-700',
    enviada: 'bg-green-100 text-green-700',
    anulada: 'bg-red-100 text-red-700',
    contingencia: 'bg-blue-100 text-blue-700',
};

function CancelModal({ invoiceId, saleStatus, onClose }: {
    invoiceId: number;
    saleStatus: string;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({ motivo: '', cancel_sale: false });
    const canCancelSale = saleStatus === 'completed';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl space-y-4">
                <h3 className="font-semibold text-gray-800">Anular Factura</h3>
                <div>
                    <p className="mb-2 text-sm text-gray-500">Ingrese el motivo de anulación:</p>
                    <textarea
                        className="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
                        rows={3}
                        value={data.motivo}
                        onChange={(e) => setData('motivo', e.target.value)}
                        placeholder="Motivo de anulación…"
                    />
                    {errors.motivo && <p className="mt-1 text-xs text-red-500">{errors.motivo}</p>}
                    {(errors as any).siat && <p className="mt-1 text-xs text-red-500">{(errors as any).siat}</p>}
                </div>

                {canCancelSale && (
                    <label className="flex items-start gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            className="mt-0.5 h-4 w-4 rounded border-gray-300"
                            checked={data.cancel_sale}
                            onChange={(e) => setData('cancel_sale', e.target.checked)}
                        />
                        <span className="text-sm text-gray-600">
                            Cancelar también la venta vinculada y devolver inventario
                        </span>
                    </label>
                )}

                <div className="flex gap-2 justify-end pt-1">
                    <Button variant="outline" size="sm" onClick={onClose}>Cerrar</Button>
                    <Button variant="destructive" size="sm" disabled={processing}
                        onClick={() => post(`/admin/siat/invoices/${invoiceId}/cancel`, { onSuccess: onClose })}>
                        Anular
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default function SiatInvoiceShow({ invoice }: { invoice: SiatInvoice }) {
    const [showCancelModal, setShowCancelModal] = useState(false);

    const fmt = (v: string) => `Bs ${parseFloat(v).toFixed(2)}`;
    const fmtDate = (d: string) => new Date(d).toLocaleString('es-BO');

    const resend = () => {
        router.post(`/admin/siat/invoices/${invoice.id}/resend`);
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'SIAT Bolivia', href: '' },
            { title: 'Facturas', href: '/admin/siat/invoices' },
            { title: `#${invoice.numero_factura}`, href: '' },
        ]}>
            <FlashMessage />
            {showCancelModal && (
                <CancelModal
                    invoiceId={invoice.id}
                    saleStatus={invoice.sale?.status ?? ''}
                    onClose={() => setShowCancelModal(false)}
                />
            )}

            <div className="p-6">
                {/* Encabezado */}
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Factura #{invoice.numero_factura}</h1>
                        <p className="text-sm text-gray-500">{fmtDate(invoice.created_at)} — {invoice.store.name}</p>
                        <div className="mt-2 flex items-center gap-2">
                            <span className={`rounded-full px-3 py-1 text-sm font-semibold ${estadoColors[invoice.estado] ?? 'bg-gray-100 text-gray-600'}`}>
                                {invoice.estado_label}
                            </span>
                            <span className={`rounded-full px-3 py-1 text-sm ${invoice.tipo_factura === 1 ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600'}`}>
                                {invoice.tipo_fact_label}
                            </span>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a href={`/admin/siat/invoices/${invoice.id}/print`} target="_blank" rel="noreferrer">
                            <Button variant="outline" className="gap-2"><Printer className="h-4 w-4" /> Imprimir Factura</Button>
                        </a>
                        {(invoice.estado === 'pendiente' || invoice.estado === 'contingencia') && (
                            <Button variant="outline" className="gap-2" onClick={resend}>
                                <RefreshCw className="h-4 w-4" /> Reenviar a SIN
                            </Button>
                        )}
                        {invoice.estado !== 'anulada' && (
                            <Button variant="destructive" className="gap-2" onClick={() => setShowCancelModal(true)}>
                                <Ban className="h-4 w-4" /> Anular
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* CUF y datos técnicos */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="rounded-lg border bg-white p-5 shadow-sm">
                            <h2 className="mb-3 font-semibold text-gray-700">Datos de la Factura</h2>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between border-b pb-2">
                                    <span className="text-gray-500">Número de Factura</span>
                                    <span className="font-bold text-lg text-blue-700">#{invoice.numero_factura}</span>
                                </div>
                                <div className="border-b pb-2">
                                    <p className="text-gray-500 text-xs mb-0.5">CUF (Código Único de Factura)</p>
                                    <p className="font-mono text-xs break-all bg-gray-50 rounded p-2">{invoice.cuf}</p>
                                </div>
                                <div className="border-b pb-2">
                                    <p className="text-gray-500 text-xs mb-0.5">CUFD utilizado</p>
                                    <p className="font-mono text-xs break-all text-gray-400">{invoice.cufd.substring(0, 64)}…</p>
                                </div>
                                {invoice.codigo_recepcion && (
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Código Recepción SIN</span>
                                        <span className="font-mono text-xs">{invoice.codigo_recepcion}</span>
                                    </div>
                                )}
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Método de Pago</span>
                                    <span>{invoice.metodo_pago_label}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Tipo de Emisión</span>
                                    <span>{invoice.tipo_emision === 1 ? 'En línea' : 'Fuera de línea'}</span>
                                </div>
                                {invoice.enviado_at && (
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Enviado al SIN</span>
                                        <span className="text-green-600">{fmtDate(invoice.enviado_at)}</span>
                                    </div>
                                )}
                                {invoice.mensaje_error && (
                                    <div className="rounded-md bg-red-50 p-3">
                                        <p className="text-xs font-semibold text-red-700">Error SIN:</p>
                                        <p className="text-xs text-red-600">{invoice.mensaje_error}</p>
                                    </div>
                                )}
                                {invoice.anulado_at && (
                                    <div className="rounded-md bg-gray-50 p-3">
                                        <p className="text-xs font-semibold text-gray-700">Anulada: {fmtDate(invoice.anulado_at)}</p>
                                        {invoice.motivo_anulacion && <p className="text-xs text-gray-500">{invoice.motivo_anulacion}</p>}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Detalle de la venta */}
                        <div className="rounded-lg border bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b px-5 py-3">
                                <h2 className="font-semibold text-gray-700">Detalle de Venta</h2>
                                <Link href={`/admin/sales/${invoice.sale.id}`} className="flex items-center gap-1 text-xs text-blue-600 hover:underline">
                                    <ShoppingCart className="h-3 w-3" /> {invoice.sale.folio}
                                </Link>
                            </div>
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-2 text-left">Producto</th>
                                        <th className="px-4 py-2 text-right">Cant.</th>
                                        <th className="px-4 py-2 text-right">P/U</th>
                                        <th className="px-4 py-2 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {invoice.sale.items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-2">
                                                <p>{item.product.name}</p>
                                                {item.product.sku && <p className="text-xs text-gray-400">{item.product.sku}</p>}
                                            </td>
                                            <td className="px-4 py-2 text-right">{item.quantity}</td>
                                            <td className="px-4 py-2 text-right">{fmt(item.price)}</td>
                                            <td className="px-4 py-2 text-right font-medium">{fmt(item.subtotal)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Panel lateral */}
                    <div className="space-y-4">
                        {/* Comprador */}
                        <div className="rounded-lg border bg-white p-5 shadow-sm">
                            <h2 className="mb-3 font-semibold text-gray-700">Datos del Comprador</h2>
                            <div className="space-y-2 text-sm">
                                <div>
                                    <p className="text-xs text-gray-400">{invoice.tipo_doc_label}</p>
                                    <p className="font-mono font-semibold">{invoice.nit_ci === '0' ? '—' : invoice.nit_ci}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-400">Nombre / Razón Social</p>
                                    <p className="font-medium">{invoice.nombre_razon_social}</p>
                                </div>
                            </div>
                        </div>

                        {/* Montos */}
                        <div className="rounded-lg border bg-white p-5 shadow-sm">
                            <h2 className="mb-3 font-semibold text-gray-700">Montos (Bs.)</h2>
                            <div className="space-y-2 text-sm">
                                {parseFloat(invoice.descuento) > 0 && (
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Descuento</span>
                                        <span className="text-red-600">- {fmt(invoice.descuento)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between">
                                    <span className="text-gray-500">Base Crédito Fiscal</span>
                                    <span>{fmt(invoice.importe_base_cf)}</span>
                                </div>
                                <div className="flex justify-between border-t pt-2 font-bold text-base">
                                    <span>TOTAL</span>
                                    <span className="text-blue-700">{fmt(invoice.importe_total)}</span>
                                </div>
                            </div>
                        </div>

                        {/* QR */}
                        {invoice.codigo_qr && (
                            <div className="rounded-lg border bg-white p-5 shadow-sm">
                                <h2 className="mb-3 font-semibold text-gray-700">Verificación SIN</h2>
                                <a
                                    href={invoice.codigo_qr}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center gap-1.5 text-sm text-blue-600 hover:underline break-all"
                                >
                                    <ExternalLink className="h-3.5 w-3.5 shrink-0" />
                                    Verificar en portal SIN
                                </a>
                                <p className="mt-2 text-xs text-gray-400 break-all">{invoice.codigo_qr}</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
