import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Link, useForm } from '@inertiajs/react';
import { Lock, ShoppingCart, X } from 'lucide-react';
import { useState } from 'react';

interface Sale {
    id: number;
    folio: string;
    total: string;
    status: string;
    payment_method: string;
    created_at: string;
}

interface Expense {
    id: number;
    category: string;
    description: string;
    amount: string;
    payment_method: string;
    date: string;
}

interface Income {
    id: number;
    category: string;
    description: string;
    amount: string;
    payment_method: string;
    date: string;
}

interface Withdrawal {
    id: number;
    amount: string;
    reason: string;
    authorized_by: string | null;
    date: string;
}

interface Shift {
    id: number;
    status: 'open' | 'closed';
    opened_at: string;
    closed_at: string | null;
    opening_amount: string;
    closing_amount: string | null;
    expected_amount: string | null;
    difference: string | null;
    notes: string | null;
    user: { name: string };
    cash_register: { id: number; name: string; store: { name: string } };
    sales: Sale[];
    expenses: Expense[];
    incomes: Income[];
    withdrawals: Withdrawal[];
}

interface Props {
    shift: Shift;
    totalSales: number;
    salesCount: number;
    salesByMethod: Record<string, number>;
    totalExpenses: number;
    expensesByMethod: Record<string, number>;
    totalIncomes: number;
    incomesByMethod: Record<string, number>;
    withdrawalsTotal: number;
}

const PM_LABEL: Record<string, string> = {
    cash: 'Efectivo',
    card: 'Tarjeta',
    transfer: 'Transferencia',
};

