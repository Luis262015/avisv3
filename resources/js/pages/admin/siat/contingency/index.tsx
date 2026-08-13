import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { router, useForm } from '@inertiajs/react';
import { CloudOff, Layers, PackageCheck, Play, Send, Square } from 'lucide-react';
import { useState } from 'react';

interface Evento {
    id: number;
    codigo_motivo_evento: number;
    descripcion: string;
    cafc: string | null;
    fecha_inicio: string;
    fecha_fin: string | null;
    codigo_recepcion_evento: string | null;
    estado: string;
    estado_label: string;
    mensaje_error: string | null;
    facturas_totales: number;
    facturas_pendientes: number;
    cufd_code: { codigo: string } | null;
}

interface Paquete {
    id: number;
    tipo: string;
    cantidad_facturas: number;
    codigo_recepcion: string | null;
    estado: string;
    estado_label: string;
    mensaje_error: string | null;
    enviado_at: string | null;
    created_at: string;
}

interface Props {
    eventos: Evento[];
    paquetes: Paquete[];
    motivos: { opciones: { codigo: number; descripcion: string }[]; error: string | null };
    masivas_pendientes: number;
    setting: { id: number; ambiente: string } | null;
    settings: { id: number; label: string }[];
}

const estadoEvento: Record<string, string> = {
    abierto: 'bg-red-100 text-red-700',
    cerrado: 'bg-amber-100 text-amber-700',
    registrado: 'bg-green-100 text-green-700',
};

const estadoPaquete: Record<string, string> = {
    pendiente: 'bg-gray-100 text-gray-600',
    enviado: 'bg-amber-100 text-amber-700',
    validado: 'bg-green-100 text-green-700',
    rechazado: 'bg-red-100 text-red-700',
};

function AbrirCorte({ settingId, motivos }: { settingId: number; motivos: Props['motivos'] }) {
    const { data, setData, post, processing, errors } = useForm({
        setting_id: settingId,
        codigo_motivo_evento: '' as number | '',
        descripcion: '',
        cafc: '',
    });

    return (
        <div className="rounded-lg border bg-white p-5 shadow-sm">
            <h2 className="mb-1 font-semibold text-gray-700">Abrir un corte</h2>
            <p className="mb-4 text-xs text-gray-500">
                A partir de aquí las ventas se facturan fuera de línea y quedan esperando al paquete.
                Ciérrelo en cuanto vuelva el servicio. El CAFC solo se rellena si el Portal SIAT entregó
                uno para este motivo: enviarlo de más hace que el SIN observe todo el lote.
            </p>

            <div className="grid gap-3 sm:grid-cols-[16rem_1fr_12rem_auto]">
                {motivos.opciones.length > 0 ? (
                    <select
                        className="rounded-md border px-3 py-2 text-sm"
                        value={data.codigo_motivo_evento}
                        onChange={(e) => setData('codigo_motivo_evento', Number(e.target.value))}
                    >
                        <option value="">Motivo del corte…</option>
                        {motivos.opciones.map((m) => (
                            <option key={m.codigo} value={m.codigo}>{m.codigo} — {m.descripcion}</option>
                        ))}
                    </select>
                ) : (
                    // Durante un corte el SIN no responde, así que la paramétrica puede
                    // no estar disponible justo cuando hace falta.
                    <Input
                        type="number"
                        placeholder="Código de motivo"
                        value={data.codigo_motivo_evento}
                        onChange={(e) => setData('codigo_motivo_evento', Number(e.target.value))}
                    />
                )}

                <Input
                    placeholder="Descripción (qué pasó exactamente)"
                    value={data.descripcion}
                    onChange={(e) => setData('descripcion', e.target.value)}
                />

                {/* Solo lo exigen algunos motivos. Mandarlo cuando no toca hace que
                    el SIN observe el paquete entero (1045), así que va vacío salvo
                    que el Portal SIAT haya entregado uno para este corte. */}
                <Input
                    placeholder="CAFC (si aplica)"
                    value={data.cafc}
                    onChange={(e) => setData('cafc', e.target.value)}
                />

                <Button
                    className="gap-2"
                    disabled={processing || !data.codigo_motivo_evento || !data.descripcion}
                    onClick={() => post('/admin/siat/contingency', { preserveScroll: true })}
                >
                    <Play className="h-4 w-4" /> Abrir corte
                </Button>
            </div>

            {(errors.codigo_motivo_evento || errors.descripcion || (errors as Record<string, string>).siat) && (
                <p className="mt-2 text-xs text-red-600">
                    {errors.codigo_motivo_evento ?? errors.descripcion ?? (errors as Record<string, string>).siat}
                </p>
            )}
        </div>
    );
}

