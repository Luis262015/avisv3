import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

export default function SupplierCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '', contact_name: '', email: '', phone: '', address: '',
        rfc: '', tax_id: '', payment_terms: '', lead_time_days: '',
        website: '', bank_account: '', is_active: true, notes: '',
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Proveedores', href: '/admin/suppliers' }, { title: 'Nuevo', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-2xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nuevo Proveedor</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/suppliers'); }} className="space-y-5">

                    <div className="rounded-lg border bg-card p-4 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Datos generales</h2>
                        <div>
                            <Label htmlFor="campo-empresa">Empresa *</Label>
                            <Input id="campo-empresa" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="campo-nombre-de-contacto">Nombre de contacto</Label>
                                <Input id="campo-nombre-de-contacto" value={data.contact_name} onChange={(e) => setData('contact_name', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="campo-telefono">Teléfono</Label>
                                <Input id="campo-telefono" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="campo-email">Email</Label>
                                <Input id="campo-email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="campo-sitio-web">Sitio web</Label>
                                <Input id="campo-sitio-web" type="url" placeholder="https://" value={data.website} onChange={(e) => setData('website', e.target.value)} />
                                {errors.website && <p className="mt-1 text-xs text-red-500">{errors.website}</p>}
                            </div>
                        </div>
                        <div>
                            <Label htmlFor="campo-direccion">Dirección</Label>
                            <Input id="campo-direccion" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                        </div>
                    </div>

                    <div className="rounded-lg border bg-card p-4 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Fiscal y bancario</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="campo-rfc-nit">RFC / NIT</Label>
                                <Input id="campo-rfc-nit" value={data.rfc} onChange={(e) => setData('rfc', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="campo-tax-id">Tax ID</Label>
                                <Input id="campo-tax-id" value={data.tax_id} onChange={(e) => setData('tax_id', e.target.value)} />
                            </div>
                        </div>
                        <div>
                            <Label htmlFor="campo-cuenta-bancaria">Cuenta bancaria</Label>
                            <Input id="campo-cuenta-bancaria" value={data.bank_account} onChange={(e) => setData('bank_account', e.target.value)} />
                        </div>
                    </div>

                    <div className="rounded-lg border bg-card p-4 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Condiciones comerciales</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="campo-plazo-de-pago">Plazo de pago</Label>
                                <Input id="campo-plazo-de-pago" placeholder="Ej: 30 días, contado" value={data.payment_terms} onChange={(e) => setData('payment_terms', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="campo-tiempo-de-entrega-dias">Tiempo de entrega (días)</Label>
                                <Input id="campo-tiempo-de-entrega-dias" type="number" min="0" max="365" value={data.lead_time_days} onChange={(e) => setData('lead_time_days', e.target.value)} />
                                {errors.lead_time_days && <p className="mt-1 text-xs text-red-500">{errors.lead_time_days}</p>}
                            </div>
                        </div>
                        <div>
                            <Label htmlFor="campo-notas">Notas</Label>
                            <textarea id="campo-notas" className="w-full rounded-md border px-3 py-2 text-sm" rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </div>
                        <div className="flex items-center gap-2">
                            <input type="checkbox" id="active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="h-4 w-4" />
                            <Label htmlFor="active">Activo</Label>
                        </div>
                    </div>

                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>Guardar</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
