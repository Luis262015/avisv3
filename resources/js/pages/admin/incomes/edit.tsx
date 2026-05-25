import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

interface CashShift { id: number; cash_register: { name: string } }
interface Income {
    id: number; cash_shift_id: number | null; category: string; description: string;
    amount: string; payment_method: string; reference: string | null; date: string; notes: string | null;
}

const INCOME_CATEGORIES = [
    'Inversión', 'Préstamo', 'Devolución', 'Donación', 'Venta de activo', 'Otro',
];

export default function IncomeEdit({ income, openShifts }: { income: Income; openShifts: CashShift[] }) {
    const { data, setData, patch, processing, errors } = useForm({
        cash_shift_id: income.cash_shift_id?.toString() ?? '',
        category: income.category,
        description: income.description,
        amount: income.amount,
        payment_method: income.payment_method,
        reference: income.reference ?? '',
        date: income.date.substring(0, 10),
        notes: income.notes ?? '',
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Ingresos', href: '/admin/incomes' }, { title: 'Editar', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-2xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Editar Ingreso</h1>
                <form onSubmit={(e) => { e.preventDefault(); patch(`/admin/incomes/${income.id}`); }} className="space-y-5">
                    <div className="rounded-lg border bg-white p-5 shadow-sm space-y-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label>Categoría *</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.category} onChange={(e) => setData('category', e.target.value)}>
                                    <option value="">— Seleccionar —</option>
                                    {INCOME_CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
                                </select>
                                {errors.category && <p className="mt-1 text-xs text-red-500">{errors.category}</p>}
                            </div>
                            <div>
                                <Label>Fecha *</Label>
                                <Input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                                {errors.date && <p className="mt-1 text-xs text-red-500">{errors.date}</p>}
                            </div>
                        </div>

                        <div>
                            <Label>Descripción *</Label>
                            <Input value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            {errors.description && <p className="mt-1 text-xs text-red-500">{errors.description}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <Label>Monto *</Label>
                                <Input type="number" step="0.01" min="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} />
                                {errors.amount && <p className="mt-1 text-xs text-red-500">{errors.amount}</p>}
                            </div>
                            <div>
                                <Label>Método de pago *</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.payment_method} onChange={(e) => setData('payment_method', e.target.value)}>
                                    <option value="cash">Efectivo</option>
                                    <option value="card">Tarjeta</option>
                                    <option value="transfer">Transferencia</option>
                                </select>
                            </div>
                            <div>
                                <Label>No. de referencia</Label>
                                <Input value={data.reference} onChange={(e) => setData('reference', e.target.value)} placeholder="Opcional" />
                            </div>
                        </div>

                        {openShifts.length > 0 && (
                            <div>
                                <Label>Turno de caja (opcional)</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.cash_shift_id} onChange={(e) => setData('cash_shift_id', e.target.value)}>
                                    <option value="">— Sin turno —</option>
                                    {openShifts.map((s) => <option key={s.id} value={s.id}>{s.cash_register.name}</option>)}
                                </select>
                            </div>
                        )}

                        <div>
                            <Label>Notas</Label>
                            <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>Guardar Cambios</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