export default function CashShiftShow({
    shift,
    totalSales,
    salesCount,
    salesByMethod,
    totalExpenses,
    expensesByMethod,
    totalIncomes,
    incomesByMethod,
    withdrawalsTotal,
}: Props) {
    const [showClose, setShowClose] = useState(false);
    const { data, setData, patch, processing, errors } = useForm({
        closing_amount: '',
        notes: shift.notes ?? '',
    });

    const fmt = (v: string | number | null) =>
        v != null ? `$${parseFloat(String(v)).toFixed(2)}` : '—';

    const expectedAmount =
        parseFloat(shift.opening_amount) + totalSales + totalIncomes - totalExpenses - withdrawalsTotal;

    const closingNum = data.closing_amount !== '' ? parseFloat(data.closing_amount) : null;
    const liveDiff   = closingNum !== null ? closingNum - expectedAmount : null;

    function handleClose(e: React.FormEvent) {
        e.preventDefault();
        patch(`/admin/cash-shifts/${shift.id}/close`);
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Turnos', href: '/admin/cash-shifts' },
                { title: `Turno #${shift.id}`, href: '' },
            ]}
        >
            <FlashMessage />
            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Turno #{shift.id}</h1>
                        <p className="text-gray-500">
                            {shift.cash_register.name} — {shift.cash_register.store.name}
                        </p>
                        <p className="text-xs text-gray-400">
                            {shift.user.name} · Abierto el {new Date(shift.opened_at).toLocaleString('es-MX')}
                            {shift.closed_at &&
                                ` · Cerrado el ${new Date(shift.closed_at).toLocaleString('es-MX')}`}
                        </p>
                    </div>
                    {shift.status === 'open' && (
                        <div className="flex gap-2">
                            <Button asChild variant="outline">
                                <Link href="/admin/sales/create">
                                    <ShoppingCart className="mr-2 h-4 w-4" />
                                    Nueva Venta
                                </Link>
                            </Button>
                            <Button variant="destructive" onClick={() => setShowClose(true)}>
                                <Lock className="mr-2 h-4 w-4" />
                                Cerrar Turno
                            </Button>
                        </div>
                    )}
                </div>

                {/* Tarjetas resumen */}
                <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Fondo inicial</p>
                        <p className="mt-1 text-xl font-bold">{fmt(shift.opening_amount)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Ventas totales</p>
                        <p className="mt-1 text-xl font-bold">{fmt(totalSales)}</p>
                        <p className="text-xs text-gray-400">
                            {salesCount} completada{salesCount !== 1 ? 's' : ''}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Movimientos</p>
                        <p className="text-sm font-semibold text-green-600">+{fmt(totalIncomes)} ingresos</p>
                        <p className="text-sm font-semibold text-red-600">-{fmt(totalExpenses)} gastos</p>
                        <p className="text-sm font-semibold text-orange-600">-{fmt(withdrawalsTotal)} retiros</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Estado</p>
                        <p className={`mt-1 text-xl font-bold ${shift.status === 'open' ? 'text-amber-600' : 'text-gray-500'}`}>
                            {shift.status === 'open' ? 'Abierto' : 'Cerrado'}
                        </p>
                    </div>
                </div>

                {/* Banner monto esperado (turno abierto) */}
                {shift.status === 'open' && (
                    <div className="mb-6 rounded-lg border border-blue-100 bg-blue-50 p-4">
                        <div className="flex items-start justify-between gap-4">
                            <div className="flex-1">
                                <p className="text-xs font-medium uppercase tracking-wide text-blue-500">
                                    Monto esperado al cerrar
                                </p>
                                <p className="mt-1 text-2xl font-bold text-blue-800">
                                    {fmt(expectedAmount)}
                                </p>
                                <div className="mt-2 flex flex-wrap gap-x-6 gap-y-0.5 text-xs text-blue-600">
                                    <span>Fondo: {fmt(shift.opening_amount)}</span>
                                    <span>+ Ventas: {fmt(totalSales)}</span>
                                    {totalIncomes > 0 && <span>+ Ingresos: {fmt(totalIncomes)}</span>}
                                    {totalExpenses > 0 && <span>- Gastos: {fmt(totalExpenses)}</span>}
                                    {withdrawalsTotal > 0 && <span>- Retiros: {fmt(withdrawalsTotal)}</span>}
                                </div>
                            </div>
                            <Button
                                variant="destructive"
                                onClick={() => setShowClose(true)}
                                className="flex-shrink-0"
                            >
                                <Lock className="mr-2 h-4 w-4" />
                                Cerrar Turno
                            </Button>
                        </div>
                    </div>
                )}

                {/* Tarjetas de cierre (turno cerrado) */}
                {shift.status === 'closed' && (
                    <div className="mb-6 grid grid-cols-3 gap-4">
                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <p className="text-xs text-gray-500">Cierre declarado</p>
                            <p className="mt-1 text-xl font-bold">{fmt(shift.closing_amount)}</p>
                        </div>
                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <p className="text-xs text-gray-500">Cierre esperado</p>
                            <p className="mt-1 text-xl font-bold">{fmt(shift.expected_amount)}</p>
                        </div>
                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <p className="text-xs text-gray-500">Diferencia</p>
                            <p
                                className={`mt-1 text-xl font-bold ${
                                    shift.difference && parseFloat(shift.difference) < 0
                                        ? 'text-red-600'
                                        : 'text-green-600'
                                }`}
                            >
                                {fmt(shift.difference)}
                            </p>
                        </div>
                    </div>
                )}

                {/* Tablas de movimientos */}
                <div className="space-y-6">
                    {/* Ventas */}
                    <Section
                        title="Ventas del turno"
                        badge={salesCount}
                        right={salesCount > 0 ? `Total: ${fmt(totalSales)}` : undefined}
                    >
                        {shift.sales.length > 0 ? (
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">Folio</th>
                                        <th className="px-4 py-3">Hora</th>
                                        <th className="px-4 py-3">Método</th>
                                        <th className="px-4 py-3">Total</th>
                                        <th className="px-4 py-3">Estado</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {shift.sales.map((sale) => (
                                        <tr key={sale.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 font-mono font-medium">
                                                <Link
                                                    href={`/admin/sales/${sale.id}`}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {sale.folio}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-gray-500">
                                                {new Date(sale.created_at).toLocaleTimeString('es-MX')}
                                            </td>
                                            <td className="px-4 py-3 text-gray-500">
                                                {PM_LABEL[sale.payment_method] ?? sale.payment_method}
                                            </td>
                                            <td className="px-4 py-3 font-medium">{fmt(sale.total)}</td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                        sale.status === 'completed'
                                                            ? 'bg-green-100 text-green-700'
                                                            : 'bg-red-100 text-red-700'
                                                    }`}
                                                >
                                                    {sale.status === 'completed' ? 'Completada' : 'Cancelada'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <EmptyRow cols={5} text="Sin ventas en este turno." />
                        )}
                    </Section>

                    {/* Ingresos */}
                    {shift.incomes.length > 0 && (
                        <Section
                            title="Ingresos"
                            badge={shift.incomes.length}
                            right={`Total: ${fmt(totalIncomes)}`}
                            accentColor="text-green-700"
                        >
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">Categoría</th>
                                        <th className="px-4 py-3">Descripción</th>
                                        <th className="px-4 py-3">Método</th>
                                        <th className="px-4 py-3">Monto</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {shift.incomes.map((inc) => (
                                        <tr key={inc.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 capitalize text-gray-600">{inc.category}</td>
                                            <td className="px-4 py-3 text-gray-600">{inc.description}</td>
                                            <td className="px-4 py-3 text-gray-500">
                                                {PM_LABEL[inc.payment_method] ?? inc.payment_method}
                                            </td>
                                            <td className="px-4 py-3 font-medium text-green-700">
                                                +{fmt(inc.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Section>
                    )}

                    {/* Gastos */}
                    {shift.expenses.length > 0 && (
                        <Section
                            title="Gastos"
                            badge={shift.expenses.length}
                            right={`Total: ${fmt(totalExpenses)}`}
                            accentColor="text-red-700"
                        >
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">Categoría</th>
                                        <th className="px-4 py-3">Descripción</th>
                                        <th className="px-4 py-3">Método</th>
                                        <th className="px-4 py-3">Monto</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {shift.expenses.map((exp) => (
                                        <tr key={exp.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 capitalize text-gray-600">{exp.category}</td>
                                            <td className="px-4 py-3 text-gray-600">{exp.description}</td>
                                            <td className="px-4 py-3 text-gray-500">
                                                {PM_LABEL[exp.payment_method] ?? exp.payment_method}
                                            </td>
                                            <td className="px-4 py-3 font-medium text-red-700">
                                                -{fmt(exp.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Section>
                    )}

                    {/* Retiros */}
                    {shift.withdrawals.length > 0 && (
                        <Section
                            title="Retiros"
                            badge={shift.withdrawals.length}
                            right={`Total: ${fmt(withdrawalsTotal)}`}
                            accentColor="text-orange-700"
                        >
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">Motivo</th>
                                        <th className="px-4 py-3">Autorizado por</th>
                                        <th className="px-4 py-3">Fecha</th>
                                        <th className="px-4 py-3">Monto</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {shift.withdrawals.map((w) => (
                                        <tr key={w.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 text-gray-600">{w.reason}</td>
                                            <td className="px-4 py-3 text-gray-500">{w.authorized_by ?? '—'}</td>
                                            <td className="px-4 py-3 text-gray-500">{w.date}</td>
                                            <td className="px-4 py-3 font-medium text-orange-700">
                                                -{fmt(w.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Section>
                    )}
                </div>
            </div>

            {/* Modal de cierre */}
            {showClose && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-lg bg-white shadow-xl">
                        <div className="flex items-start justify-between border-b px-6 py-4">
                            <div>
                                <h2 className="text-lg font-bold">Cierre de Turno #{shift.id}</h2>
                                <p className="text-sm text-gray-500">
                                    {shift.cash_register.name} — {shift.cash_register.store.name}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowClose(false)}
                                className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={handleClose} className="space-y-4 p-6">
                            {/* Desglose por método de pago */}
                            <div className="rounded-md border bg-gray-50 p-4 text-sm">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    Desglose del turno
                                </p>
                                <div className="space-y-1 text-gray-600">
                                    {/* Fondo inicial */}
                                    <ModalRow label="Fondo inicial" value={fmt(shift.opening_amount)} />

                                    {/* Ventas por método */}
                                    {Object.entries(salesByMethod).map(([method, amount]) => (
                                        <ModalRow
                                            key={`sale-${method}`}
                                            label={`+ Ventas (${PM_LABEL[method] ?? method})`}
                                            value={fmt(amount)}
                                        />
                                    ))}

                                    {/* Ingresos por método */}
                                    {Object.entries(incomesByMethod).map(([method, amount]) => (
                                        <ModalRow
                                            key={`inc-${method}`}
                                            label={`+ Ingresos (${PM_LABEL[method] ?? method})`}
                                            value={fmt(amount)}
                                            color="text-green-700"
                                        />
                                    ))}

                                    {/* Gastos por método */}
                                    {Object.entries(expensesByMethod).map(([method, amount]) => (
                                        <ModalRow
                                            key={`exp-${method}`}
                                            label={`- Gastos (${PM_LABEL[method] ?? method})`}
                                            value={`-${fmt(amount)}`}
                                            color="text-red-700"
                                        />
                                    ))}

                                    {/* Retiros */}
                                    {withdrawalsTotal > 0 && (
                                        <ModalRow
                                            label="- Retiros"
                                            value={`-${fmt(withdrawalsTotal)}`}
                                            color="text-orange-700"
                                        />
                                    )}
                                </div>
                                <div className="mt-2 flex justify-between border-t pt-2 font-bold text-gray-800">
                                    <span>Total esperado</span>
                                    <span>{fmt(expectedAmount)}</span>
                                </div>
                            </div>

                            {/* Monto contado */}
                            <div>
                                <Label htmlFor="closing_amount" className="mb-1.5 block">
                                    Monto contado / declarado ($) *
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
                                    onClick={() => setShowClose(false)}
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

/* ── Componentes auxiliares ── */

function Section({
    title,
    badge,
    right,
    accentColor,
    children,
}: {
    title: string;
    badge?: number;
    right?: string;
    accentColor?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-lg border bg-white shadow-sm">
            <div className={`flex items-center justify-between border-b px-4 py-3 ${accentColor ?? ''}`}>
                <h2 className="font-semibold">
                    {title}
                    {badge !== undefined && (
                        <span className="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-normal text-gray-500">
                            {badge}
                        </span>
                    )}
                </h2>
                {right && <span className="text-sm font-medium">{right}</span>}
            </div>
            {children}
        </div>
    );
}

function EmptyRow({ cols, text }: { cols: number; text: string }) {
    return (
        <table className="w-full">
            <tbody>
                <tr>
                    <td colSpan={cols} className="px-4 py-8 text-center text-sm text-gray-400">
                        {text}
                    </td>
                </tr>
            </tbody>
        </table>
    );
}

function ModalRow({
    label,
    value,
    color,
}: {
    label: string;
    value: string;
    color?: string;
}) {
    return (
        <div className={`flex justify-between ${color ?? ''}`}>
            <span>{label}</span>
            <span>{value}</span>
        </div>
    );
}
