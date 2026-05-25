import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, Plus, Trash2 } from 'lucide-react';

interface Supplier { id: number; name: string }
interface Store { id: number; name: string }
interface Product { id: number; name: string; sku: string | null; cost: string }
interface PurchaseItem {
    product_id: number; quantity: string; cost: string;
    product: { id: number; name: string; sku: string | null };
}
interface Purchase {
    id: number; folio: string; status: string;
    supplier_id: number | null; store_id: number | null; date: string; tax: string; notes: string | null;
    supplier: { id: number; name: string } | null;
    store: { id: number; name: string } | null;
    items: PurchaseItem[];
}

interface FormItem { product_id: string; quantity: string; cost: string }

export default function PurchaseEdit({
    purchase, suppliers, stores, products,
}: {
    purchase: Purchase; suppliers: Supplier[]; stores: Store[]; products: Product[];
}) {
    const { data, setData, patch, processing, errors } = useForm<any>({
        supplier_id: purchase.supplier_id?.toString() ?? '',
        store_id:    purchase.store_id?.toString() ?? '',
        date: purchase.date.substring(0, 10),
        tax: purchase.tax,
        notes: purchase.notes ?? '',
        items: purchase.items.map((item) => ({
            product_id: item.product_id.toString(),
            quantity: item.quantity,
            cost: item.cost,
        })) as FormItem[],
    });

    const addItem = () => setData('items', [...data.items, { product_id: '', quantity: '1', cost: '0' }]);
    const removeItem = (i: number) => setData('items', data.items.filter((_: FormItem, j: number) => j !== i));
    const setItem = (i: number, field: keyof FormItem, value: string) => {
        const items = [...data.items];
        items[i] = { ...items[i], [field]: value };
        if (field === 'product_id' && value) {
            const prod = products.find((p) => p.id === parseInt(value));
            if (prod) items[i].cost = prod.cost;
        }
        setData('items', items);
    };

    const subtotal = data.items.reduce(
        (s: number, i: FormItem) => s + (parseFloat(i.quantity) || 0) * (parseFloat(i.cost) || 0), 0,
    );
    const total = subtotal + (parseFloat(data.tax) || 0);

    const productName = (productId: string) =>
        products.find((p) => p.id === parseInt(productId))?.name ?? '—';

    return (
        <AppLayout breadcrumbs={[
            { title: 'Compras', href: '/admin/purchases' },
            { title: purchase.folio, href: `/admin/purchases/${purchase.id}` },
            { title: 'Editar', href: '' },
        ]}>
            <FlashMessage />
            <div className="mx-auto max-w-4xl p-6">
                <h1 className="mb-2 text-2xl font-bold">Editar Compra {purchase.folio}</h1>

                {purchase.status === 'received' && (
                    <div className="mb-5 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                        <span>Esta compra ya fue <strong>recibida</strong>. Los cambios en los artículos ajustarán el inventario automáticamente.</span>
                    </div>
                )}

                <form onSubmit={(e) => { e.preventDefault(); patch(`/admin/purchases/${purchase.id}`); }} className="space-y-6">
                    {/* Datos generales */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <h2 className="mb-4 font-semibold text-gray-700">Datos generales</h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label>Proveedor</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)}>
                                    <option value="">— Sin proveedor —</option>
                                    {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <Label>Tienda destino</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.store_id} onChange={(e) => setData('store_id', e.target.value)}>
                                    <option value="">— Sin tienda —</option>
                                    {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                                {errors.store_id && <p className="mt-1 text-xs text-red-500">{errors.store_id}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 mt-4">
                            <div>
                                <Label>Fecha *</Label>
                                <Input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                                {errors.date && <p className="mt-1 text-xs text-red-500">{errors.date}</p>}
                            </div>
                            <div>
                                <Label>IVA ($)</Label>
                                <Input type="number" step="0.01" min="0" value={data.tax} onChange={(e) => setData('tax', e.target.value)} />
                            </div>
                        </div>
                        <div className="mt-4">
                            <Label>Notas</Label>
                            <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </div>
                    </div>

                    {/* Productos */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="font-semibold text-gray-700">Productos</h2>
                            <Button type="button" variant="outline" size="sm" onClick={addItem}><Plus className="mr-1 h-4 w-4" /> Agregar</Button>
                        </div>
                        {errors.items && <p className="mb-2 text-xs text-red-500">{errors.items}</p>}
                        <div className="space-y-2">
                            <div className="grid grid-cols-12 gap-2 text-xs font-semibold uppercase text-gray-400">
                                <div className="col-span-6">Producto</div>
                                <div className="col-span-2">Cantidad</div>
                                <div className="col-span-2">Costo unit.</div>
                                <div className="col-span-1">Subtotal</div>
                                <div className="col-span-1" />
                            </div>
                            {data.items.map((item: FormItem, i: number) => (
                                <div key={i} className="grid grid-cols-12 items-center gap-2">
                                    <div className="col-span-6">
                                        <select className="w-full rounded-md border px-3 py-2 text-sm" value={item.product_id} onChange={(e) => setItem(i, 'product_id', e.target.value)}>
                                            <option value="">— Seleccionar —</option>
                                            {products.map((p) => <option key={p.id} value={p.id}>{p.name}{p.sku ? ` (${p.sku})` : ''}</option>)}
                                        </select>
                                    </div>
                                    <div className="col-span-2">
                                        <Input type="number" min="0.01" step="0.01" value={item.quantity} onChange={(e) => setItem(i, 'quantity', e.target.value)} />
                                    </div>
                                    <div className="col-span-2">
                                        <Input type="number" min="0" step="0.01" value={item.cost} onChange={(e) => setItem(i, 'cost', e.target.value)} />
                                    </div>
                                    <div className="col-span-1 text-right text-sm font-medium">
                                        ${((parseFloat(item.quantity) || 0) * (parseFloat(item.cost) || 0)).toFixed(2)}
                                    </div>
                                    <div className="col-span-1 text-right">
                                        {data.items.length > 1 && (
                                            <Button type="button" variant="ghost" size="sm" onClick={() => removeItem(i)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 border-t pt-4 text-right text-sm">
                            <p className="text-gray-500">Subtotal: <span className="font-medium">${subtotal.toFixed(2)}</span></p>
                            <p className="text-gray-500">IVA: <span className="font-medium">${parseFloat(data.tax || '0').toFixed(2)}</span></p>
                            <p className="text-lg font-bold">Total: ${total.toFixed(2)}</p>
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
