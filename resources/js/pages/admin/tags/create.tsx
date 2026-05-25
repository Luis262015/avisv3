import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

export default function TagCreate() {
    const { data, setData, post, processing, errors } = useForm({ name: '' });
    return (
        <AppLayout breadcrumbs={[{ title: 'Etiquetas', href: '/admin/tags' }, { title: 'Nueva', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-md p-6">
                <h1 className="mb-6 text-2xl font-bold">Nueva Etiqueta</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/tags'); }} className="space-y-4">
                    <div>
                        <Label>Nombre *</Label>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
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
