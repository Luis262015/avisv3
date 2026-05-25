import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

interface Store { id: number; name: string }
interface Setting {
    id?: number;
    store_id: number | string;
    nit: string;
    razon_social: string;
    municipio: string;
    telefono: string;
    direccion: string;
    actividad_economica: string;
    actividad_descripcion: string;
    codigo_sucursal: number | string;
    codigo_punto_venta: number | string;
    nombre_punto_venta: string;
    modalidad: number | string;
    ambiente: string;
    tipo_factura_default: number | string;
    cuis: string;
    token_api: string;
    is_active: boolean;
}

const defaults: Setting = {
    store_id: '', nit: '', razon_social: '', municipio: '', telefono: '', direccion: '',
    actividad_economica: '', actividad_descripcion: '', codigo_sucursal: 0, codigo_punto_venta: 0,
    nombre_punto_venta: 'Principal', modalidad: 2, ambiente: 'simulado', tipo_factura_default: 2,
    cuis: '', token_api: '', is_active: true,
};

export default function SiatSettingForm({ setting, stores }: { setting: Setting | null; stores: Store[] }) {
    const isEdit = !!setting?.id;
    const { data, setData, post, put, processing, errors } = useForm<Setting>(setting ?? defaults);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEdit) put(`/admin/siat/settings/${setting!.id}`);
        else post('/admin/siat/settings');
    };

    const field = (label: string, name: keyof Setting, opts?: { type?: string; hint?: string; required?: boolean }) => (
        <div>
            <Label>{label}{opts?.required !== false && <span className="text-red-500 ml-0.5">*</span>}</Label>
            {opts?.hint && <p className="text-xs text-gray-400 mb-1">{opts.hint}</p>}
            <Input
                type={opts?.type ?? 'text'}
                value={String(data[name] ?? '')}
                onChange={(e) => setData(name, e.target.value as any)}
                className={errors[name] ? 'border-red-500' : ''}
            />
            {errors[name] && <p className="mt-1 text-xs text-red-500">{errors[name]}</p>}
        </div>
    );

    return (
        <AppLayout breadcrumbs={[
            { title: 'SIAT Bolivia', href: '/admin/siat/settings' },
            { title: isEdit ? 'Editar' : 'Nueva Configuración', href: '' },
        ]}>
            <FlashMessage />
            <div className="p-6 max-w-3xl mx-auto">
                <h1 className="text-2xl font-bold mb-6">{isEdit ? 'Editar' : 'Nueva'} Configuración SIAT</h1>

                <form onSubmit={submit} className="space-y-6">
                    {/* Tienda */}
                    <section className="rounded-lg border bg-white p-5 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Tienda</h2>
                        <div>
                            <Label>Tienda <span className="text-red-500">*</span></Label>
                            <select
                                className="w-full rounded-md border px-3 py-2 text-sm"
                                value={String(data.store_id)}
                                onChange={(e) => setData('store_id', e.target.value)}
                            >
                                <option value="">— Seleccionar —</option>
                                {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                            {errors.store_id && <p className="mt-1 text-xs text-red-500">{errors.store_id}</p>}
                        </div>
                    </section>

                    {/* Datos fiscales del emisor */}
                    <section className="rounded-lg border bg-white p-5 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Datos del Emisor (SIN Bolivia)</h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {field('NIT Emisor', 'nit', { hint: '13 dígitos registrados en el SIN', required: true })}
                            {field('Razón Social', 'razon_social', { required: true })}
                            {field('Municipio', 'municipio', { required: true })}
                            {field('Teléfono', 'telefono', { required: false })}
                        </div>
                        <div>{field('Dirección', 'direccion', { required: false })}</div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {field('Código Actividad Económica (CAEB)', 'actividad_economica', { hint: 'Ej: 470000 (Comercio minorista)', required: true })}
                            {field('Descripción Actividad Económica', 'actividad_descripcion', { required: false })}
                        </div>
                    </section>

                    {/* Punto de venta */}
                    <section className="rounded-lg border bg-white p-5 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Punto de Venta</h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            {field('Código Sucursal', 'codigo_sucursal', { type: 'number', hint: '0 = Casa matriz' })}
                            {field('Código Punto de Venta', 'codigo_punto_venta', { type: 'number' })}
                            {field('Nombre Punto de Venta', 'nombre_punto_venta')}
                        </div>
                    </section>

                    {/* Configuración SIN */}
                    <section className="rounded-lg border bg-white p-5 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Configuración SIN</h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <Label>Ambiente <span className="text-red-500">*</span></Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.ambiente} onChange={(e) => setData('ambiente', e.target.value)}>
                                    <option value="simulado">Simulado (sin SIN)</option>
                                    <option value="piloto">Piloto SIN</option>
                                    <option value="produccion">Producción SIN</option>
                                </select>
                            </div>
                            <div>
                                <Label>Modalidad <span className="text-red-500">*</span></Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={String(data.modalidad)} onChange={(e) => setData('modalidad', e.target.value)}>
                                    <option value="1">En línea</option>
                                    <option value="2">Fuera de línea</option>
                                </select>
                            </div>
                            <div>
                                <Label>Tipo Factura por Defecto <span className="text-red-500">*</span></Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={String(data.tipo_factura_default)} onChange={(e) => setData('tipo_factura_default', e.target.value)}>
                                    <option value="1">Con crédito fiscal</option>
                                    <option value="2">Sin crédito fiscal</option>
                                </select>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {field('CUIS (Código Único Inicio Sistema)', 'cuis', { required: false, hint: 'Obtenido del portal SIN' })}
                            {field('Token API SIN', 'token_api', { required: false, hint: 'Para conexión a webservice SIN' })}
                        </div>
                        <div className="flex items-center gap-2">
                            <input type="checkbox" id="is_active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                            <label htmlFor="is_active" className="text-sm font-medium">Configuración activa</label>
                        </div>
                    </section>

                    {data.ambiente !== 'simulado' && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            <strong>Importante:</strong> Para conectar al SIN de Bolivia, el servidor necesita acceso a los
                            webservices SOAP de {data.ambiente === 'piloto' ? 'piloto' : 'producción'}.
                            Asegúrese de tener el CUIS y token configurados.
                        </div>
                    )}

                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>
                            {isEdit ? 'Actualizar' : 'Guardar'} Configuración
                        </Button>
                        <a href="/admin/siat/settings">
                            <Button type="button" variant="outline">Cancelar</Button>
                        </a>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
