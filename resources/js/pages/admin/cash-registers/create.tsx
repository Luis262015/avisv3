import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

interface Store { id: number; name: string }

export default function CashRegisterCreate({ stores }: { stores: Store[] }) {
    const { data, setData, post, processing, errors } = useForm({ store_id: '', name: '', is_active: true });
    return (
        <AppLayout breadcrumbs={[{ title: 'Cajas', href: '/admin/cash-registers' }, { title: 'Nueva', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-md p-6">
                <h1 className="mb-6 text-2xl font-bold">Nueva Caja</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/cash-registers'); }} className="space-y-4">
                    <div>
                        <Label>Tienda *</Label>
                        <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.store_id} onChange={(e) => setData('store_id', e.target.value)}>
                            <option value="">— Seleccionar —</option>
                            {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                        {errors.store_id && <p className="mt-1 text-xs text-red-500">{errors.store_id}</p>}
                    </div>
                    <div>
                        <Label>Nombre de la caja *</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Ej: Caja 1, Caja Principal" />
                        {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                    </div>
                    <div className="flex items-center gap-2">
                        <input type="checkbox" id="active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="h-4 w-4" />
                        <Label htmlFor="active">Activa</Label>
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
