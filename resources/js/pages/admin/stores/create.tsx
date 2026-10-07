import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

export default function StoreCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '', address: '', phone: '', email: '', rfc: '', is_active: true,
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Tiendas', href: '/admin/stores' }, { title: 'Nueva', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nueva Tienda</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/stores'); }} className="space-y-4">
                    <div>
                        <Label htmlFor="campo-nombre">Nombre *</Label>
                        <Input id="campo-nombre" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                    </div>
                    <div>
                        <Label htmlFor="campo-direccion">Dirección</Label>
                        <Input id="campo-direccion" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="campo-telefono">Teléfono</Label>
                            <Input id="campo-telefono" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                        </div>
                        <div>
                            <Label htmlFor="campo-email">Email</Label>
                            <Input id="campo-email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        </div>
                    </div>
                    <div>
                        <Label htmlFor="campo-rfc">RFC</Label>
                        <Input id="campo-rfc" value={data.rfc} onChange={(e) => setData('rfc', e.target.value)} />
                    </div>
                    <div className="flex items-center gap-2">
                        <input type="checkbox" id="is_active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="h-4 w-4" />
                        <Label htmlFor="is_active">Activa</Label>
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
