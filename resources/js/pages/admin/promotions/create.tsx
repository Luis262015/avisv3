import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

interface Product { id: number; name: string; sku: string | null; price: string }
interface Category { id: number; name: string }
interface ComboItem { product_id: number; quantity: string }

export default function PromotionCreate({ products, categories }: { products: Product[]; categories: Category[] }) {
    const { data, setData, post, processing, errors } = useForm<any>({
        name: '', code: '', type: 'percentage', value: '0', combo_price: '0', scope: 'all', min_purchase: '0',
        buy_qty: '', get_qty: '', starts_at: '', ends_at: '', usage_limit: '', is_active: true, notes: '',
        product_ids: [] as number[], category_ids: [] as number[], combo_items: [] as ComboItem[],
    });

    const toggleId = (field: 'product_ids' | 'category_ids', id: number) => {
        const list = data[field] as number[];
        setData(field, list.includes(id) ? list.filter((x) => x !== id) : [...list, id]);
    };

    const productById = (id: number) => products.find((p) => p.id === id);
    const addComboItem = (id: number) => {
        if (!id || (data.combo_items as ComboItem[]).some((c) => c.product_id === id)) return;
        setData('combo_items', [...data.combo_items, { product_id: id, quantity: '1' }]);
    };
    const setComboQty = (id: number, qty: string) =>
        setData('combo_items', (data.combo_items as ComboItem[]).map((c) => (c.product_id === id ? { ...c, quantity: qty } : c)));
    const removeComboItem = (id: number) =>
        setData('combo_items', (data.combo_items as ComboItem[]).filter((c) => c.product_id !== id));

    const comboRegular = (data.combo_items as ComboItem[]).reduce(
        (s, c) => s + parseFloat(productById(c.product_id)?.price ?? '0') * (parseFloat(c.quantity) || 0), 0,
    );
    const comboSavings = comboRegular - (parseFloat(data.combo_price) || 0);
    const isCombo = data.type === 'combo';

    return (
        <AppLayout breadcrumbs={[{ title: 'Promociones', href: '/admin/promotions' }, { title: 'Nueva', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-2xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nueva Promoción</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/promotions'); }} className="space-y-5">
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Nombre *</Label>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                            </div>
                            <div>
                                <Label>Código de cupón (opcional)</Label>
                                <Input value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="Ej: VERANO10" />
                                {errors.code && <p className="mt-1 text-xs text-red-500">{errors.code}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Tipo *</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                    <option value="percentage">Porcentaje (%)</option>
                                    <option value="fixed">Monto fijo ($)</option>
                                    <option value="buy_x_get_y">Lleva X paga Y</option>
                                    <option value="combo">Combo de productos</option>
                                </select>
                            </div>
                            {isCombo ? (
                                <div>
                                    <Label>Precio del combo * ($)</Label>
                                    <Input type="number" step="0.01" min="0" value={data.combo_price} onChange={(e) => setData('combo_price', e.target.value)} />
                                    {errors.combo_price && <p className="mt-1 text-xs text-red-500">{errors.combo_price}</p>}
                                </div>
                            ) : data.type !== 'buy_x_get_y' ? (
                                <div>
                                    <Label>Valor * {data.type === 'percentage' ? '(%)' : '($)'}</Label>
                                    <Input type="number" step="0.01" min="0" value={data.value} onChange={(e) => setData('value', e.target.value)} />
                                    {errors.value && <p className="mt-1 text-xs text-red-500">{errors.value}</p>}
                                </div>
                            ) : (
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <Label>Lleva</Label>
                                        <Input type="number" min="1" value={data.buy_qty} onChange={(e) => setData('buy_qty', e.target.value)} />
                                        {errors.buy_qty && <p className="mt-1 text-xs text-red-500">{errors.buy_qty}</p>}
                                    </div>
                                    <div>
                                        <Label>Gratis</Label>
                                        <Input type="number" min="1" value={data.get_qty} onChange={(e) => setData('get_qty', e.target.value)} />
                                        {errors.get_qty && <p className="mt-1 text-xs text-red-500">{errors.get_qty}</p>}
                                    </div>
                                </div>
                            )}
                        </div>

                        {!isCombo && (
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label>Alcance *</Label>
                                    <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.scope} onChange={(e) => setData('scope', e.target.value)}>
                                        <option value="all">Toda la venta</option>
                                        <option value="product">Productos específicos</option>
                                        <option value="category">Categorías específicas</option>
                                    </select>
                                </div>
                                <div>
                                    <Label>Compra mínima ($)</Label>
                                    <Input type="number" step="0.01" min="0" value={data.min_purchase} onChange={(e) => setData('min_purchase', e.target.value)} />
                                </div>
                            </div>
                        )}

                        {!isCombo && data.scope === 'product' && (
                            <div>
                                <Label>Productos aplicables</Label>
                                {errors.product_ids && <p className="text-xs text-red-500">{errors.product_ids}</p>}
                                <div className="mt-1 max-h-48 overflow-y-auto rounded-md border p-2 text-sm">
                                    {products.map((p) => (
                                        <label key={p.id} className="flex items-center gap-2 py-1">
                                            <input type="checkbox" checked={data.product_ids.includes(p.id)} onChange={() => toggleId('product_ids', p.id)} />
                                            {p.name}{p.sku ? ` (${p.sku})` : ''}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}
                        {!isCombo && data.scope === 'category' && (
                            <div>
                                <Label>Categorías aplicables</Label>
                                {errors.category_ids && <p className="text-xs text-red-500">{errors.category_ids}</p>}
                                <div className="mt-1 max-h-48 overflow-y-auto rounded-md border p-2 text-sm">
                                    {categories.map((c) => (
                                        <label key={c.id} className="flex items-center gap-2 py-1">
                                            <input type="checkbox" checked={data.category_ids.includes(c.id)} onChange={() => toggleId('category_ids', c.id)} />
                                            {c.name}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

                        {isCombo && (
                            <div className="rounded-md border border-dashed bg-gray-50 p-3 space-y-3">
                                <div>
                                    <Label>Productos del combo *</Label>
                                    <p className="text-xs text-gray-500">Selecciona los productos que se venderán juntos en este combo.</p>
                                    {errors.combo_items && <p className="mt-1 text-xs text-red-500">{errors.combo_items}</p>}
                                </div>
                                <select
                                    className="w-full rounded-md border px-3 py-2 text-sm"
                                    value=""
                                    onChange={(e) => { addComboItem(Number(e.target.value)); e.target.value = ''; }}
                                >
                                    <option value="">+ Agregar producto al combo…</option>
                                    {products
                                        .filter((p) => !(data.combo_items as ComboItem[]).some((c) => c.product_id === p.id))
                                        .map((p) => (
                                            <option key={p.id} value={p.id}>{p.name}{p.sku ? ` (${p.sku})` : ''} — ${parseFloat(p.price).toFixed(2)}</option>
                                        ))}
                                </select>

                                {(data.combo_items as ComboItem[]).length > 0 && (
                                    <div className="overflow-hidden rounded-md border bg-white">
                                        <table className="w-full text-sm">
                                            <thead className="border-b bg-gray-50 text-xs uppercase text-gray-400">
                                                <tr>
                                                    <th className="px-3 py-2 text-left">Producto</th>
                                                    <th className="px-3 py-2 text-right">Precio</th>
                                                    <th className="px-3 py-2 text-right">Cant.</th>
                                                    <th className="px-3 py-2 text-right">Subtotal</th>
                                                    <th className="px-3 py-2" />
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {(data.combo_items as ComboItem[]).map((c) => {
                                                    const p = productById(c.product_id);
                                                    const line = parseFloat(p?.price ?? '0') * (parseFloat(c.quantity) || 0);
                                                    return (
                                                        <tr key={c.product_id}>
                                                            <td className="px-3 py-2 font-medium">{p?.name ?? `#${c.product_id}`}</td>
                                                            <td className="px-3 py-2 text-right text-gray-500">${parseFloat(p?.price ?? '0').toFixed(2)}</td>
                                                            <td className="px-3 py-2 text-right">
                                                                <Input className="w-16 text-right" type="number" step="0.01" min="0.01" value={c.quantity} onChange={(e) => setComboQty(c.product_id, e.target.value)} />
                                                            </td>
                                                            <td className="px-3 py-2 text-right">${line.toFixed(2)}</td>
                                                            <td className="px-3 py-2 text-right">
                                                                <Button type="button" variant="ghost" size="sm" onClick={() => removeComboItem(c.product_id)}><Trash2 className="h-4 w-4 text-red-400" /></Button>
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                )}

                                <div className="flex flex-wrap justify-end gap-4 text-sm">
                                    <span className="text-gray-500">Precio regular: <span className="font-medium text-gray-700">${comboRegular.toFixed(2)}</span></span>
                                    <span className={comboSavings >= 0 ? 'text-green-600' : 'text-red-600'}>
                                        {comboSavings >= 0 ? 'Ahorro' : 'Recargo'}: <span className="font-semibold">${Math.abs(comboSavings).toFixed(2)}</span>
                                    </span>
                                </div>
                            </div>
                        )}

                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <Label>Inicio</Label>
                                <Input type="date" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                            </div>
                            <div>
                                <Label>Fin</Label>
                                <Input type="date" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                                {errors.ends_at && <p className="mt-1 text-xs text-red-500">{errors.ends_at}</p>}
                            </div>
                            <div>
                                <Label>Límite de usos</Label>
                                <Input type="number" min="1" value={data.usage_limit} onChange={(e) => setData('usage_limit', e.target.value)} />
                            </div>
                        </div>
                        <div>
                            <Label>Notas</Label>
                            <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </div>
                        <div className="flex items-center gap-2">
                            <input type="checkbox" id="active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="h-4 w-4" />
                            <Label htmlFor="active">Activa</Label>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>Guardar</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
