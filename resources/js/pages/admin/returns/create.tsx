import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { router, useForm } from '@inertiajs/react';

interface SaleItem { id: number; product_id: number; quantity: string; price: string; product: { name: string; sku: string | null } }
interface Sale {
    id: number; folio: string; total: string; customer: { name: string } | null; items: SaleItem[];
}
interface RecentSale { id: number; folio: string; total: string; created_at: string; customer: { name: string } | null }
interface ReturnItem { sale_item_id: number; product_id: number; quantity: string }

export default function ReturnCreate({ sale, recentSales }: { sale: Sale | null; recentSales: RecentSale[] }) {
    const { data, setData, post, processing, errors } = useForm<any>({
        sale_id: sale?.id?.toString() ?? '',
        date: new Date().toISOString().split('T')[0],
        reason: '', refund_method: 'cash', restock: true, notes: '',
        items: (sale?.items.map((i) => ({ sale_item_id: i.id, product_id: i.product_id, quantity: '0' })) ?? []) as ReturnItem[],
    });

    const pickSale = (id: string) => {
        if (id) router.get('/admin/returns/create', { sale_id: id }, { preserveState: false });
    };

    const setQty = (i: number, value: string) => {
        const items = [...data.items];
        items[i] = { ...items[i], quantity: value };
        setData('items', items);
    };

    const refund = sale
        ? sale.items.reduce((s, it, i) => s + (parseFloat(data.items[i]?.quantity) || 0) * parseFloat(it.price), 0)
        : 0;

    return (
        <AppLayout breadcrumbs={[{ title: 'Devoluciones', href: '/admin/returns' }, { title: 'Nueva', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-3xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nueva Devolución</h1>

                {errors.status && <p className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-600">{errors.status}</p>}

                {!sale ? (
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <Label>Selecciona la venta a devolver</Label>
                        <select className="mt-1 w-full rounded-md border px-3 py-2 text-sm" defaultValue="" onChange={(e) => pickSale(e.target.value)}>
                            <option value="">— Seleccionar venta —</option>
                            {recentSales.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.folio} · ${parseFloat(s.total).toFixed(2)} · {s.customer?.name ?? 'Consumidor final'} · {new Date(s.created_at).toLocaleDateString()}
                                </option>
                            ))}
                        </select>
                    </div>
                ) : (
                    <form onSubmit={(e) => { e.preventDefault(); post('/admin/returns'); }} className="space-y-6">
                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <p className="text-sm text-gray-500">Venta <span className="font-mono font-semibold text-gray-800">{sale.folio}</span> · {sale.customer?.name ?? 'Consumidor final'}</p>
                        </div>

                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <h2 className="mb-3 font-semibold text-gray-700">Productos a devolver</h2>
                            {errors.items && <p className="mb-2 text-xs text-red-500">{errors.items}</p>}
                            <table className="w-full text-sm">
                                <thead className="border-b text-left text-xs uppercase text-gray-400">
                                    <tr>
                                        <th className="py-2">Producto</th>
                                        <th className="py-2 text-right">Vendido</th>
                                        <th className="py-2 text-right">Precio</th>
                                        <th className="py-2 text-right">A devolver</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {sale.items.map((it, i) => (
                                        <tr key={it.id}>
                                            <td className="py-2 font-medium">{it.product.name}</td>
                                            <td className="py-2 text-right text-gray-500">{it.quantity}</td>
                                            <td className="py-2 text-right">${parseFloat(it.price).toFixed(2)}</td>
                                            <td className="py-2 text-right">
                                                <Input className="w-24 text-right" type="number" min="0" max={it.quantity} step="0.01"
                                                    value={data.items[i]?.quantity ?? '0'} onChange={(e) => setQty(i, e.target.value)} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <p className="mt-3 text-right text-lg font-bold">Reembolso estimado: ${refund.toFixed(2)}</p>
                        </div>

                        <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label>Fecha *</Label>
                                    <Input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                                </div>
                                <div>
                                    <Label>Método de reembolso</Label>
                                    <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.refund_method} onChange={(e) => setData('refund_method', e.target.value)}>
                                        <option value="cash">Efectivo</option>
                                        <option value="card">Tarjeta</option>
                                        <option value="transfer">Transferencia</option>
                                        <option value="store_credit">Crédito en tienda</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <Label>Motivo</Label>
                                <Input value={data.reason} onChange={(e) => setData('reason', e.target.value)} />
                            </div>
                            <div>
                                <Label>Notas</Label>
                                <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </div>
                            <div className="flex items-center gap-2">
                                <input type="checkbox" id="restock" checked={data.restock} onChange={(e) => setData('restock', e.target.checked)} className="h-4 w-4" />
                                <Label htmlFor="restock">Reintegrar productos al inventario al completar</Label>
                            </div>
                        </div>

                        <div className="flex gap-2">
                            <Button type="submit" disabled={processing}>Registrar Devolución</Button>
                            <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
