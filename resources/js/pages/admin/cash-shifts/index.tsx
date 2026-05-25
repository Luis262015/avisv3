import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { AlertCircle, Eye, Lock, Plus, X } from 'lucide-react';
import { useState } from 'react';

interface Shift {
    id: number;
    status: 'open' | 'closed';
    opened_at: string;
    closed_at: string | null;
    opening_amount: string;
    closing_amount: string | null;
    difference: string | null;
    notes: string | null;
    // agregados via withSum
    total_sales_amount: string | null;
    total_expenses_amount: string | null;
    total_incomes_amount: string | null;
    withdrawals_total: string | null;
    user: { name: string };
    cash_register: { id: number; name: string; store: { name: string } };
}

interface MyOpenShift {
    id: number;
    register_name: string;
}

interface Props {
    shifts: PaginatedData<Shift>;
    myOpenShift: MyOpenShift | null;
}

export default function CashShiftsIndex({ shifts, myOpenShift }: Props) {
    const fmt = (v: string | number | null) =>
        v != null && v !== '' ? `$${parseFloat(String(v)).toFixed(2)}` : '$0.00';

    const [closingShift, setClosingShift] = useState<Shift | null>(null);
    const { data, setData, patch, processing, errors, reset } = useForm({ closing_amount: '', notes: '' });

    const openShifts = shifts.data.filter((s) => s.status === 'open');

    // Totales del turno seleccionado para el modal
    const allSales       = closingShift ? parseFloat(closingShift.total_sales_amount ?? '0') : 0;
    const allIncomes     = closingShift ? parseFloat(closingShift.total_incomes_amount ?? '0') : 0;
    const allExpenses    = closingShift ? parseFloat(closingShift.total_expenses_amount ?? '0') : 0;
    const withdrawals    = closingShift ? parseFloat(closingShift.withdrawals_total ?? '0') : 0;
    const expectedAmount = closingShift
        ? parseFloat(closingShift.opening_amount) + allSales + allIncomes - allExpenses - withdrawals
        : 0;
    const closingNum  = data.closing_amount !== '' ? parseFloat(data.closing_amount) : null;
    const liveDiff    = closingNum !== null ? closingNum - expectedAmount : null;

    function openCloseModal(shift: Shift) {
        setClosingShift(shift);
        reset();
        setData({ closing_amount: '', notes: shift.notes ?? '' });
    }

    function handleClose(e: React.FormEvent) {
        e.preventDefault();
        if (!closingShift) return;
        patch(`/admin/cash-shifts/${closingShift.id}/close`, {
            onSuccess: () => {
                setClosingShift(null);
                reset();
            },
        });
    }

    return (
        <AppLayout breadcrumbs={[{ title: 'Turnos de Caja', href: '/admin/cash-shifts' }]}>
            <FlashMessage />
            <div className="p-6">
                {/* Header */}
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Turnos de Caja</h1>
                        {openShifts.length > 0 && (
                            <p className="text-sm font-medium text-amber-600">
                                {openShifts.length} turno{openShifts.length > 1 ? 's' : ''} abierto
                                {openShifts.length > 1 ? 's' : ''}
                            </p>
                        )}
                    </div>
                    {myOpenShift ? (
                        /* El usuario ya tiene turno abierto: lo lleva directo a su turno */
                        <Button asChild variant="destructive">
                            <Link href={`/admin/cash-shifts/${myOpenShift.id}`}>
                                <Lock className="mr-2 h-4 w-4" />
                                Mi turno activo — {myOpenShift.register_name}
                            </Link>
                        </Button>
                    ) : (
                        <Button asChild>
                            <Link href="/admin/cash-shifts/create">
                                <Plus className="mr-2 h-4 w-4" /> Iniciar Turno
                            </Link>
                        </Button>
                    )}
                </div>

                {/* Banner: turno propio activo */}
                {myOpenShift && (
                    <div className="mb-4 flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <AlertCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-600" />
                        <div className="flex-1 text-sm text-blue-800">
                            Tienes un turno abierto en <strong>{myOpenShift.register_name}</strong>.
                            No puedes iniciar otro hasta cerrarlo.
                        </div>
                        <Button size="sm" variant="destructive" asChild className="flex-shrink-0">
                            <Link href={`/admin/cash-shifts/${myOpenShift.id}`}>
                                <Lock className="mr-1.5 h-3.5 w-3.5" />
                                Cerrar mi caja
                            </Link>
                        </Button>
                    </div>
                )}

                {/* Alerta de otros turnos abiertos (excluyendo el propio) */}
                {!myOpenShift && openShifts.length > 0 && (
                    <div className="mb-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <AlertCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-amber-600" />
                        <div className="flex-1 text-sm text-amber-800">
                            <strong>
                                {openShifts.length === 1
                                    ? `Turno abierto en "${openShifts[0].cash_register.name}"`
                                    : `${openShifts.length} turnos abiertos`}
                            </strong>
                            <span className="ml-1">
                                — Haz clic en <strong>Cerrar</strong> para realizar el cierre de caja.
                            </span>
                        </div>
                        {openShifts.length === 1 && (
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => openCloseModal(openShifts[0])}
                                className="flex-shrink-0"
                            >
                                <Lock className="mr-1.5 h-3.5 w-3.5" />
                                Cerrar caja
                            </Button>
                        )}
                    </div>
                )}

                {/* Tabla */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Caja / Tienda</th>
                                <th className="px-4 py-3">Usuario</th>
                                <th className="px-4 py-3">Apertura</th>
                                <th className="px-4 py-3">Fondo inicial</th>
                                <th className="px-4 py-3">Diferencia</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {shifts.data.map((s) => (
                                <tr
                                    key={s.id}
                                    className={`hover:bg-gray-50 ${s.status === 'open' ? 'bg-amber-50/50' : ''}`}
                                >
                                    <td className="px-4 py-3">
                                        <p className="font-medium">{s.cash_register.name}</p>
                                        <p className="text-xs text-gray-400">{s.cash_register.store.name}</p>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{s.user.name}</td>
                                    <td className="px-4 py-3 text-gray-500">
                                        {new Date(s.opened_at).toLocaleString('es-MX')}
                                    </td>
                                    <td className="px-4 py-3">{fmt(s.opening_amount)}</td>
                                    <td
                                        className={`px-4 py-3 font-medium ${
                                            s.difference && parseFloat(s.difference) < 0
                                                ? 'text-red-600'
                                                : 'text-green-600'
                                        }`}
                                    >
                                        {fmt(s.difference)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                                s.status === 'open'
                                                    ? 'bg-amber-100 text-amber-700'
                                                    : 'bg-gray-100 text-gray-500'
                                            }`}
                                        >
                                            {s.status === 'open' ? '● Abierto' : 'Cerrado'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1.5">
                                            {s.status === 'open' && (
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => openCloseModal(s)}
                                                >
                                                    <Lock className="mr-1 h-3 w-3" />
                                                    Cerrar
                                                </Button>
                                            )}
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/cash-shifts/${s.id}`}>
                                                    <Eye className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {shifts.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-gray-400">
                                        Sin turnos registrados.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Modal de cierre rápido */}
            {closingShift && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-lg bg-white shadow-xl">
                        {/* Header del modal */}
                        <div className="flex items-start justify-between border-b px-6 py-4">
                            <div>
                                <h2 className="text-lg font-bold">Cierre de Caja</h2>
                                <p className="text-sm text-gray-500">
                                    {closingShift.cash_register.name} —{' '}
                                    {closingShift.cash_register.store.name}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setClosingShift(null)}
                                className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={handleClose} className="space-y-4 p-6">
                            {/* Desglose de efectivo */}
                            <div className="rounded-md border bg-gray-50 p-4 text-sm">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    Desglose de efectivo en caja
                                </p>
                                <div className="space-y-1 text-gray-600">
                                    <div className="flex justify-between">
                                        <span>Fondo inicial</span>
                                        <span>{fmt(closingShift.opening_amount)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>+ Ventas</span>
                                        <span>{fmt(allSales)}</span>
                                    </div>
                                    {allIncomes > 0 && (
                                        <div className="flex justify-between text-green-700">
                                            <span>+ Ingresos</span>
                                            <span>{fmt(allIncomes)}</span>
                                        </div>
                                    )}
                                    {allExpenses > 0 && (
                                        <div className="flex justify-between text-red-700">
                                            <span>- Gastos</span>
                                            <span>-{fmt(allExpenses)}</span>
                                        </div>
                                    )}
                                    {withdrawals > 0 && (
                                        <div className="flex justify-between text-orange-700">
                                            <span>- Retiros</span>
                                            <span>-{fmt(withdrawals)}</span>
                                        </div>
                                    )}
                                </div>
                                <div className="mt-2 flex justify-between border-t pt-2 font-bold text-gray-800">
                                    <span>Total esperado en caja</span>
                                    <span>{fmt(expectedAmount)}</span>
                                </div>
                            </div>

                            {/* Monto contado */}
                            <div>
                                <Label htmlFor="closing_amount" className="mb-1.5 block">
                                    Monto contado en caja ($) *
                                </Label>
                                <Input
                                    id="closing_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    autoFocus
                                    placeholder="0.00"
                                    value={data.closing_amount}
                                    onChange={(e) => setData('closing_amount', e.target.value)}
                                    className="text-lg"
                                />
                                {errors.closing_amount && (
                                    <p className="mt-1 text-xs text-red-500">{errors.closing_amount}</p>
                                )}
                            </div>

                            {/* Diferencia en tiempo real */}
                            {liveDiff !== null && (
                                <div
                                    className={`flex items-center justify-between rounded-md border px-4 py-3 text-sm font-semibold ${
                                        liveDiff < 0
                                            ? 'border-red-200 bg-red-50 text-red-700'
                                            : liveDiff > 0
                                              ? 'border-yellow-200 bg-yellow-50 text-yellow-700'
                                              : 'border-green-200 bg-green-50 text-green-700'
                                    }`}
                                >
                                    <span>Diferencia</span>
                                    <span>
                                        {liveDiff > 0 ? '+' : ''}
                                        {fmt(liveDiff)}
                                    </span>
                                </div>
                            )}

                            {/* Notas */}
                            <div>
                                <Label htmlFor="close_notes" className="mb-1.5 block">
                                    Notas de cierre
                                </Label>
                                <textarea
                                    id="close_notes"
                                    className="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    rows={2}
                                    placeholder="Observaciones opcionales..."
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                />
                            </div>

                            {/* Acciones */}
                            <div className="flex gap-2 pt-1">
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing || !data.closing_amount}
                                    className="flex-1"
                                >
                                    {processing ? 'Cerrando turno...' : 'Confirmar Cierre'}
                                </Button>
                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => setClosingShift(null)}
                                >
                                    Cancelar
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
