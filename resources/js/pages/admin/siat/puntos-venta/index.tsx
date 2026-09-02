import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { router, useForm } from '@inertiajs/react';
import { Ban, CheckCircle2, KeyRound, Plus, RefreshCw } from 'lucide-react';
import { useState } from 'react';

interface PuntoVenta {
    id: number;
    codigo: number;
    codigo_sucursal: number;
    nombre: string;
    descripcion: string | null;
    tipo: number | null;
    tiene_cuis: boolean;
    cuis_fecha_vigencia: string | null;
    es_principal: boolean;
    estado: string;
}

interface Setting {
    id: number;
    store_id: number;
    codigo_sucursal: number;
    codigo_punto_venta: number;
    ambiente: string;
}

export default function PuntosVentaIndex({
    setting,
    settings,
    puntos,
    tipos,
}: {
    setting: Setting | null;
    settings: { id: number; store_id: number; store: string | null }[];
    puntos: PuntoVenta[];
    tipos: Record<string, string>;
}) {
    const [abriendo, setAbriendo] = useState(false);

    const form = useForm({ nombre: '', descripcion: '', tipo: Object.keys(tipos)[0] ?? '2' });

    const accion = (ruta: string, confirmacion?: string) => {
        if (confirmacion && !window.confirm(confirmacion)) return;
        router.post(ruta, {}, { preserveScroll: true });
    };

    const registrar = () => {
        if (!setting) return;
        form.post(`/admin/siat/settings/${setting.id}/puntos-venta`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setAbriendo(false);
            },
        });
    };

    if (!setting) {
        return (
            <AppLayout breadcrumbs={[{ title: 'SIAT Bolivia', href: '' }, { title: 'Puntos de venta', href: '' }]}>
                <div className="p-6">
                    <p className="text-gray-500">No hay ninguna configuración SIAT activa.</p>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={[{ title: 'SIAT Bolivia', href: '' }, { title: 'Puntos de venta', href: '' }]}>
            <FlashMessage />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Puntos de venta</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Sucursal {setting.codigo_sucursal} · ambiente {setting.ambiente}. Cada punto lleva su propio
                            CUIS y su propia serie de facturas.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {settings.length > 1 && (
                            <select
                                className="rounded-md border border-gray-300 px-3 text-sm"
                                value={setting.id}
                                onChange={(e) => router.get('/admin/siat/puntos-venta', { setting: e.target.value })}
                            >
                                {settings.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.store ?? `Tienda ${s.store_id}`}
                                    </option>
                                ))}
                            </select>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => accion(`/admin/siat/settings/${setting.id}/puntos-venta/sync`)}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Sincronizar con el SIN
                        </Button>
                        <Button onClick={() => setAbriendo((v) => !v)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Registrar punto de venta
                        </Button>
                    </div>
                </div>

                {abriendo && (
                    <div className="space-y-3 rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-sm text-gray-600">
                            El número lo asigna el SIN: no se puede elegir. El registro solo se deshace dando de baja
                            el punto.
                        </p>
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label className="text-xs uppercase text-gray-400">Nombre</label>
                                <Input
                                    value={form.data.nombre}
                                    onChange={(e) => form.setData('nombre', e.target.value)}
                                    placeholder="Caja 2"
                                />
                                {form.errors.nombre && <p className="text-xs text-red-600">{form.errors.nombre}</p>}
                            </div>
                            <div>
                                <label className="text-xs uppercase text-gray-400">Descripción</label>
                                <Input
                                    value={form.data.descripcion}
                                    onChange={(e) => form.setData('descripcion', e.target.value)}
                                    placeholder="Segundo punto de venta"
                                />
                                {form.errors.descripcion && (
                                    <p className="text-xs text-red-600">{form.errors.descripcion}</p>
                                )}
                            </div>
                            <div>
                                <label className="text-xs uppercase text-gray-400">Tipo</label>
                                <select
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                    value={form.data.tipo}
                                    onChange={(e) => form.setData('tipo', e.target.value)}
                                >
                                    {Object.entries(tipos).map(([codigo, nombre]) => (
                                        <option key={codigo} value={codigo}>
                                            {codigo} · {nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Button disabled={form.processing} onClick={registrar}>
                                Registrar ante el SIN
                            </Button>
                            <Button variant="outline" onClick={() => setAbriendo(false)}>
                                Cancelar
                            </Button>
                        </div>
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Código</th>
                                <th className="px-4 py-3">Nombre</th>
                                <th className="px-4 py-3">CUIS</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {puntos.map((punto) => {
                                const emitiendo = punto.codigo === setting.codigo_punto_venta;

                                return (
                                    <tr key={punto.id} className={emitiendo ? 'bg-green-50' : ''}>
                                        <td className="px-4 py-3 font-semibold">
                                            {punto.codigo}
                                            {punto.es_principal && (
                                                <span className="ml-2 text-xs font-normal text-gray-400">
                                                    casa matriz
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {punto.nombre}
                                            {punto.descripcion && (
                                                <div className="text-xs text-gray-400">{punto.descripcion}</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {punto.tiene_cuis ? (
                                                <span className="text-green-700">
                                                    Sí
                                                    {punto.cuis_fecha_vigencia && (
                                                        <span className="ml-1 text-xs text-gray-400">
                                                            hasta{' '}
                                                            {new Date(punto.cuis_fecha_vigencia).toLocaleDateString(
                                                                'es-BO',
                                                            )}
                                                        </span>
                                                    )}
                                                </span>
                                            ) : (
                                                <span className="text-amber-700">Falta</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {emitiendo ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">
                                                    <CheckCircle2 className="h-3 w-3" />
                                                    Emitiendo
                                                </span>
                                            ) : (
                                                <span
                                                    className={`rounded-full px-2 py-1 text-xs font-semibold ${
                                                        punto.estado === 'activo'
                                                            ? 'bg-gray-100 text-gray-600'
                                                            : 'bg-red-100 text-red-700'
                                                    }`}
                                                >
                                                    {punto.estado === 'activo' ? 'Activo' : 'Cerrado'}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                {!punto.tiene_cuis && punto.estado === 'activo' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            accion(
                                                                `/admin/siat/settings/${setting.id}/puntos-venta/${punto.id}/cuis`,
                                                            )
                                                        }
                                                    >
                                                        <KeyRound className="mr-1 h-4 w-4" />
                                                        Solicitar CUIS
                                                    </Button>
                                                )}
                                                {!emitiendo && punto.tiene_cuis && punto.estado === 'activo' && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            accion(
                                                                `/admin/siat/settings/${setting.id}/puntos-venta/${punto.id}/activate`,
                                                            )
                                                        }
                                                    >
                                                        Emitir por este
                                                    </Button>
                                                )}
                                                {!emitiendo && !punto.es_principal && punto.estado === 'activo' && (
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            accion(
                                                                `/admin/siat/settings/${setting.id}/puntos-venta/${punto.id}/close`,
                                                                '¿Dar de baja este punto de venta ante el SIN? No tiene vuelta atrás.',
                                                            )
                                                        }
                                                    >
                                                        <Ban className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                            {puntos.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-10 text-center text-gray-500">
                                        No hay puntos de venta registrados.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
