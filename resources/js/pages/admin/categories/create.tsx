import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

interface Parent { id: number; name: string }

export default function CategoryCreate({ parents }: { parents: Parent[] }) {
    const { data, setData, post, processing, errors } = useForm({ name: '', description: '', parent_id: '', is_active: true });
    return (
        <AppLayout breadcrumbs={[{ title: 'Categorías', href: '/admin/categories' }, { title: 'Nueva', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-lg p-6">
                <h1 className="mb-6 text-2xl font-bold">Nueva Categoría</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/categories'); }} className="space-y-4">
                    <div>
                        <Label>Nombre *</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                    </div>
                    <div>
                        <Label>Descripción</Label>
                        <Input value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </div>
                    <div>
                        <Label>Categoría padre</Label>
                        <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.parent_id} onChange={(e) => setData('parent_id', e.target.value)}>
                            <option value="">— Ninguna —</option>
                            {parents.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </select>
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
