import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface Summary {
    total_purchases: number; total_amount: number; avg_amount: number;
    total_tax: number; unpaid_amount: number; partial_amount: number;
}
interface BySupplier { supplier_id: number | null; count: number; total_amount: number; supplier: { name: string; avg_rating: string | null } | null }
interface ByProduct { product_id: number; total_quantity: number; total_amount: number; avg_cost: number; product: { name: string; sku: string | null } | null }
interface CostEvolution { month: string; total_amount: number; count: number; total_tax: number }
interface Compliance {
    supplier_id: number; total_orders: number; completed_orders: number; paid_orders: number;
    total_amount: number; unpaid_amount: number;
    supplier: { name: string; avg_rating: string | null; payment_terms: string | null; lead_time_days: number | null } | null;
}
interface Filters { from?: string; to?: string; supplier_id?: string; store_id?: string }
interface DropdownItem { id: number; name: string }

export default function PurchasesReports({
    summary, bySupplier, byProduct, costEvolution, compliance, filters, suppliers, stores,
}: {
    summary: Summary; bySupplier: BySupplier[]; byProduct: ByProduct[];
    costEvolution: CostEvolution[]; compliance: Compliance[];
    filters: Filters; suppliers: DropdownItem[]; stores: DropdownItem[];
}) {
    const [form, setForm] = useState({ from: filters.from ?? '', to: filters.to ?? '', supplier_id: filters.supplier_id ?? '', store_id: filters.store_id ?? '' });
    const fmt = (v: number) => `$${v.toFixed(2)}`;
    const pct = (a: number, b: number) => b > 0 ? `${Math.round((a / b) * 100)}%` : '—';

    const apply = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/purchases-reports', form as any, { preserveState: true });
    };
    const clear = () => {
        setForm({ from: '', to: '', supplier_id: '', store_id: '' });
        router.get('/admin/purchases-reports', {});
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Compras', href: '/admin/purchases' }, { title: 'Reportes', href: '' }]}>
            <FlashMessage />
            <div className="p-6 space-y-6">
                <h1 className="text-2xl font-bold">Reportes de Compras</h1>

                {/* Filters */}
                <div className="rounded-lg border bg-white p-4 shadow-sm">
                    <form onSubmit={apply} className="grid grid-cols-2 gap-4 md:grid-cols-5">
                        <div>
                            <Label>Desde</Label>
                            <Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} />
                        </div>
                        <div>
                            <Label>Hasta</Label>
                            <Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} />
                        </div>
                        <div>
                            <Label>Proveedor</Label>
                            <select className="w-full rounded-md border px-3 py-2 text-sm" value={form.supplier_id} onChange={(e) => setForm({ ...form, supplier_id: e.target.value })}>
                                <option value="">Todos</option>
                                {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <Label>Tienda</Label>
                            <select className="w-full rounded-md border px-3 py-2 text-sm" value={form.store_id} onChange={(e) => setForm({ ...form, store_id: e.target.value })}>
                                <option value="">Todas</option>
                                {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>
                        <div className="flex items-end gap-2">
                            <Button type="submit">Aplicar</Button>
                            <Button type="button" variant="outline" onClick={clear}>Limpiar</Button>
                        </div>
                    </form>
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    {[
                        { label: 'Total compras', value: summary.total_purchases.toString(), color: 'text-blue-600' },
                        { label: 'Monto total', value: fmt(summary.total_amount), color: 'text-gray-800' },
                        { label: 'Promedio', value: fmt(summary.avg_amount), color: 'text-gray-600' },
                        { label: 'IVA total', value: fmt(summary.total_tax), color: 'text-gray-600' },
                        { label: 'Sin pagar', value: fmt(summary.unpaid_amount), color: 'text-red-600' },
                        { label: 'Pago parcial', value: fmt(summary.partial_amount), color: 'text-yellow-600' },
                    ].map((c) => (
                        <div key={c.label} className="rounded-lg border bg-white p-4 shadow-sm text-center">
                            <p className="text-xs uppercase text-gray-500">{c.label}</p>
                            <p className={`mt-1 text-lg font-bold ${c.color}`}>{c.value}</p>
                        </div>
                    ))}
                </div>

                {/* By supplier */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Compras por proveedor</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Proveedor</th>
                                <th className="px-4 py-3 text-right"># Compras</th>
                                <th className="px-4 py-3 text-right">Total</th>
                                <th className="px-4 py-3 text-right">Calificación</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {bySupplier.map((row, i) => (
                                <tr key={i} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{row.supplier?.name ?? 'Sin proveedor'}</td>
                                    <td className="px-4 py-3 text-right">{row.count}</td>
                                    <td className="px-4 py-3 text-right font-medium">{fmt(row.total_amount)}</td>
                                    <td className="px-4 py-3 text-right">
                                        {row.supplier?.avg_rating ? (
                                            <span className="font-medium text-amber-600">★ {parseFloat(row.supplier.avg_rating).toFixed(1)}</span>
                                        ) : '—'}
                                    </td>
                                </tr>
                            ))}
                            {bySupplier.length === 0 && <tr><td colSpan={4} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                        </tbody>
                    </table>
                </div>

                {/* By product */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Productos más comprados (top 50)</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3">SKU</th>
                                <th className="px-4 py-3 text-right">Cantidad total</th>
                                <th className="px-4 py-3 text-right">Costo promedio</th>
                                <th className="px-4 py-3 text-right">Monto total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {byProduct.map((row, i) => (
                                <tr key={i} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{row.product?.name ?? '—'}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-400">{row.product?.sku ?? '—'}</td>
                                    <td className="px-4 py-3 text-right">{parseFloat(row.total_quantity as any).toFixed(2)}</td>
                                    <td className="px-4 py-3 text-right">{fmt(row.avg_cost)}</td>
                                    <td className="px-4 py-3 text-right font-medium">{fmt(row.total_amount)}</td>
                                </tr>
                            ))}
                            {byProduct.length === 0 && <tr><td colSpan={5} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                        </tbody>
                    </table>
                </div>

                {/* Cost evolution */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Evolución mensual de compras</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Mes</th>
                                <th className="px-4 py-3 text-right"># Compras</th>
                                <th className="px-4 py-3 text-right">Monto total</th>
                                <th className="px-4 py-3 text-right">IVA</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {costEvolution.map((row, i) => (
                                <tr key={i} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{row.month}</td>
                                    <td className="px-4 py-3 text-right">{row.count}</td>
                                    <td className="px-4 py-3 text-right font-medium">{fmt(row.total_amount)}</td>
                                    <td className="px-4 py-3 text-right text-gray-500">{fmt(row.total_tax)}</td>
                                </tr>
                            ))}
                            {costEvolution.length === 0 && <tr><td colSpan={4} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                        </tbody>
                    </table>
                </div>

                {/* Compliance */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Cumplimiento por proveedor</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Proveedor</th>
                                <th className="px-4 py-3">Plazo pago</th>
                                <th className="px-4 py-3 text-right">Compras</th>
                                <th className="px-4 py-3 text-right">Completadas</th>
                                <th className="px-4 py-3 text-right">% Compl.</th>
                                <th className="px-4 py-3 text-right">Pagadas</th>
                                <th className="px-4 py-3 text-right">Saldo pendiente</th>
                                <th className="px-4 py-3 text-right">Calificación</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {compliance.map((row, i) => (
                                <tr key={i} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{row.supplier?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500 text-xs">{row.supplier?.payment_terms ?? '—'}</td>
                                    <td className="px-4 py-3 text-right">{row.total_orders}</td>
                                    <td className="px-4 py-3 text-right">{row.completed_orders}</td>
                                    <td className="px-4 py-3 text-right">
                                        <span className={`font-semibold ${parseFloat(pct(row.completed_orders, row.total_orders)) >= 80 ? 'text-green-600' : 'text-red-600'}`}>
                                            {pct(row.completed_orders, row.total_orders)}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">{row.paid_orders}</td>
                                    <td className="px-4 py-3 text-right font-medium text-red-600">{fmt(row.unpaid_amount)}</td>
                                    <td className="px-4 py-3 text-right">
                                        {row.supplier?.avg_rating ? (
                                            <span className="font-medium text-amber-600">★ {parseFloat(row.supplier.avg_rating).toFixed(1)}</span>
                                        ) : '—'}
                                    </td>
                                </tr>
                            ))}
                            {compliance.length === 0 && <tr><td colSpan={8} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