export default function SiatContingencyIndex({ eventos, paquetes, motivos, masivas_pendientes, setting, settings }: Props) {
    const [busy, setBusy] = useState<string | null>(null);

    const abierto = eventos.find((e) => e.estado === 'abierto') ?? null;
    const fmtDate = (d: string | null) => (d ? new Date(d).toLocaleString('es-BO') : '—');

    const call = (clave: string, url: string) => {
        setBusy(clave);
        router.post(url, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'SIAT Bolivia', href: '' }, { title: 'Contingencia', href: '' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Contingencia y envío por lotes</h1>
                        <p className="text-sm text-gray-500">
                            Cortes de servicio, eventos significativos y paquetes de facturas.
                        </p>
                    </div>
                    {settings.length > 1 && (
                        <select
                            className="rounded-md border px-3 py-2 text-sm"
                            value={setting?.id ?? ''}
                            onChange={(e) => router.get('/admin/siat/contingency', { setting_id: Number(e.target.value) })}
                        >
                            {settings.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
                        </select>
                    )}
                </div>

                {!setting && (
                    <div className="rounded-lg border-2 border-dashed border-gray-200 py-16 text-center text-gray-400">
                        No hay configuración SIAT activa.
                    </div>
                )}

                {setting && (
                    <div className="space-y-6">
                        {motivos.error && (
                            <div className="rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600">
                                No se pudo consultar la paramétrica de eventos ({motivos.error}). Puede escribir el
                                código de motivo a mano.
                            </div>
                        )}

                        {/* Emisión masiva: no es una avería, es volumen. Solo se
                            muestra si hay facturas esperando su lote. */}
                        {masivas_pendientes > 0 && (
                            <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-blue-200 bg-blue-50 p-5">
                                <div className="flex items-start gap-3">
                                    <Layers className="mt-0.5 h-5 w-5 text-blue-600" />
                                    <div>
                                        <p className="font-semibold text-blue-900">
                                            {masivas_pendientes} factura(s) en modalidad masiva esperando su lote
                                        </p>
                                        <p className="text-sm text-blue-700">
                                            Se emitieron con conexión y con tipo de emisión 3, así que no se envían sueltas.
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    className="gap-2"
                                    disabled={busy !== null}
                                    onClick={() => {
                                        setBusy('masivo');
                                        router.post('/admin/siat/contingency/masivo',
                                            { setting_id: setting.id },
                                            { preserveScroll: true, onFinish: () => setBusy(null) });
                                    }}
                                >
                                    <Send className="h-4 w-4" />
                                    {busy === 'masivo' ? 'Enviando…' : 'Enviar lote masivo'}
                                </Button>
                            </div>
                        )}

                        {abierto ? (
                            <div className="rounded-lg border border-red-200 bg-red-50 p-5">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div className="flex items-start gap-3">
                                        <CloudOff className="mt-0.5 h-5 w-5 text-red-600" />
                                        <div>
                                            <p className="font-semibold text-red-900">
                                                Corte en curso desde {fmtDate(abierto.fecha_inicio)}
                                            </p>
                                            <p className="text-sm text-red-700">{abierto.descripcion}</p>
                                            <p className="mt-1 text-xs text-red-600">
                                                Las ventas se están facturando fuera de línea
                                                ({abierto.facturas_pendientes} factura(s) esperando envío).
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        variant="destructive"
                                        className="gap-2"
                                        disabled={busy !== null}
                                        onClick={() => call(`close:${abierto.id}`, `/admin/siat/contingency/${abierto.id}/close`)}
                                    >
                                        <Square className="h-4 w-4" /> Cerrar corte
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <AbrirCorte settingId={setting.id} motivos={motivos} />
                        )}

                        {/* Eventos */}
                        <div className="overflow-hidden rounded-lg border bg-white shadow-sm">
                            <h2 className="border-b px-5 py-3 font-semibold text-gray-700">Cortes registrados</h2>
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-2 text-left">Periodo</th>
                                        <th className="px-4 py-2 text-left">Motivo</th>
                                        <th className="px-4 py-2 text-center">Facturas</th>
                                        <th className="px-4 py-2 text-center">Estado</th>
                                        <th className="px-4 py-2 text-left">Recepción evento</th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {eventos.map((e) => (
                                        <tr key={e.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-2 text-xs text-gray-600">
                                                {fmtDate(e.fecha_inicio)}<br />
                                                <span className="text-gray-400">→ {fmtDate(e.fecha_fin)}</span>
                                            </td>
                                            <td className="px-4 py-2">
                                                <p className="text-xs text-gray-400">Código {e.codigo_motivo_evento}</p>
                                                <p>{e.descripcion}</p>
                                                {e.mensaje_error && (
                                                    <p className="mt-1 text-xs text-red-600">{e.mensaje_error}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-center">
                                                {e.facturas_totales}
                                                {e.facturas_pendientes > 0 && (
                                                    <span className="ml-1 text-xs text-amber-600">
                                                        ({e.facturas_pendientes} sin enviar)
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-center">
                                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${estadoEvento[e.estado] ?? 'bg-gray-100'}`}>
                                                    {e.estado_label}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2 font-mono text-xs text-gray-500">
                                                {e.codigo_recepcion_evento ?? '—'}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {e.estado === 'abierto' && (
                                                    <Button size="sm" variant="outline" disabled={busy !== null}
                                                        onClick={() => call(`close:${e.id}`, `/admin/siat/contingency/${e.id}/close`)}>
                                                        Cerrar
                                                    </Button>
                                                )}
                                                {e.estado !== 'abierto' && e.facturas_pendientes > 0 && (
                                                    <Button size="sm" className="gap-1.5" disabled={busy !== null}
                                                        onClick={() => call(`send:${e.id}`, `/admin/siat/contingency/${e.id}/send`)}>
                                                        <Send className="h-3.5 w-3.5" />
                                                        {busy === `send:${e.id}` ? 'Enviando…' : 'Declarar y enviar'}
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {eventos.length === 0 && (
                                        <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400">
                                            Nunca se ha declarado un corte.
                                        </td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Paquetes */}
                        <div className="overflow-hidden rounded-lg border bg-white shadow-sm">
                            <h2 className="border-b px-5 py-3 font-semibold text-gray-700">Paquetes enviados</h2>
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-2 text-left">Enviado</th>
                                        <th className="px-4 py-2 text-left">Tipo</th>
                                        <th className="px-4 py-2 text-center">Facturas</th>
                                        <th className="px-4 py-2 text-left">Código recepción</th>
                                        <th className="px-4 py-2 text-center">Estado</th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {paquetes.map((p) => (
                                        <tr key={p.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-2 text-xs text-gray-500">{fmtDate(p.enviado_at ?? p.created_at)}</td>
                                            <td className="px-4 py-2 text-xs capitalize">{p.tipo}</td>
                                            <td className="px-4 py-2 text-center">{p.cantidad_facturas}</td>
                                            <td className="px-4 py-2 font-mono text-xs text-gray-500">{p.codigo_recepcion ?? '—'}</td>
                                            <td className="px-4 py-2 text-center">
                                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${estadoPaquete[p.estado] ?? 'bg-gray-100'}`}>
                                                    {p.estado_label}
                                                </span>
                                                {p.mensaje_error && (
                                                    <p className="mt-1 text-xs text-red-600">{p.mensaje_error}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {p.codigo_recepcion && p.estado !== 'validado' && (
                                                    <Button size="sm" variant="outline" className="gap-1.5" disabled={busy !== null}
                                                        onClick={() => call(`val:${p.id}`, `/admin/siat/contingency/packages/${p.id}/validate`)}>
                                                        <PackageCheck className="h-3.5 w-3.5" />
                                                        {busy === `val:${p.id}` ? 'Consultando…' : 'Consultar estado'}
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {paquetes.length === 0 && (
                                        <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400">
                                            Todavía no se ha enviado ningún paquete.
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
