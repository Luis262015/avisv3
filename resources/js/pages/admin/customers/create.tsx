import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

export default function CustomerCreate() {
    const { data, setData, post, processing, errors } = useForm<any>({
        name: '', document_type: 'none', document_number: '', phone: '',
        email: '', address: '', notes: '', is_active: true,
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Clientes', href: '/admin/customers' }, { title: 'Nuevo', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-2xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nuevo Cliente</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/customers'); }} className="space-y-5">
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                        <div>
                            <Label>Nombre / Razón social *</Label>
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <Label>Tipo de documento</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.document_type} onChange={(e) => setData('document_type', e.target.value)}>
                                    <option value="none">Sin documento</option>
                                    <option value="ci">CI</option>
                                    <option value="nit">NIT</option>
                                </select>
                            </div>
                            <div className="col-span-2">
                                <Label>Número de documento</Label>
                                <Input value={data.document_number} onChange={(e) => setData('document_number', e.target.value)} disabled={data.document_type === 'none'} />
                                {errors.document_number && <p className="mt-1 text-xs text-red-500">{errors.document_number}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Teléfono</Label>
                                <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            </div>
                            <div>
                                <Label>Email</Label>
                                <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email}</p>}
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
