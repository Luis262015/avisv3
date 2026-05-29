import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

interface Supplier {
    id: number; name: string; contact_name: string | null; email: string | null;
    phone: string | null; address: string | null; rfc: string | null; tax_id: string | null;
    payment_terms: string | null; lead_time_days: number | null; website: string | null;
    bank_account: string | null; is_active: boolean; notes: string | null;
}

export default function SupplierEdit({ supplier }: { supplier: Supplier }) {
    const { data, setData, put, processing, errors } = useForm({
        name: supplier.name,
        contact_name: supplier.contact_name ?? '',
        email: supplier.email ?? '',
        phone: supplier.phone ?? '',
        address: supplier.address ?? '',
        rfc: supplier.rfc ?? '',
        tax_id: supplier.tax_id ?? '',
        payment_terms: supplier.payment_terms ?? '',
        lead_time_days: supplier.lead_time_days?.toString() ?? '',
        website: supplier.website ?? '',
        bank_account: supplier.bank_account ?? '',
        is_active: supplier.is_active,
        notes: supplier.notes ?? '',
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Proveedores', href: '/admin/suppliers' }, { title: supplier.name, href: `/admin/suppliers/${supplier.id}` }, { title: 'Editar', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-2xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Editar Proveedor</h1>
                <form onSubmit={(e) => { e.preventDefault(); put(`/admin/suppliers/${supplier.id}`); }} className="space-y-5">

                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Datos generales</h2>
                        <div>
                            <Label>Empresa *</Label>
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div><Label>Contacto</Label><Input value={data.contact_name} onChange={(e) => setData('contact_name', e.target.value)} /></div>
                            <div><Label>Teléfono</Label><Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} /></div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div><Label>Email</Label><Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} /></div>
                            <div>
                                <Label>Sitio web</Label>
                                <Input type="url" placeholder="https://" value={data.website} onChange={(e) => setData('website', e.target.value)} />
                                {errors.website && <p className="mt-1 text-xs text-red-500">{errors.website}</p>}
                            </div>
                        </div>
                        <div><Label>Dirección</Label><Input value={data.address} onChange={(e) => setData('address', e.target.value)} /></div>
                    </div>

                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Fiscal y bancario</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <div><Label>RFC / NIT</Label><Input value={data.rfc} onChange={(e) => setData('rfc', e.target.value)} /></div>
                            <div><Label>Tax ID</Label><Input value={data.tax_id} onChange={(e) => setData('tax_id', e.target.value)} /></div>
                        </div>
                        <div><Label>Cuenta bancaria</Label><Input value={data.bank_account} onChange={(e) => setData('bank_account', e.target.value)} /></div>
                    </div>

                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Condiciones comerciales</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Plazo de pago</Label>
                                <Input placeholder="Ej: 30 días, contado" value={data.payment_terms} onChange={(e) => setData('payment_terms', e.target.value)} />
                            </div>
                            <div>
                                <Label>Tiempo de entrega (días)</Label>
                                <Input type="number" min="0" max="365" value={data.lead_time_days} onChange={(e) => setData('lead_time_days', e.target.value)} />
                                {errors.lead_time_days && <p className="mt-1 text-xs text-red-500">{errors.lead_time_days}</p>}
                            </div>
                        </div>
                        <div>
                            <Label>Notas</Label>
                            <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </div>
                        <div className="flex items-center gap-2">
                            <input type="checkbox" id="active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="h-4 w-4" />
                            <Label htmlFor="active">Activo</Label>
                        </div>
                    </div>

                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>Actualizar</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
