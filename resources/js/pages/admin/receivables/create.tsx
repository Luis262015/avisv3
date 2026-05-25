import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

export default function ReceivableCreate() {
    const { data, setData, post, processing, errors } = useForm({
        customer_name: '',
        customer_phone: '',
        customer_email: '',
        description: '',
        amount: '',
        due_date: '',
        notes: '',
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Cuentas por cobrar', href: '/admin/receivables' }, { title: 'Nueva', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-2xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nueva Cuenta por Cobrar</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/receivables'); }} className="space-y-5">
                    <div className="rounded-lg border bg-white p-5 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Datos del cliente</h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <Label>Nombre *</Label>
                                <Input value={data.customer_name} onChange={(e) => setData('customer_name', e.target.value)} placeholder="Nombre completo" />
                                {errors.customer_name && <p className="mt-1 text-xs text-red-500">{errors.customer_name}</p>}
                            </div>
                            <div>
                                <Label>Teléfono</Label>
                                <Input value={data.customer_phone} onChange={(e) => setData('customer_phone', e.target.value)} placeholder="Opcional" />
                            </div>
                            <div>
                                <Label>Correo</Label>
                                <Input type="email" value={data.customer_email} onChange={(e) => setData('customer_email', e.target.value)} placeholder="Opcional" />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-lg border bg-white p-5 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Deuda</h2>
                        <div>
                            <Label>Descripción *</Label>
                            <Input value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="Ej. Venta a crédito productos varios" />
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
