import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

export default function SupplierCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '', contact_name: '', email: '', phone: '', address: '', rfc: '', is_active: true, notes: '',
    });
    return (
        <AppLayout breadcrumbs={[{ title: 'Proveedores', href: '/admin/suppliers' }, { title: 'Nuevo', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nuevo Proveedor</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/suppliers'); }} className="space-y-4">
                    <div>
                        <Label>Empresa *</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Nombre de contacto</Label>
                            <Input value={data.contact_name} onChange={(e) => setData('contact_name', e.target.value)} />
                        </div>
                        <div>
                            <Label>Teléfono</Label>
                            <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Email</Label>
                            <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        </div>
                        <div>
                            <Label>RFC</Label>
                            <Input value={data.rfc} onChange={(e) => setData('rfc', e.target.value)} />
                        </div>
                    </div>
                    <div>
                        <Label>Dirección</Label>
                        <Input value={data.address} onChange={(e) => setData('address', e.target.value)} />
                    </div>
                    <div>
                        <Label>Notas</Label>
                        <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>
                    <div className="flex items-center gap-2">
                        <input type="checkbox" id="active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="h-4 w-4" />
                        <Label htmlFor="active">Activo</Label>
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
