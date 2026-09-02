import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';
import { Ban, RefreshCw, RotateCcw, SearchCheck } from 'lucide-react';
import { useState } from 'react';

interface Nota {
    id: number;
    numero_nota: number;
    documento_sector: number;
    sector_label: string;
    cuf: string;
    cufd: string;
    fecha_emision: string;
    nit_ci: string;
    nombre_razon_social: string;
    monto_total_original: string;
    monto_total_devuelto: string;
    monto_descuento: string;
    monto_efectivo: string;
    descuento_adicional: string | null;
    estado: string;
    estado_label: string;
    codigo_recepcion: string | null;
    codigo_qr: string | null;
    mensaje_error: string | null;
    enviado_at: string | null;
    anulado_at: string | null;
    motivo_anulacion: number | null;
    store: { id: number; name: string } | null;
    invoice: { id: number; numero_factura: number; cuf: string; importe_total: string } | null;
    sale_return: {
        id: number;
        folio: string;
        reason: string | null;
        sale: { id: number; folio: string } | null;
        items: { id: number; quantity: string; unit_price: string; subtotal: string; product: { name: string; sku: string | null } }[];
    } | null;
}

const estadoColors: Record<string, string> = {
    pendiente: 'bg-amber-100 text-amber-700',
    enviada: 'bg-green-100 text-green-700',
    validada: 'bg-green-100 text-green-700',
    rechazada: 'bg-red-100 text-red-700',
    anulada: 'bg-gray-200 text-gray-700',
};

