import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface Summary {
    total_sales: number; total_amount: number; avg_ticket: number;
    total_discount: number; total_tax: number; cancelled: number;
}
interface ByProduct { product_id: number; total_quantity: number; total_amount: number; product: { name: string; sku: string | null } | null }
interface ByCategory { category: string | null; total_quantity: number; total_amount: number }
interface BySeller { user_id: number; count: number; total_amount: number; user: { name: string } | null }
interface ByPayment { payment_method: string; count: number; total_amount: number }
interface Evolution { month: string; total_amount: number; count: number }
interface TopCustomer { customer_id: number; count: number; total_amount: number; customer: { name: string } | null }
interface Filters { from?: string; to?: string; store_id?: string; user_id?: string }
interface DropdownItem { id: number; name: string }

const paymentLabels: Record<string, string> = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia', mixed: 'Mixto' };

export default function SalesReports({
    summary, byProduct, byCategory, bySeller, byPaymentMethod, salesEvolution, topCustomers, filters, stores, sellers,
}: {
    summary: Summary; byProduct: ByProduct[]; byCategory: ByCategory[]; bySeller: BySeller[];
    byPaymentMethod: ByPayment[]; salesEvolution: Evolution[]; topCustomers: TopCustomer[];
    filters: Filters; stores: DropdownItem[]; sellers: DropdownItem[];
}) {
    const [form, setForm] = useState({ from: filters.from ?? '', to: filters.to ?? '', store_id: filters.store_id ?? '', user_id: filters.user_id ?? '' });
    const fmt = (v: number) => `$${Number(v).toFixed(2)}`;

    const apply = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/sales-reports', form as any, { preserveState: true });
    };
    const clear = () => {
        setForm({ from: '', to: '', store_id: '', user_id: '' });
        router.get('/admin/sales-reports', {});
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Ventas', href: '/admin/sales' }, { title: 'Reportes', href: '' }]}>
            <FlashMessage />
            <div className="space-y-6 p-6">
                <h1 className="text-2xl font-bold">Reportes y Análisis de Ventas</h1>

                <div className="rounded-lg border bg-white p-4 shadow-sm">
                    <form onSubmit={apply} className="grid grid-cols-2 gap-4 md:grid-cols-5">
                        <div><Label>Desde</Label><Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} /></div>
                        <div><Label>Hasta</Label><Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} /></div>
                        <div>
                            <Label>Tienda</Label>
                            <select className="w-full rounded-md border px-3 py-2 text-sm" value={form.store_id} onChange={(e) => setForm({ ...form, store_id: e.target.value })}>
                                <option value="">Todas</option>
                                {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <Label>Vendedor</Label>
                            <select className="w-full rounded-md border px-3 py-2 text-sm" value={form.user_id} onChange={(e) => setForm({ ...form, user_id: e.target.value })}>
                                <option value="">Todos</option>
                                {sellers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>
                        <div className="flex items-end gap-2">
                            <Button type="submit">Aplicar</Button>
                            <Button type="button" variant="outline" onClick={clear}>Limpiar</Button>
                        </div>
                    </form>
                </div>

                <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    {[
                        { label: 'Ventas', value: summary.total_sales.toString(), color: 'text-blue-600' },
                        { label: 'Monto total', value: fmt(summary.total_amount), color: 'text-gray-800' },
                        { label: 'Ticket promedio', value: fmt(summary.avg_ticket), color: 'text-gray-600' },
                        { label: 'Descuentos', value: fmt(summary.total_discount), color: 'text-green-600' },
                        { label: 'IVA total', value: fmt(summary.total_tax), color: 'text-gray-600' },
                        { label: 'Canceladas', value: summary.cancelled.toString(), color: 'text-red-600' },
                    ].map((c) => (
                        <div key={c.label} className="rounded-lg border bg-white p-4 shadow-sm text-center">
                            <p className="text-xs uppercase text-gray-500">{c.label}</p>
                            <p className={`mt-1 text-lg font-bold ${c.color}`}>{c.value}</p>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-4 py-3 font-semibold text-gray-700">Ventas por vendedor</div>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th className="px-4 py-3">Vendedor</th><th className="px-4 py-3 text-right"># Ventas</th><th className="px-4 py-3 text-right">Total</th></tr>
                            </thead>
                            <tbody className="divide-y">
                                {bySeller.map((row, i) => (
                                    <tr key={i} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 font-medium">{row.user?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-right">{row.count}</td>
                                        <td className="px-4 py-3 text-right font-medium">{fmt(row.total_amount)}</td>
                                    </tr>
                                ))}
                                {bySeller.length === 0 && <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                            </tbody>
                        </table>
                    </div>

                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-4 py-3 font-semibold text-gray-700">Ventas por método de pago</div>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th className="px-4 py-3">Método</th><th className="px-4 py-3 text-right"># Ventas</th><th className="px-4 py-3 text-right">Total</th></tr>
                            </thead>
                            <tbody className="divide-y">
                                {byPaymentMethod.map((row, i) => (
                                    <tr key={i} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 font-medium">{paymentLabels[row.payment_method] ?? row.payment_method}</td>
                                        <td className="px-4 py-3 text-right">{row.count}</td>
                                        <td className="px-4 py-3 text-right font-medium">{fmt(row.total_amount)}</td>
                                    </tr>
                                ))}
                                {byPaymentMethod.length === 0 && <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Productos más vendidos (top 50)</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3">SKU</th>
                                <th className="px-4 py-3 text-right">Cantidad</th>
                                <th className="px-4 py-3 text-right">Monto total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {byProduct.map((row, i) => (
                                <tr key={i} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{row.product?.name ?? '—'}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-400">{row.product?.sku ?? '—'}</td>
                                    <td className="px-4 py-3 text-right">{Number(row.total_quantity).toFixed(2)}</td>
                                    <td className="px-4 py-3 text-right font-medium">{fmt(row.total_amount)}</td>
                                </tr>
                            ))}
                            {byProduct.length === 0 && <tr><td colSpan={4} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                        </tbody>
                    </table>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-4 py-3 font-semibold text-gray-700">Ventas por categoría</div>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th className="px-4 py-3">Categoría</th><th className="px-4 py-3 text-right">Cantidad</th><th className="px-4 py-3 text-right">Total</th></tr>
                            </thead>
                            <tbody className="divide-y">
                                {byCategory.map((row, i) => (
                                    <tr key={i} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 font-medium">{row.category ?? 'Sin categoría'}</td>
                                        <td className="px-4 py-3 text-right">{Number(row.total_quantity).toFixed(2)}</td>
                                        <td className="px-4 py-3 text-right font-medium">{fmt(row.total_amount)}</td>
                                    </tr>
                                ))}
                                {byCategory.length === 0 && <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                            </tbody>
                        </table>
                    </div>

                    <div className="rounded-lg border bg-white shadow-sm">
                        <div className="border-b px-4 py-3 font-semibold text-gray-700">Mejores clientes (top 20)</div>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th className="px-4 py-3">Cliente</th><th className="px-4 py-3 text-right"># Ventas</th><th className="px-4 py-3 text-right">Total</th></tr>
                            </thead>
                            <tbody className="divide-y">
                                {topCustomers.map((row, i) => (
                                    <tr key={i} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 font-medium">{row.customer?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-right">{row.count}</td>
                                        <td className="px-4 py-3 text-right font-medium">{fmt(row.total_amount)}</td>
                                    </tr>
                                ))}
                                {topCustomers.length === 0 && <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Evolución mensual de ventas</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th className="px-4 py-3">Mes</th><th className="px-4 py-3 text-right"># Ventas</th><th className="px-4 py-3 text-right">Monto total</th></tr>
                        </thead>
                        <tbody className="divide-y">
                            {salesEvolution.map((row, i) => (
                                <tr key={i} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{row.month}</td>
                                    <td className="px-4 py-3 text-right">{row.count}</td>
                                    <td className="px-4 py-3 text-right font-medium">{fmt(row.total_amount)}</td>
                                </tr>
                            ))}
                            {salesEvolution.length === 0 && <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
