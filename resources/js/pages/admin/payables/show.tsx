import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Payment { id: number; amount: string; payment_method: string; date: string; notes: string | null; user: { name: string } }
interface Payable {
    id: number; description: string; amount: string; amount_paid: string; balance: string;
    due_date: string; status: string; notes: string | null;
    supplier: { name: string; contact_name: string | null; phone: string | null } | null;
    purchase: { folio: string } | null;
    user: { name: string };
    payments: Payment[];
}

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700', partial: 'bg-blue-100 text-blue-700',
    paid: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = { pending: 'Pendiente', partial: 'Parcial', paid: 'Pagada', cancelled: 'Cancelada' };
const paymentLabels: Record<string, string> = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia' };

export default function PayableShow({ payable }: { payable: Payable }) {
    const fmt = (v: string) => `$${parseFloat(v).toFixed(2)}`;
    const [showPaymentForm, setShowPaymentForm] = useState(false);
    const canPay = !['paid', 'cancelled'].includes(payable.status);

    const { data, setData, post, processing, errors, reset } = useForm({
        amount: payable.balance,
        payment_method: 'cash',
        date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const handlePayment = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/payables/${payable.id}/payments`, {
            onSuccess: () => { setShowPaymentForm(false); reset(); },
        });
    };

    const handleCancel = () => {
        if (confirm('¿Cancelar esta cuenta por pagar?')) {
            router.patch(`/admin/payables/${payable.id}/cancel`);
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Cuentas por pagar', href: '/admin/payables' }, { title: payable.description, href: '' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{payable.description}</h1>
                        <p className="text-gray-500">{payable.supplier?.name ?? 'Sin proveedor'}{payable.purchase ? ` — Compra ${payable.purchase.folio}` : ''}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className={`rounded-full px-3 py-1 text-sm font-semibold ${statusColors[payable.status]}`}>{statusLabels[payable.status]}</span>
                        {canPay && (
                            <>
                                <Button onClick={() => setShowPaymentForm(!showPaymentForm)}>Registrar Pago</Button>
                                <Button variant="destructive" onClick={handleCancel}>Cancelar</Button>
                            </>
                        )}
                    </div>
                </div>

                <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                    {[
                        { label: 'Total', value: fmt(payable.amount), color: '' },
                        { label: 'Pagado', value: fmt(payable.amount_paid), color: 'text-green-600' },
                        { label: 'Saldo', value: fmt(payable.balance), color: 'text-red-600 font-bold' },
                        { label: 'Vencimiento', value: payable.due_date, color: '' },
                    ].map((item) => (
                        <div key={item.label} className="rounded-lg border bg-white p-4 shadow-sm text-center">
                            <p className="text-xs text-gray-500 uppercase">{item.label}</p>
                            <p className={`mt-1 text-lg font-semibold ${item.color}`}>{item.value}</p>
                        </div>
                    ))}
                </div>

                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    {payable.supplier && (
                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <h2 className="mb-2 font-semibold text-gray-700">Proveedor</h2>
                            <p className="font-medium">{payable.supplier.name}</p>
                            {payable.supplier.contact_name && <p className="text-sm text-gray-500">{payable.supplier.contact_name}</p>}
                            {payable.supplier.phone && <p className="text-sm text-gray-500">{payable.supplier.phone}</p>}
                            <p className="text-sm text-gray-500">Registrado por: {payable.user.name}</p>
                        </div>
                    )}
                    {payable.notes && (
                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <h2 className="mb-2 font-semibold text-gray-700">Notas</h2>
                            <p className="text-sm text-gray-600">{payable.notes}</p>
                        </div>
                    )}
                </div>

                {showPaymentForm && (
                    <div className="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-5 shadow-sm">
                        <h2 className="mb-4 font-semibold text-blue-800">Registrar Pago</h2>
                        <form onSubmit={handlePayment} className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <Label>Monto *</Label>
                                <Input type="number" step="0.01" min="0.01" max={payable.balance} value={data.amount} onChange={(e) => setData('amount', e.target.value)} />
                                {errors.amount && <p className="mt-1 text-xs text-red-500">{errors.amount}</p>}
                            </div>
                            <div>
                                <Label>Método *</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.payment_method} onChange={(e) => setData('payment_method', e.target.value)}>
                                    <option value="cash">Efectivo</option>
                                    <option value="card">Tarjeta</option>
                                    <option value="transfer">Transferencia</option>
                                </select>
                            </div>
                            <div>
                                <Label>Fecha *</Label>
                                <Input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button type="submit" disabled={processing}>Guardar</Button>
                                <Button type="button" variant="outline" onClick={() => setShowPaymentForm(false)}>Cancelar</Button>
                            </div>
                        </form>
                    </div>
                )}

                {payable.payments.length > 0 && (
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-4 py-3 font-semibold text-gray-700">Historial de pagos</div>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-4 py-3">Fecha</th>
                                    <th className="px-4 py-3">Método</th>
                                    <th className="px-4 py-3">Registrado por</th>
                                    <th className="px-4 py-3">Notas</th>
                                    <th className="px-4 py-3 text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {payable.payments.map((p) => (
                                    <tr key={p.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-gray-500">{p.date}</td>
                                        <td className="px-4 py-3">{paymentLabels[p.payment_method]}</td>
                                        <td className="px-4 py-3 text-gray-500">{p.user.name}</td>
                                        <td className="px-4 py-3 text-gray-500">{p.notes ?? '—'}</td>
                                        <td className="px-4 py-3 text-right font-semibold text-green-600">{fmt(p.amount)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