export default function SiatNotaShow({ nota }: { nota: Nota }) {
    const [ocupado, setOcupado] = useState<string | null>(null);

    const fmt = (v: string | null) => (v === null ? '—' : `Bs ${parseFloat(v).toFixed(2)}`);
    const fmtDate = (d: string | null) => (d ? new Date(d).toLocaleString('es-BO') : '—');

    const accion = (ruta: string, confirmacion?: string, datos: { codigo_motivo?: number } = {}) => {
        if (confirmacion && !window.confirm(confirmacion)) return;
        setOcupado(ruta);
        router.post(`/admin/siat/notas/${nota.id}/${ruta}`, datos, {
            preserveScroll: true,
            onFinish: () => setOcupado(null),
        });
    };

    const puedeReenviar = ['pendiente', 'rechazada'].includes(nota.estado);
    const puedeAnular = ['enviada', 'validada'].includes(nota.estado) && !!nota.codigo_recepcion;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'SIAT Bolivia', href: '' },
                { title: 'Notas de Crédito-Débito', href: '/admin/siat/notas' },
                { title: `#${nota.numero_nota}`, href: '' },
            ]}
        >
            <FlashMessage />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-bold">Nota #{nota.numero_nota}</h1>
                            <span
                                className={`rounded-full px-3 py-1 text-sm font-semibold ${
                                    estadoColors[nota.estado] ?? 'bg-gray-100 text-gray-600'
                                }`}
                            >
                                {nota.estado_label}
                            </span>
                            <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                Sector {nota.documento_sector} · {nota.sector_label}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">
                            Emitida el {fmtDate(nota.fecha_emision)}
                            {nota.store && <> · {nota.store.name}</>}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {puedeReenviar && (
                            <Button disabled={ocupado === 'resend'} onClick={() => accion('resend')}>
                                <RefreshCw className={`mr-2 h-4 w-4 ${ocupado === 'resend' ? 'animate-spin' : ''}`} />
                                Reenviar al SIN
                            </Button>
                        )}
                        {puedeAnular && (
                            <>
                                <Button variant="outline" onClick={() => accion('check-status')}>
                                    <SearchCheck className="mr-2 h-4 w-4" />
                                    Consultar estado
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() =>
                                        accion('cancel', '¿Anular esta nota ante el SIN?', { codigo_motivo: 2 })
                                    }
                                >
                                    <Ban className="mr-2 h-4 w-4" />
                                    Anular
                                </Button>
                            </>
                        )}
                        {nota.estado === 'anulada' && (
                            <Button
                                variant="outline"
                                onClick={() => accion('revert-cancellation', '¿Deshacer la anulación?')}
                            >
                                <RotateCcw className="mr-2 h-4 w-4" />
                                Revertir anulación
                            </Button>
                        )}
                    </div>
                </div>

                {nota.mensaje_error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p className="font-semibold">El SIN rechazó la nota</p>
                        <p className="mt-1">{nota.mensaje_error}</p>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase text-gray-400">Total original</p>
                        <p className="text-xl font-bold">{fmt(nota.monto_total_original)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase text-gray-400">Devuelto</p>
                        <p className="text-xl font-bold">{fmt(nota.monto_total_devuelto)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase text-gray-400">Crédito fiscal revertido</p>
                        <p className="text-xl font-bold">{fmt(nota.monto_efectivo)}</p>
                        <p className="mt-1 text-xs text-gray-400">13 % de lo devuelto</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase text-gray-400">Descuento prorrateado</p>
                        <p className="text-xl font-bold">{fmt(nota.monto_descuento)}</p>
                        {nota.descuento_adicional && (
                            <p className="mt-1 text-xs text-gray-400">
                                Descuento de la factura: {fmt(nota.descuento_adicional)}
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div className="space-y-1 rounded-lg border bg-white p-4 text-sm shadow-sm">
                        <p className="mb-2 font-semibold text-gray-700">Documento</p>
                        <p className="break-all">
                            <span className="text-gray-400">CUF:</span> {nota.cuf}
                        </p>
                        <p className="break-all">
                            <span className="text-gray-400">CUFD:</span> {nota.cufd}
                        </p>
                        <p>
                            <span className="text-gray-400">Recepción:</span> {nota.codigo_recepcion ?? '—'}
                        </p>
                        <p>
                            <span className="text-gray-400">Enviada:</span> {fmtDate(nota.enviado_at)}
                        </p>
                        {nota.anulado_at && (
                            <p>
                                <span className="text-gray-400">Anulada:</span> {fmtDate(nota.anulado_at)} (motivo{' '}
                                {nota.motivo_anulacion})
                            </p>
                        )}
                    </div>

                    <div className="space-y-1 rounded-lg border bg-white p-4 text-sm shadow-sm">
                        <p className="mb-2 font-semibold text-gray-700">Origen</p>
                        <p>
                            <span className="text-gray-400">Cliente:</span> {nota.nombre_razon_social} ({nota.nit_ci})
                        </p>
                        {nota.invoice && (
                            <p>
                                <span className="text-gray-400">Factura ajustada:</span>{' '}
                                <Link
                                    href={`/admin/siat/invoices/${nota.invoice.id}`}
                                    className="text-blue-600 hover:underline"
                                >
                                    #{nota.invoice.numero_factura}
                                </Link>{' '}
                                por {fmt(nota.invoice.importe_total)}
                            </p>
                        )}
                        {nota.sale_return && (
                            <>
                                <p>
                                    <span className="text-gray-400">Devolución:</span>{' '}
                                    <Link
                                        href={`/admin/returns/${nota.sale_return.id}`}
                                        className="text-blue-600 hover:underline"
                                    >
                                        {nota.sale_return.folio}
                                    </Link>
                                </p>
                                {nota.sale_return.reason && (
                                    <p>
                                        <span className="text-gray-400">Motivo:</span> {nota.sale_return.reason}
                                    </p>
                                )}
                            </>
                        )}
                    </div>
                </div>

                {nota.sale_return && (
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
                                {nota.sale_return.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-3">
                                            {item.product.name}
                                            {item.product.sku && (
                                                <span className="ml-2 text-xs text-gray-400">{item.product.sku}</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">{parseFloat(item.quantity)}</td>
                                        <td className="px-4 py-3 text-right">{fmt(item.unit_price)}</td>
                                        <td className="px-4 py-3 text-right">{fmt(item.subtotal)}</td>
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
