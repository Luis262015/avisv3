import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

interface Supplier { id: number; name: string }

export default function PayableCreate({ suppliers }: { suppliers: Supplier[] }) {
    const { data, setData, post, processing, errors } = useForm({
        supplier_id: '',
        description: '',
        amount: '',
        due_date: '',
        notes: '',
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Cuentas por pagar', href: '/admin/payables' }, { title: 'Nueva', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-2xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nueva Cuenta por Pagar</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/payables'); }} className="space-y-5">
                    <div className="rounded-lg border bg-white p-5 shadow-sm space-y-4">
                        <div>
                            <Label>Proveedor</Label>
                            <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)}>
                                <option value="">— Sin proveedor —</option>
                                {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>

                        <div>
                            <Label>Descripción *</Label>
                            <Input value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="Ej. Factura #1234 productos varios" />
                            {errors.description && <p className="mt-1 text-xs text-red-500">{errors.description}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label>Monto total *</Label>
                                <Input type="number" step="0.01" min="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} placeholder="0.00" />
                                {errors.amount && <p className="mt-1 text-xs text-red-500">{errors.amount}</p>}
                            </div>
                            <div>
                                <Label>Fecha de vencimiento *</Label>
                                <Input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} />
                                {errors.due_date && <p className="mt-1 text-xs text-red-500">{errors.due_date}</p>}
                            </div>
                        </div>

                        <div>
                            <Label>Notas</Label>
                            <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>Registrar Cuenta</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
