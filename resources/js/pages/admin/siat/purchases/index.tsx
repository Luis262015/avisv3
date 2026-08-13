import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, Check, PackageCheck, Send, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

interface Compra {
    id: number;
    folio: string;
    invoice_number: string | null;
    invoice_date: string | null;
    proveedor: string | null;
    nit: string | null;
    codigo_autorizacion: string | null;
    total: string;
    tipo_compra: number | null;
    paquete_id: number | null;
    problemas: string[];
}

interface Paquete {
    id: number;
    gestion: number | null;
    periodo: number | null;
    cantidad_facturas: number;
    codigo_recepcion: string | null;
    codigo_estado: number | null;
    estado: string;
    estado_label: string;
    mensaje_error: string | null;
    enviado_at: string | null;
}

interface Props {
    compras: Compra[];
    paquetes: Paquete[];
    filtros: { gestion: number; periodo: number };
    setting: { id: number; ambiente: string } | null;
    settings: { id: number; label: string }[];
}

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

const estadoPaquete: Record<string, string> = {
    pendiente: 'bg-gray-100 text-gray-600',
    enviado: 'bg-amber-100 text-amber-700',
    validado: 'bg-green-100 text-green-700',
    rechazado: 'bg-red-100 text-red-700',
};

/**
 * Los datos que el SIN pide y el módulo de Compras no guardaba. Se editan en la
 * misma fila: mandar al usuario a otra pantalla por cada compra incompleta haría
 * el cierre de periodo interminable.
 */
