import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';

interface Store { id: number; name: string }
interface Product { id: number; name: string; sku: string | null }
interface Item { product_id: string; quantity: string }

export default function StockTransferCreate({ stores, products }: { stores: Store[]; products: Product[] }) {
    const { data, setData, post, processing, errors } = useForm<any>({
        from_store_id: '',
        to_store_id:   '',
        notes:         '',
        items:         [{ product_id: '', quantity: '1' }] as Item[],
    });

    const addItem = () => setData('items', [...data.items, { product_id: '', quantity: '1' }]);
    const removeItem = (i: number) => setData('items', data.items.filter((_: Item, j: number) => j !== i));
    const setItem = (i: number, field: keyof Item, value: string) => {
        const items = [...data.items];
        items[i] = { ...items[i], [field]: value };
        setData('items', items);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/stock-transfers');
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'Transferencias', href: '/admin/stock-transfers' },
            { title: 'Nueva', href: '' },
        ]}>
            <FlashMessage />
            <div className="mx-auto max-w-3xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nueva transferencia de stock</h1>

                <form onSubmit={submit} className="space-y-6">
                    {/* Stores */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <h2 className="mb-4 font-semibold text-gray-700">Tiendas</h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label>Tienda origen *</Label>
                                <select
                                    className="w-full rounded-md border px-3 py-2 text-sm"
                                    value={data.from_store_id}
                                    onChange={(e) => setData('from_store_id', e.target.value)}
                                >
                                    <option value="">— Seleccionar —</option>
                                    {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                                {errors.from_store_id && <p className="mt-1 text-xs text-red-500">{errors.from_store_id}</p>}
                            </div>
                            <div>
                                <Label>Tienda destino *</Label>
                                <select
                                    className="w-full rounded-md border px-3 py-2 text-sm"
                                    value={data.to_store_id}
                                    onChange={(e) => setData('to_store_id', e.target.value)}
                                >
                                    <option value="">— Seleccionar —</option>
                                    {stores
                                        .filter((s) => s.id.toString() !== data.from_store_id)
                                        .map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                                {errors.to_store_id && <p className="mt-1 text-xs text-red-500">{errors.to_store_id}</p>}
                            </div>
                        </div>
                        <div className="mt-4">
                            <Label>Notas</Label>
                            <textarea
                                className="w-full rounded-md border px-3 py-2 text-sm"
                                rows={2}
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                placeholder="Motivo de la transferencia (opcional)"
                            />
                        </div>
                    </div>

                    {/* Items */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="font-semibold text-gray-700">Productos</h2>
                            <Button type="button" variant="outline" size="sm" onClick={addItem}>
                                <Plus className="mr-1 h-4 w-4" /> Agregar
                            </Button>
                        </div>
                        {errors.items && <p className="mb-2 text-xs text-red-500">{errors.items}</p>}

                        <div className="space-y-2">
                            <div className="grid grid-cols-12 gap-2 text-xs font-semibold uppercase text-gray-400">
                                <div className="col-span-9">Producto</div>
                                <div className="col-span-2">Cantidad</div>
                                <div className="col-span-1" />
                            </div>
                            {data.items.map((item: Item, i: number) => (
                                <div key={i} className="grid grid-cols-12 items-center gap-2">
                                    <div className="col-span-9">
                                        <select
                                            className="w-full rounded-md border px-3 py-2 text-sm"
                                            value={item.product_id}
                                            onChange={(e) => setItem(i, 'product_id', e.target.value)}
                                        >
                                            <option value="">— Seleccionar producto —</option>
                                            {products.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.name}{p.sku ? ` (${p.sku})` : ''}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="col-span-2">
                                        <Input
                                            type="number"
                                            min="1"
                                            value={item.quantity}
                                            onChange={(e) => setItem(i, 'quantity', e.target.value)}
                                        />
                                    </div>
                                    <div className="col-span-1 text-right">
                                        {data.items.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => removeItem(i)}
                                            >
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>Crear transferencia</Button>
                        <Button type="button" variant="outline" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
