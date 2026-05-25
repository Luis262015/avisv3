import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

interface Register { id: number; name: string; store_id: number; is_active: boolean }
interface Store { id: number; name: string }

export default function CashRegisterEdit({ register, stores }: { register: Register; stores: Store[] }) {
    const { data, setData, put, processing, errors } = useForm({ store_id: register.store_id.toString(), name: register.name, is_active: register.is_active });
    return (
        <AppLayout breadcrumbs={[{ title: 'Cajas', href: '/admin/cash-registers' }, { title: 'Editar', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-md p-6">
                <h1 className="mb-2 text-2xl font-bold">Editar Caja</h1>
                <p className="mb-6 font-mono text-sm text-gray-400">ID: #{register.id}</p>
                <form onSubmit={(e) => { e.preventDefault(); put(`/admin/cash-registers/${register.id}`); }} className="space-y-4">
                    <div>
                        <Label>Tienda *</Label>
                        <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.store_id} onChange={(e) => setData('store_id', e.target.value)}>
                            {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <Label>Nombre *</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                    </div>
                    <div className="flex items-center gap-2">
                        <input type="checkbox" id="active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="h-4 w-4" />
                        <Label htmlFor="active">Activa</Label>
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