function FilaCompra({ compra }: { compra: Compra }) {
    const [abierto, setAbierto] = useState(false);
    const { data, setData, put, processing } = useForm({
        codigo_autorizacion: compra.codigo_autorizacion ?? '',
        invoice_number: compra.invoice_number ?? '',
        nit_proveedor: compra.nit ?? '',
        tipo_compra: compra.tipo_compra ?? 1,
    });

    const declarada = compra.paquete_id !== null;
    const listo = compra.problemas.length === 0;

    return (
        <>
            <tr className={`hover:bg-gray-50 ${declarada ? 'opacity-60' : ''}`}>
                <td className="px-3 py-2">
                    <p className="font-medium">{compra.proveedor ?? '—'}</p>
                    <p className="text-xs text-gray-400">{compra.folio} · NIT {compra.nit ?? '—'}</p>
                </td>
                <td className="px-3 py-2 text-sm">{compra.invoice_number ?? '—'}</td>
                <td className="px-3 py-2 text-xs text-gray-500">{compra.invoice_date ?? '—'}</td>
                <td className="px-3 py-2 font-mono text-xs text-gray-500">
                    {compra.codigo_autorizacion
                        ? `${compra.codigo_autorizacion.substring(0, 14)}…`
                        : <span className="text-red-500">falta</span>}
                </td>
                <td className="px-3 py-2 text-right font-medium">Bs {parseFloat(compra.total).toFixed(2)}</td>
                <td className="px-3 py-2 text-center">
                    {declarada
                        ? <span className="inline-flex items-center gap-1 text-xs text-green-600">
                            <Check className="h-3.5 w-3.5" /> Declarada
                          </span>
                        : listo
                            ? <span className="text-xs text-gray-500">Lista</span>
                            : <span className="text-xs text-amber-600">Incompleta</span>}
                </td>
                <td className="px-3 py-2 text-right">
                    {!declarada && (
                        <Button size="sm" variant="ghost" onClick={() => setAbierto((v) => !v)}>
                            {abierto ? 'Cerrar' : 'Completar'}
                        </Button>
                    )}
                </td>
            </tr>

            {compra.problemas.length > 0 && !abierto && (
                <tr>
                    <td colSpan={7} className="bg-amber-50/60 px-3 pb-2 text-xs text-amber-700">
                        Falta: {compra.problemas.join('; ')}.
                    </td>
                </tr>
            )}

            {abierto && (
                <tr>
                    <td colSpan={7} className="bg-gray-50 px-3 py-3">
                        <div className="grid gap-3 sm:grid-cols-5">
                            <div className="sm:col-span-2">
                                <label className="mb-1 block text-xs text-gray-500">Código de autorización (CUF)</label>
                                <Input value={data.codigo_autorizacion}
                                    onChange={(e) => setData('codigo_autorizacion', e.target.value)} />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs text-gray-500">Nº de factura</label>
                                <Input value={data.invoice_number}
                                    onChange={(e) => setData('invoice_number', e.target.value)} />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs text-gray-500">NIT del proveedor</label>
                                <Input value={data.nit_proveedor}
                                    onChange={(e) => setData('nit_proveedor', e.target.value)} />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs text-gray-500">Tipo de compra</label>
                                <Input type="number" value={data.tipo_compra}
                                    onChange={(e) => setData('tipo_compra', Number(e.target.value))} />
                            </div>
                        </div>
                        <div className="mt-3 flex justify-end">
                            <Button size="sm" disabled={processing}
                                onClick={() => put(`/admin/siat/purchase-registry/${compra.id}`,
                                    { preserveScroll: true, onSuccess: () => setAbierto(false) })}>
                                Guardar
                            </Button>
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
}

export default function SiatPurchaseRegistryIndex({ compras, paquetes, filtros, setting, settings }: Props) {
    const [busy, setBusy] = useState<string | null>(null);

    const pendientes = compras.filter((c) => c.paquete_id === null);
    const incompletas = pendientes.filter((c) => c.problemas.length > 0);
    const declarables = pendientes.length - incompletas.length;

    const navegar = (extra: Record<string, unknown>) =>
        router.get('/admin/siat/purchase-registry',
            { ...filtros, setting_id: setting?.id, ...extra },
            { preserveState: true, preserveScroll: true });

    const enviar = (url: string, clave: string) => {
        setBusy(clave);
        router.post(url, { setting_id: setting?.id, ...filtros },
            { preserveScroll: true, onFinish: () => setBusy(null) });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'SIAT Bolivia', href: '' }, { title: 'Registro de Compras', href: '' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Registro de Compras</h1>
                        <p className="text-sm text-gray-500">
                            Declaración ante el SIN de las compras del periodo, por la fecha de la factura del proveedor.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {settings.length > 1 && (
                            <select className="rounded-md border px-3 py-2 text-sm" value={setting?.id ?? ''}
                                onChange={(e) => navegar({ setting_id: Number(e.target.value) })}>
                                {settings.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
                            </select>
                        )}
                        <select className="rounded-md border px-3 py-2 text-sm" value={filtros.periodo}
                            onChange={(e) => navegar({ periodo: Number(e.target.value) })}>
                            {MESES.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
                        </select>
                        <Input type="number" className="w-24" value={filtros.gestion}
                            onChange={(e) => navegar({ gestion: Number(e.target.value) })} />
                    </div>
                </div>

                {!setting ? (
                    <div className="rounded-lg border-2 border-dashed border-gray-200 py-16 text-center text-gray-400">
                        No hay configuración SIAT activa.
                    </div>
                ) : (
                    <div className="space-y-6">
                        {incompletas.length > 0 && (
                            <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-4">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                                <div className="text-sm text-amber-800">
                                    <p className="font-medium">
                                        {incompletas.length} compra(s) sin los datos que exige el SIN
                                    </p>
                                    <p className="text-xs">
                                        El paquete es todo o nada: una sola compra incompleta tumba la declaración
                                        entera, así que complétalas antes de enviar.
                                    </p>
                                </div>
                            </div>
                        )}

                        <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border bg-white p-5 shadow-sm">
                            <div className="text-sm text-gray-600">
                                <span className="font-semibold">{MESES[filtros.periodo - 1]} {filtros.gestion}</span>
                                {' — '}{pendientes.length} sin declarar, {declarables} lista(s) para enviar.
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button className="gap-2"
                                    disabled={busy !== null || declarables === 0 || incompletas.length > 0}
                                    onClick={() => enviar('/admin/siat/purchase-registry/send', 'send')}>
                                    <Send className="h-4 w-4" />
                                    {busy === 'send' ? 'Enviando…' : `Declarar ${declarables} compra(s)`}
                                </Button>
                                <Button variant="outline" className="gap-2" disabled={busy !== null}
                                    onClick={() => enviar('/admin/siat/purchase-registry/confirm', 'confirm')}>
                                    <ShieldCheck className="h-4 w-4" />
                                    {busy === 'confirm' ? 'Confirmando…' : 'Cerrar periodo'}
                                </Button>
                            </div>
                        </div>

                        <div className="overflow-hidden rounded-lg border bg-white shadow-sm">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-3 py-3 text-left">Proveedor</th>
                                        <th className="px-3 py-3 text-left">Nº factura</th>
                                        <th className="px-3 py-3 text-left">Fecha</th>
                                        <th className="px-3 py-3 text-left">Autorización</th>
                                        <th className="px-3 py-3 text-right">Total</th>
                                        <th className="px-3 py-3 text-center">Estado</th>
                                        <th className="px-3 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {compras.map((c) => <FilaCompra key={c.id} compra={c} />)}
                                    {compras.length === 0 && (
                                        <tr><td colSpan={7} className="px-4 py-10 text-center text-gray-400">
                                            No hay compras registradas en ese periodo.
                                        </td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="overflow-hidden rounded-lg border bg-white shadow-sm">
                            <h2 className="border-b px-5 py-3 font-semibold text-gray-700">Periodos declarados</h2>
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-2 text-left">Periodo</th>
                                        <th className="px-4 py-2 text-center">Compras</th>
                                        <th className="px-4 py-2 text-left">Código recepción</th>
                                        <th className="px-4 py-2 text-center">Estado</th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {paquetes.map((p) => (
                                        <tr key={p.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-2">
                                                {p.periodo ? MESES[p.periodo - 1] : '—'} {p.gestion ?? ''}
                                            </td>
                                            <td className="px-4 py-2 text-center">{p.cantidad_facturas}</td>
                                            <td className="px-4 py-2 font-mono text-xs text-gray-500">
                                                {p.codigo_recepcion ?? '—'}
                                            </td>
                                            <td className="px-4 py-2 text-center">
                                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${estadoPaquete[p.estado] ?? 'bg-gray-100'}`}>
                                                    {p.estado_label}
                                                </span>
                                                {p.codigo_estado && (
                                                    <span className="ml-1 text-xs text-gray-400">({p.codigo_estado})</span>
                                                )}
                                                {p.mensaje_error && (
                                                    <p className="mt-1 text-xs text-red-600">{p.mensaje_error}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {p.codigo_recepcion && (
                                                    <Button size="sm" variant="outline" className="gap-1.5"
                                                        disabled={busy !== null}
                                                        onClick={() => {
                                                            setBusy(`val:${p.id}`);
                                                            router.post(`/admin/siat/purchase-registry/packages/${p.id}/validate`,
                                                                {}, { preserveScroll: true, onFinish: () => setBusy(null) });
                                                        }}>
                                                        <PackageCheck className="h-3.5 w-3.5" />
                                                        {busy === `val:${p.id}` ? 'Consultando…' : 'Consultar'}
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {paquetes.length === 0 && (
                                        <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">
                                            Todavía no se ha declarado ningún periodo.
                                        </td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
