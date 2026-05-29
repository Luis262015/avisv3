import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';

interface Customer { id: number; name: string }
interface Product { id: number; name: string; sku: string | null; price: string }
interface Item { product_id: string; quantity: string; price: string; discount: string }
interface Quote {
    id: number; customer_id: number | null; date: string; valid_until: string | null;
    tax: string; discount: string; notes: string | null;
    items: { product_id: number; quantity: string; price: string; discount: string }[];
}

export default function QuoteEdit({ quote, customers, products }: { quote: Quote; customers: Customer[]; products: Product[] }) {
    const { data, setData, put, processing, errors } = useForm<any>({
        customer_id: quote.customer_id?.toString() ?? '',
        date: quote.date?.split('T')[0] ?? '',
        valid_until: quote.valid_until?.split('T')[0] ?? '',
        tax: quote.tax ?? '0', discount: quote.discount ?? '0', notes: quote.notes ?? '',
        items: quote.items.map((i) => ({
            product_id: i.product_id.toString(), quantity: i.quantity, price: i.price, discount: i.discount,
        })) as Item[],
    });

    const addItem = () => setData('items', [...data.items, { product_id: '', quantity: '1', price: '0', discount: '0' }]);
    const removeItem = (i: number) => setData('items', data.items.filter((_: Item, j: number) => j !== i));
    const setItem = (i: number, field: keyof Item, value: string) => {
        const items = [...data.items];
        items[i] = { ...items[i], [field]: value };
        if (field === 'product_id' && value) {
            const prod = products.find((p) => p.id === parseInt(value));
            if (prod) items[i].price = prod.price;
        }
        setData('items', items);
    };

    const lineTotal = (i: Item) => (parseFloat(i.quantity) || 0) * (parseFloat(i.price) || 0) - (parseFloat(i.discount) || 0);
    const subtotal = data.items.reduce((s: number, i: Item) => s + lineTotal(i), 0);
    const total = subtotal - (parseFloat(data.discount) || 0) + (parseFloat(data.tax) || 0);

    return (
        <AppLayout breadcrumbs={[{ title: 'Cotizaciones', href: '/admin/quotes' }, { title: 'Editar', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-4xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Editar Cotización</h1>
                <form onSubmit={(e) => { e.preventDefault(); put(`/admin/quotes/${quote.id}`); }} className="space-y-6">
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                        <h2 className="font-semibold text-gray-700">Datos generales</h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <Label>Cliente</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.customer_id} onChange={(e) => setData('customer_id', e.target.value)}>
                                    <option value="">— Sin cliente —</option>
                                    {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <Label>Fecha *</Label>
                                <Input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                                {errors.date && <p className="mt-1 text-xs text-red-500">{errors.date}</p>}
                            </div>
                            <div>
                                <Label>Válida hasta</Label>
                                <Input type="date" value={data.valid_until} onChange={(e) => setData('valid_until', e.target.value)} />
                                {errors.valid_until && <p className="mt-1 text-xs text-red-500">{errors.valid_until}</p>}
                            </div>
                        </div>
                        <div>
                            <Label>Notas</Label>
                            <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </div>
                    </div>

                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="font-semibold text-gray-700">Productos</h2>
                            <Button type="button" variant="outline" size="sm" onClick={addItem}><Plus className="mr-1 h-4 w-4" /> Agregar</Button>
                        </div>
                        {errors.items && <p className="mb-2 text-xs text-red-500">{errors.items}</p>}
                        <div className="space-y-2">
                            <div className="grid grid-cols-12 gap-2 text-xs font-semibold uppercase text-gray-400">
                                <div className="col-span-5">Producto</div>
                                <div className="col-span-2">Cantidad</div>
                                <div className="col-span-2">Precio</div>
                                <div className="col-span-1">Desc.</div>
                                <div className="col-span-1 text-right">Subtotal</div>
                                <div className="col-span-1" />
                            </div>
                            {data.items.map((item: Item, i: number) => (
                                <div key={i} className="grid grid-cols-12 items-center gap-2">
                                    <div className="col-span-5">
                                        <select className="w-full rounded-md border px-3 py-2 text-sm" value={item.product_id} onChange={(e) => setItem(i, 'product_id', e.target.value)}>
                                            <option value="">— Seleccionar —</option>
                                            {products.map((p) => <option key={p.id} value={p.id}>{p.name}{p.sku ? ` (${p.sku})` : ''}</option>)}
                                        </select>
                                    </div>
                                    <div className="col-span-2">
                                        <Input type="number" min="0.01" step="0.01" value={item.quantity} onChange={(e) => setItem(i, 'quantity', e.target.value)} />
                                    </div>
                                    <div className="col-span-2">
                                        <Input type="number" min="0" step="0.01" value={item.price} onChange={(e) => setItem(i, 'price', e.target.value)} />
                                    </div>
                                    <div className="col-span-1">
                                        <Input type="number" min="0" step="0.01" value={item.discount} onChange={(e) => setItem(i, 'discount', e.target.value)} />
                                    </div>
                                    <div className="col-span-1 text-right text-sm font-medium">${lineTotal(item).toFixed(2)}</div>
                                    <div className="col-span-1 text-right">
                                        {data.items.length > 1 && (
                                            <Button type="button" variant="ghost" size="sm" onClick={() => removeItem(i)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 grid grid-cols-2 gap-4 border-t pt-4">
                            <div className="space-y-2">
                                <div>
                                    <Label>Descuento global ($)</Label>
                                    <Input type="number" step="0.01" min="0" value={data.discount} onChange={(e) => setData('discount', e.target.value)} />
                                </div>
                                <div>
                                    <Label>IVA ($)</Label>
                                    <Input type="number" step="0.01" min="0" value={data.tax} onChange={(e) => setData('tax', e.target.value)} />
                                </div>
                            </div>
                            <div className="text-right text-sm">
                                <p className="text-gray-500">Subtotal: <span className="font-medium">${subtotal.toFixed(2)}</span></p>
                                <p className="text-gray-500">Descuento: <span className="font-medium">-${(parseFloat(data.discount) || 0).toFixed(2)}</span></p>
                                <p className="text-gray-500">IVA: <span className="font-medium">${(parseFloat(data.tax) || 0).toFixed(2)}</span></p>
                                <p className="text-lg font-bold">Total: ${total.toFixed(2)}</p>
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>Actualizar</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
