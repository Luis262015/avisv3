import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

interface Store { id: number; name: string; address: string; phone: string; email: string; rfc: string; is_active: boolean }

export default function StoreEdit({ store }: { store: Store }) {
    const { data, setData, put, processing, errors } = useForm({
        name: store.name, address: store.address ?? '', phone: store.phone ?? '',
        email: store.email ?? '', rfc: store.rfc ?? '', is_active: store.is_active,
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Tiendas', href: '/admin/stores' }, { title: 'Editar', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Editar Tienda</h1>
                <form onSubmit={(e) => { e.preventDefault(); put(`/admin/stores/${store.id}`); }} className="space-y-4">
                    <div>
                        <Label>Nombre *</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                    </div>
                    <div>
                        <Label>Dirección</Label>
                        <Input value={data.address} onChange={(e) => setData('address', e.target.value)} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Teléfono</Label>
                            <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                        </div>
                        <div>
                            <Label>Email</Label>
                            <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        </div>
                    </div>
                    <div>
                        <Label>RFC</Label>
                        <Input value={data.rfc} onChange={(e) => setData('rfc', e.target.value)} />
                    </div>
                    <div className="flex items-center gap-2">
                        <input type="checkbox" id="is_active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="h-4 w-4" />
                        <Label htmlFor="is_active">Activa</Label>
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
