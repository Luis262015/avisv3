import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';
import { CheckCircle, Edit, KeyRound, ListChecks, Plug, Plus, RefreshCw, Trash2, XCircle } from 'lucide-react';
import { useState } from 'react';

interface Setting {
    id: number;
    nit: string;
    razon_social: string;
    municipio: string;
    ambiente: string;
    ambiente_label: string;
    modalidad_label: string;
    actividad_economica: string;
    codigo_punto_venta: number;
    is_active: boolean;
    has_token_api: boolean;
    has_cuis: boolean;
    store: { id: number; name: string };
}

interface Store { id: number; name: string }

export default function SiatSettingsIndex({ settings, stores }: { settings: Setting[]; stores: Store[] }) {
    // Las operaciones contra el SIN tardan (SOAP + WSDL); sin este estado no hay
    // forma de saber si el clic hizo algo.
    const [busy, setBusy] = useState<string | null>(null);

    const call = (id: number, accion: string) => {
        const clave = `${id}:${accion}`;
        setBusy(clave);
        router.post(`/admin/siat/settings/${id}/${accion}`, {}, {
            preserveScroll: true,
            onFinish: () => setBusy((b) => (b === clave ? null : b)),
        });
    };

    const esperando = (id: number, accion: string) => busy === `${id}:${accion}`;

    const destroy = (id: number) => {
        if (confirm('¿Eliminar esta configuración SIAT?')) {
            router.delete(`/admin/siat/settings/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'SIAT Bolivia', href: '' }, { title: 'Configuración', href: '' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Configuración SIAT Bolivia v2</h1>
                        <p className="text-sm text-gray-500">Facturación electrónica SIN Bolivia</p>
                    </div>
                    <Link href="/admin/siat/settings/create">
                        <Button className="gap-2">
                            <Plus className="h-4 w-4" /> Nueva Configuración
                        </Button>
                    </Link>
                </div>

                {settings.length === 0 ? (
                    <div className="rounded-lg border-2 border-dashed border-gray-200 py-16 text-center">
                        <p className="text-gray-400">No hay configuraciones SIAT.</p>
                        <Link href="/admin/siat/settings/create" className="mt-2 inline-block text-sm text-blue-600 hover:underline">
                            Crear configuración
                        </Link>
                    </div>
                ) : (
                    <div className="grid gap-4">
                        {settings.map((s) => (
                            <div key={s.id} className="rounded-lg border bg-white p-5 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div className="flex items-start gap-3">
                                        <div className={`mt-0.5 rounded-full p-1 ${s.is_active ? 'bg-green-100' : 'bg-gray-100'}`}>
                                            {s.is_active
                                                ? <CheckCircle className="h-5 w-5 text-green-600" />
                                                : <XCircle className="h-5 w-5 text-gray-400" />}
                                        </div>
                                        <div>
                                            <p className="font-semibold text-gray-800">{s.razon_social}</p>
                                            <p className="text-sm text-gray-500">Tienda: {s.store.name}</p>
                                            <p className="mt-1 text-sm text-gray-600">
                                                <span className="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">NIT: {s.nit}</span>
                                                {' '}<span className="ml-2 text-xs text-gray-400">{s.municipio}</span>
                                            </p>
                                            {/* El token y el CUIS nunca viajan al navegador; solo si están. */}
                                            <div className="mt-2 flex flex-wrap gap-1.5 text-xs">
                                                <span className={`rounded-full px-2 py-0.5 ${s.has_token_api ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'}`}>
                                                    {s.has_token_api ? 'Token cargado' : 'Sin Token Delegado'}
                                                </span>
                                                <span className={`rounded-full px-2 py-0.5 ${s.has_cuis ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'}`}>
                                                    {s.has_cuis ? 'CUIS vigente' : 'Sin CUIS'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-right text-xs text-gray-500 space-y-1">
                                        <p>Ambiente: <span className={`font-medium ${s.ambiente === 'produccion' ? 'text-green-600' : s.ambiente === 'simulado' ? 'text-purple-600' : 'text-amber-600'}`}>{s.ambiente_label}</span></p>
                                        <p>Modalidad: {s.modalidad_label}</p>
                                        <p>Actividad: {s.actividad_economica} — POS: {s.codigo_punto_venta}</p>
                                    </div>
                                </div>

                                <div className="mt-4 flex flex-wrap gap-2 border-t pt-3">
                                    {/* El modo simulado no habla con el SIN: el controlador rechaza
                                        estas dos operaciones, así que ni se ofrecen. */}
                                    {s.ambiente !== 'simulado' && (
                                        <>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="gap-1.5"
                                                disabled={esperando(s.id, 'test-connection')}
                                                onClick={() => call(s.id, 'test-connection')}
                                            >
                                                <Plug className="h-3.5 w-3.5" />
                                                {esperando(s.id, 'test-connection') ? 'Probando…' : 'Probar conexión'}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="gap-1.5"
                                                disabled={esperando(s.id, 'request-cuis')}
                                                onClick={() => call(s.id, 'request-cuis')}
                                            >
                                                <KeyRound className="h-3.5 w-3.5" />
                                                {esperando(s.id, 'request-cuis') ? 'Solicitando…' : s.has_cuis ? 'Renovar CUIS' : 'Solicitar CUIS'}
                                            </Button>
                                        </>
                                    )}
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="gap-1.5"
                                        disabled={esperando(s.id, 'generate-cufd')}
                                        onClick={() => call(s.id, 'generate-cufd')}
                                    >
                                        <RefreshCw className="h-3.5 w-3.5" />
                                        {esperando(s.id, 'generate-cufd') ? 'Generando…' : 'Generar CUFD'}
                                    </Button>
                                    <Link href={`/admin/siat/homologation?setting_id=${s.id}`}>
                                        <Button size="sm" variant="ghost" className="gap-1.5 text-gray-600">
                                            <ListChecks className="h-3.5 w-3.5" /> Homologar productos
                                        </Button>
                                    </Link>
                                    <Link href={`/admin/siat/settings/${s.id}/cufd-history`}>
                                        <Button size="sm" variant="ghost" className="gap-1.5 text-gray-600">
                                            Historial CUFD
                                        </Button>
                                    </Link>
                                    <Link href={`/admin/siat/settings/${s.id}/edit`}>
                                        <Button size="sm" variant="ghost" className="gap-1.5">
                                            <Edit className="h-3.5 w-3.5" /> Editar
                                        </Button>
                                    </Link>
                                    <Button size="sm" variant="ghost" className="gap-1.5 text-red-500 hover:text-red-700" onClick={() => destroy(s.id)}>
                                        <Trash2 className="h-3.5 w-3.5" /> Eliminar
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
