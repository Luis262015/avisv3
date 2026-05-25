import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

interface Register { id: number; name: string; store: { name: string } }

export default function CashShiftCreate({ registers }: { registers: Register[] }) {
    const { data, setData, post, processing, errors } = useForm({ cash_register_id: '', opening_amount: '', notes: '' });
    return (
        <AppLayout breadcrumbs={[{ title: 'Turnos de Caja', href: '/admin/cash-shifts' }, { title: 'Iniciar Turno', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-md p-6">
                <h1 className="mb-2 text-2xl font-bold">Iniciar Turno de Caja</h1>
                <p className="mb-6 text-sm text-gray-500">Selecciona la caja y el fondo inicial de apertura.</p>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/cash-shifts'); }} className="space-y-4">
                    <div>
                        <Label>Caja *</Label>
                        <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.cash_register_id} onChange={(e) => setData('cash_register_id', e.target.value)}>
                            <option value="">— Seleccionar caja disponible —</option>
                            {registers.map((r) => (
                                <option key={r.id} value={r.id}>{r.name} — {r.store.name}</option>
                            ))}
                        </select>
                        {errors.cash_register_id && <p className="mt-1 text-xs text-red-500">{errors.cash_register_id}</p>}
                        {registers.length === 0 && <p className="mt-1 text-xs text-amber-600">No hay cajas disponibles (todas tienen turno abierto).</p>}
                    </div>
                    <div>
                        <Label>Fondo inicial ($) *</Label>
                        <Input type="number" step="0.01" min="0" value={data.opening_amount} onChange={(e) => setData('opening_amount', e.target.value)} />
                        {errors.opening_amount && <p className="mt-1 text-xs text-red-500">{errors.opening_amount}</p>}
                    </div>
                    <div>
                        <Label>Notas</Label>
                        <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>
                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing || registers.length === 0}>Iniciar Turno</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
