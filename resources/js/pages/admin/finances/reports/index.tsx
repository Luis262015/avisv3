import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface Summary {
    sales: number; incomes: number; purchases: number; expenses: number; withdrawals: number;
    total_income: number; total_outflow: number; net_result: number;
}
interface Receivables {
    generated_count: number; generated_amount: number; outstanding_count: number;
    outstanding_balance: number; overdue_balance: number; collected_in_period: number;
}
interface Payables {
    generated_count: number; generated_amount: number; outstanding_count: number;
    outstanding_balance: number; overdue_balance: number; paid_in_period: number;
}
interface ByCategory { category: string | null; count: number; total_amount: number }
interface Evolution {
    month: string; sales: number; incomes: number; purchases: number;
    expenses: number; withdrawals: number; net_result: number;
}
interface Filters { period: string; from: string; to: string; store_id?: string }
interface DropdownItem { id: number; name: string }

const periods = [
    { value: 'month', label: 'Mensual' },
    { value: 'quarter', label: 'Trimestral' },
    { value: 'year', label: 'Anual' },
    { value: 'custom', label: 'Entre fechas' },
];

export default function FinancialReports({
    summary, receivables, payables, expensesByCategory, incomesByCategory, evolution, filters, stores,
}: {
    summary: Summary; receivables: Receivables; payables: Payables;
    expensesByCategory: ByCategory[]; incomesByCategory: ByCategory[];
    evolution: Evolution[]; filters: Filters; stores: DropdownItem[];
}) {
    const [form, setForm] = useState({
        period: filters.period ?? 'month',
        from: filters.from ?? '',
        to: filters.to ?? '',
        store_id: filters.store_id ?? '',
    });

    const fmt = (v: number) =>
        `Bs ${Number(v).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const apply = (overrides: Partial<typeof form> = {}) => {
        const payload = { ...form, ...overrides };
        if (payload.period !== 'custom') {
            payload.from = '';
            payload.to = '';
        }
        router.get('/admin/financial-reports', payload as Record<string, string>, { preserveState: true, preserveScroll: true });
    };

    const selectPeriod = (period: string) => {
        setForm((f) => ({ ...f, period }));
        if (period !== 'custom') apply({ period });
    };

    const periodLabel = `${filters.from} → ${filters.to}`;
    const net = summary.net_result;

    return (
        <AppLayout breadcrumbs={[{ title: 'Finanzas', href: '/admin/expenses' }, { title: 'Reporte financiero', href: '' }]}>
            <FlashMessage />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold">Reporte Financiero</h1>
                        <p className="text-sm text-gray-500">Periodo: <span className="font-medium text-gray-700">{periodLabel}</span></p>
                    </div>
                </div>

                {/* Filtros */}
                <div className="rounded-lg border bg-white p-4 shadow-sm">
                    <div className="flex flex-wrap items-end gap-4">
                        <div>
                            <Label className="mb-1 block">Periodo</Label>
                            <div className="inline-flex rounded-md border bg-gray-50 p-1">
                                {periods.map((p) => (
                                    <button
                                        key={p.value}
                                        type="button"
                                        onClick={() => selectPeriod(p.value)}
                                        className={`rounded px-3 py-1.5 text-sm font-medium transition ${
                                            form.period === p.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'
                                        }`}
                                    >
                                        {p.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {form.period === 'custom' && (
                            <>
                                <div>
                                    <Label>Desde</Label>
                                    <Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} />
                                </div>
                                <div>
                                    <Label>Hasta</Label>
                                    <Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} />
                                </div>
                            </>
                        )}

                        <div>
                            <Label>Tienda</Label>
                            <select
                                className="w-full rounded-md border px-3 py-2 text-sm"
                                value={form.store_id}
                                onChange={(e) => setForm({ ...form, store_id: e.target.value })}
                            >
                                <option value="">Todas</option>
                                {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>

                        <Button type="button" onClick={() => apply()}>Aplicar</Button>
                    </div>
                </div>

                {/* KPIs */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    {[
                        { label: 'Ventas', value: fmt(summary.sales), color: 'text-blue-600' },
                        { label: 'Ingresos', value: fmt(summary.incomes), color: 'text-emerald-600' },
                        { label: 'Compras', value: fmt(summary.purchases), color: 'text-amber-600' },
                        { label: 'Gastos', value: fmt(summary.expenses), color: 'text-orange-600' },
                        { label: 'Retiros', value: fmt(summary.withdrawals), color: 'text-red-600' },
                        { label: 'Resultado neto', value: fmt(net), color: net >= 0 ? 'text-green-700' : 'text-red-700' },
                    ].map((c) => (
                        <div key={c.label} className="rounded-lg border bg-white p-4 text-center shadow-sm">
                            <p className="text-xs uppercase text-gray-500">{c.label}</p>
                            <p className={`mt-1 text-lg font-bold ${c.color}`}>{c.value}</p>
                        </div>
                    ))}
                </div>

                {/* Estado de resultados */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Estado financiero del periodo</div>
                    <table className="w-full text-sm">
                        <tbody className="divide-y">
                            <tr className="bg-emerald-50/50">
                                <td className="px-4 py-2 font-semibold text-emerald-800" colSpan={2}>Ingresos</td>
                            </tr>
                            <tr><td className="px-4 py-2 pl-8">Ventas</td><td className="px-4 py-2 text-right">{fmt(summary.sales)}</td></tr>
                            <tr><td className="px-4 py-2 pl-8">Otros ingresos</td><td className="px-4 py-2 text-right">{fmt(summary.incomes)}</td></tr>
                            <tr className="font-semibold">
                                <td className="px-4 py-2 pl-8">Total ingresos</td>
                                <td className="px-4 py-2 text-right text-emerald-700">{fmt(summary.total_income)}</td>
                            </tr>

                            <tr className="bg-orange-50/50">
                                <td className="px-4 py-2 font-semibold text-orange-800" colSpan={2}>Egresos</td>
                            </tr>
                            <tr><td className="px-4 py-2 pl-8">Compras</td><td className="px-4 py-2 text-right">{fmt(summary.purchases)}</td></tr>
                            <tr><td className="px-4 py-2 pl-8">Gastos</td><td className="px-4 py-2 text-right">{fmt(summary.expenses)}</td></tr>
                            <tr><td className="px-4 py-2 pl-8">Retiros</td><td className="px-4 py-2 text-right">{fmt(summary.withdrawals)}</td></tr>
                            <tr className="font-semibold">
                                <td className="px-4 py-2 pl-8">Total egresos</td>
                                <td className="px-4 py-2 text-right text-orange-700">{fmt(summary.total_outflow)}</td>
                            </tr>

                            <tr className={`text-base font-bold ${net >= 0 ? 'bg-green-50' : 'bg-red-50'}`}>
                                <td className="px-4 py-3">Resultado neto</td>
                                <td className={`px-4 py-3 text-right ${net >= 0 ? 'text-green-700' : 'text-red-700'}`}>{fmt(net)}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {/* CxC y CxP */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-4 py-3 font-semibold text-gray-700">Cuentas por cobrar</div>
                        <table className="w-full text-sm">
                            <tbody className="divide-y">
                                <tr><td className="px-4 py-2 text-gray-600">Generadas en el periodo</td><td className="px-4 py-2 text-right">{receivables.generated_count} · {fmt(receivables.generated_amount)}</td></tr>
                                <tr><td className="px-4 py-2 text-gray-600">Cobrado en el periodo</td><td className="px-4 py-2 text-right text-emerald-600">{fmt(receivables.collected_in_period)}</td></tr>
                                <tr><td className="px-4 py-2 text-gray-600">Cuentas pendientes</td><td className="px-4 py-2 text-right">{receivables.outstanding_count}</td></tr>
                                <tr className="font-semibold"><td className="px-4 py-2">Saldo por cobrar</td><td className="px-4 py-2 text-right">{fmt(receivables.outstanding_balance)}</td></tr>
                                <tr><td className="px-4 py-2 text-gray-600">Saldo vencido</td><td className="px-4 py-2 text-right text-red-600">{fmt(receivables.overdue_balance)}</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-4 py-3 font-semibold text-gray-700">Cuentas por pagar</div>
                        <table className="w-full text-sm">
                            <tbody className="divide-y">
                                <tr><td className="px-4 py-2 text-gray-600">Generadas en el periodo</td><td className="px-4 py-2 text-right">{payables.generated_count} · {fmt(payables.generated_amount)}</td></tr>
                                <tr><td className="px-4 py-2 text-gray-600">Pagado en el periodo</td><td className="px-4 py-2 text-right text-orange-600">{fmt(payables.paid_in_period)}</td></tr>
                                <tr><td className="px-4 py-2 text-gray-600">Cuentas pendientes</td><td className="px-4 py-2 text-right">{payables.outstanding_count}</td></tr>
                                <tr className="font-semibold"><td className="px-4 py-2">Saldo por pagar</td><td className="px-4 py-2 text-right">{fmt(payables.outstanding_balance)}</td></tr>
                                <tr><td className="px-4 py-2 text-gray-600">Saldo vencido</td><td className="px-4 py-2 text-right text-red-600">{fmt(payables.overdue_balance)}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Desgloses por categoría */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-4 py-3 font-semibold text-gray-700">Gastos por categoría</div>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th className="px-4 py-3">Categoría</th><th className="px-4 py-3 text-right"># </th><th className="px-4 py-3 text-right">Total</th></tr>
                            </thead>
                            <tbody className="divide-y">
                                {expensesByCategory.map((row, i) => (
                                    <tr key={i} className="hover:bg-gray-50">
                                        <td className="px-4 py-2 font-medium">{row.category ?? 'Sin categoría'}</td>
                                        <td className="px-4 py-2 text-right">{row.count}</td>
                                        <td className="px-4 py-2 text-right font-medium">{fmt(row.total_amount)}</td>
                                    </tr>
                                ))}
                                {expensesByCategory.length === 0 && <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                            </tbody>
                        </table>
                    </div>

                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-4 py-3 font-semibold text-gray-700">Ingresos por categoría</div>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th className="px-4 py-3">Categoría</th><th className="px-4 py-3 text-right"># </th><th className="px-4 py-3 text-right">Total</th></tr>
                            </thead>
                            <tbody className="divide-y">
                                {incomesByCategory.map((row, i) => (
                                    <tr key={i} className="hover:bg-gray-50">
                                        <td className="px-4 py-2 font-medium">{row.category ?? 'Sin categoría'}</td>
                                        <td className="px-4 py-2 text-right">{row.count}</td>
                                        <td className="px-4 py-2 text-right font-medium">{fmt(row.total_amount)}</td>
                                    </tr>
                                ))}
                                {incomesByCategory.length === 0 && <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Evolución mensual */}
                <div className="overflow-x-auto rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Evolución mensual</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Mes</th>
                                <th className="px-4 py-3 text-right">Ventas</th>
                                <th className="px-4 py-3 text-right">Ingresos</th>
                                <th className="px-4 py-3 text-right">Compras</th>
                                <th className="px-4 py-3 text-right">Gastos</th>
                                <th className="px-4 py-3 text-right">Retiros</th>
                                <th className="px-4 py-3 text-right">Resultado</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {evolution.map((row, i) => (
                                <tr key={i} className="hover:bg-gray-50">
                                    <td className="px-4 py-2 font-medium">{row.month}</td>
                                    <td className="px-4 py-2 text-right">{fmt(row.sales)}</td>
                                    <td className="px-4 py-2 text-right">{fmt(row.incomes)}</td>
                                    <td className="px-4 py-2 text-right">{fmt(row.purchases)}</td>
                                    <td className="px-4 py-2 text-right">{fmt(row.expenses)}</td>
                                    <td className="px-4 py-2 text-right">{fmt(row.withdrawals)}</td>
                                    <td className={`px-4 py-2 text-right font-semibold ${row.net_result >= 0 ? 'text-green-700' : 'text-red-700'}`}>{fmt(row.net_result)}</td>
                                </tr>
                            ))}
                            {evolution.length === 0 && <tr><td colSpan={7} className="px-4 py-6 text-center text-gray-400">Sin datos en el periodo.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
