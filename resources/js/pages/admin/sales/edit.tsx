import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Product {
    id: number; name: string; sku: string | null; barcode: string | null;
    price: string; stock: number; track_inventory: boolean;
    primary_image: { url: string } | null;
}
interface SaleItem {
    product_id: number; quantity: string; price: string; discount: string;
    product: { id: number; name: string; sku: string | null };
}
interface Sale {
    id: number; folio: string; status: string;
    payment_method: string; discount: string; tax: string;
    amount_paid: string; notes: string | null;
    items: SaleItem[];
}
interface FormItem {
    product_id: number; product_name: string; quantity: string; price: string; discount: string;
}

export default function SaleEdit({ sale, products }: { sale: Sale; products: Product[] }) {
    const [search, setSearch] = useState('');

    const { data, setData, patch, processing, errors } = useForm<any>({
        payment_method: sale.payment_method,
        discount: sale.discount,
        tax: sale.tax,
        amount_paid: sale.amount_paid,
        notes: sale.notes ?? '',
        items: sale.items.map((item) => ({
            product_id: item.product_id,
            product_name: item.product.name,
            quantity: item.quantity,
            price: item.price,
            discount: item.discount,
        })) as FormItem[],
    });

    const filtered = products.filter((p) =>
        p.name.toLowerCase().includes(search.toLowerCase()) ||
        (p.sku && p.sku.toLowerCase().includes(search.toLowerCase())) ||
        (p.barcode && p.barcode.toLowerCase().includes(search.toLowerCase()))
    ).slice(0, 8);

    const addProduct = (p: Product) => {
        const exists = data.items.find((i: FormItem) => i.product_id === p.id);
        if (exists) {
            setData('items', data.items.map((i: FormItem) =>
                i.product_id === p.id ? { ...i, quantity: String(parseFloat(i.quantity) + 1) } : i,
            ));
        } else {
            setData('items', [...data.items, {
                product_id: p.id, product_name: p.name,
                quantity: '1', price: p.price, discount: '0',
            }]);
        }
        setSearch('');
    };

    const removeItem = (i: number) => setData('items', data.items.filter((_: FormItem, j: number) => j !== i));
    const setItem = (i: number, field: keyof FormItem, value: string) => {
        const items = [...data.items];
        items[i] = { ...items[i], [field]: value };
        setData('items', items);
    };

    const subtotal = data.items.reduce(
        (s: number, i: FormItem) =>
            s + (parseFloat(i.quantity) || 0) * (parseFloat(i.price) || 0) - (parseFloat(i.discount) || 0),
        0,
    );
    const tax = parseFloat(data.tax) || 0;
    const discount = parseFloat(data.discount) || 0;
    const total = subtotal - discount + tax;
    const change = (parseFloat(data.amount_paid) || 0) - total;

    return (
        <AppLayout breadcrumbs={[
            { title: 'Ventas', href: '/admin/sales' },
            { title: sale.folio, href: `/admin/sales/${sale.id}` },
            { title: 'Editar', href: '' },
        ]}>
            <FlashMessage />
            <div className="p-6">
                <h1 className="mb-2 text-2xl font-bold">Editar Venta {sale.folio}</h1>

                <div className="mb-5 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                    <span>Los cambios en los artículos ajustarán el inventario automáticamente. El total será recalculado.</span>
                </div>

                <form onSubmit={(e) => { e.preventDefault(); patch(`/admin/sales/${sale.id}`); }} className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Columna izquierda: buscador + artículos */}
                    <div className="lg:col-span-2 space-y-4">
                        {/* Buscador de productos */}
                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <Label>Agregar producto</Label>
                            <div className="relative mt-1">
                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                <Input
                                    className="pl-9"
                                    placeholder="Nombre, SKU o código de barras…"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            {search && (
                                <div className="mt-2 rounded-md border divide-y bg-white shadow-md">
                                    {filtered.map((p) => (
                                        <button key={p.id} type="button" onClick={() => addProduct(p)}
                                            className="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-gray-50">
                                            {p.primary_image && <img src={p.primary_image.url} className="h-8 w-8 rounded object-cover" alt="" />}
                                            <div className="flex-1">
                                                <p className="font-medium">{p.name}</p>
                                                {p.sku && <span className="text-xs text-gray-400">SKU: {p.sku}</span>}
                                            </div>
                                            <div className="text-right">
                                                <p className="font-medium">${parseFloat(p.price).toFixed(2)}</p>
                                                <p className={`text-xs ${p.stock <= 0 ? 'text-red-500' : 'text-gray-400'}`}>Stock: {p.stock}</p>
                                            </div>
                                        </button>
                                    ))}
                                    {filtered.length === 0 && <p className="px-3 py-2 text-sm text-gray-400">Sin resultados.</p>}
                                </div>
                            )}
                        </div>

                        {/* Tabla de artículos */}
                        <div className="rounded-lg border bg-white shadow-sm">
                            <div className="border-b px-4 py-3 font-semibold text-gray-700">Artículos ({data.items.length})</div>
                            {data.items.length === 0 ? (
                                <div className="px-4 py-10 text-center text-gray-400">
                                    <Plus className="mx-auto mb-2 h-8 w-8" />
                                    <p>Busca y agrega productos</p>
                                </div>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-gray-50 text-xs uppercase text-gray-400">
                                        <tr>
                                            <th className="px-4 py-2 text-left">Producto</th>
                                            <th className="px-4 py-2 text-right">Precio</th>
                                            <th className="px-4 py-2 text-right">Descuento</th>
                                            <th className="px-4 py-2 text-right">Cant.</th>
                                            <th className="px-4 py-2 text-right">Subtotal</th>
                                            <th className="px-4 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {data.items.map((item: FormItem, i: number) => (
                                            <tr key={i}>
                                                <td className="px-4 py-2 font-medium">{item.product_name}</td>
                                                <td className="px-4 py-2">
                                                    <Input className="w-24 text-right" type="number" step="0.01" min="0" value={item.price} onChange={(e) => setItem(i, 'price', e.target.value)} />
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Input className="w-20 text-right" type="number" step="0.01" min="0" value={item.discount} onChange={(e) => setItem(i, 'discount', e.target.value)} />
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Input className="w-20 text-right" type="number" step="0.01" min="0.01" value={item.quantity} onChange={(e) => setItem(i, 'quantity', e.target.value)} />
                                                </td>
                                                <td className="px-4 py-2 text-right font-medium">
                                                    ${((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0) - (parseFloat(item.discount) || 0)).toFixed(2)}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => removeItem(i)}><Trash2 className="h-4 w-4 text-red-400" /></Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                            {errors.items && <p className="px-4 py-2 text-xs text-red-500">{errors.items}</p>}
                        </div>
                    </div>

                    {/* Columna derecha: resumen y pago */}
                    <div className="space-y-4">
                        <div className="rounded-lg border bg-white p-4 shadow-sm space-y-3">
                            <h2 className="font-semibold text-gray-700">Resumen</h2>
                            <div className="flex justify-between text-sm"><span className="text-gray-500">Subtotal</span><span>${subtotal.toFixed(2)}</span></div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-gray-500">Descuento global</span>
                                <Input className="w-24 text-right" type="number" step="0.01" min="0" value={data.discount} onChange={(e) => setData('discount', e.target.value)} />
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-gray-500">IVA</span>
                                <Input className="w-24 text-right" type="number" step="0.01" min="0" value={data.tax} onChange={(e) => setData('tax', e.target.value)} />
                            </div>
                            <div className="flex justify-between border-t pt-2 text-lg font-bold"><span>Total</span><span>${total.toFixed(2)}</span></div>
                        </div>

                        <div className="rounded-lg border bg-white p-4 shadow-sm space-y-3">
                            <h2 className="font-semibold text-gray-700">Pago</h2>
                            <div>
                                <Label>Método de pago</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.payment_method} onChange={(e) => setData('payment_method', e.target.value)}>
                                    <option value="cash">Efectivo</option>
                                    <option value="card">Tarjeta</option>
                                    <option value="transfer">Transferencia</option>
                                    <option value="mixed">Mixto</option>
                                </select>
                            </div>
                            <div>
                                <Label>Monto recibido *</Label>
                                <Input type="number" step="0.01" min="0" value={data.amount_paid} onChange={(e) => setData('amount_paid', e.target.value)} className="text-xl font-bold" />
                                {errors.amount_paid && <p className="mt-1 text-xs text-red-500">{errors.amount_paid}</p>}
                            </div>
                            {data.amount_paid && (
                                <div className={`rounded-lg p-3 text-center ${change >= 0 ? 'bg-green-50' : 'bg-red-50'}`}>
                                    <p className="text-xs text-gray-500">Cambio</p>
                                    <p className={`text-2xl font-bold ${change >= 0 ? 'text-green-700' : 'text-red-700'}`}>${Math.abs(change).toFixed(2)}</p>
                                </div>
                            )}
                            <div>
                                <Label>Notas</Label>
                                <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </div>
                        </div>

                        <div className="flex gap-2">
                            <Button type="submit" className="flex-1" disabled={processing || data.items.length === 0}>Guardar Cambios</Button>
                            <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
