import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useRef } from 'react';

interface Option { id: number; name: string }

export default function ProductCreate({ categories, brands, tags }: { categories: Option[]; brands: Option[]; tags: Option[] }) {
    const fileRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, progress } = useForm<any>({
        name: '', sku: '', barcode: '', category_id: '', brand_id: '', description: '',
        price: '', cost: '', stock: '0', min_stock: '0', unit: 'pza',
        status: 'active', track_inventory: true, tags: [] as number[], images: [] as File[],
    });

    const toggleTag = (id: number) => {
        const t = data.tags.includes(id) ? data.tags.filter((t: number) => t !== id) : [...data.tags, id];
        setData('tags', t);
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Productos', href: '/admin/products' }, { title: 'Nuevo', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-3xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nuevo Producto</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/products', { forceFormData: true }); }} className="space-y-6">
                    {/* Info básica */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <h2 className="mb-4 font-semibold text-gray-700">Información básica</h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="md:col-span-2">
                                <Label>Nombre *</Label>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                            </div>
                            <div>
                                <Label>SKU</Label>
                                <Input value={data.sku} onChange={(e) => setData('sku', e.target.value)} placeholder="Código interno" />
                                {errors.sku && <p className="mt-1 text-xs text-red-500">{errors.sku}</p>}
                            </div>
                            <div>
                                <Label>Código de barras</Label>
                                <Input value={data.barcode} onChange={(e) => setData('barcode', e.target.value)} />
                            </div>
                            <div>
                                <Label>Categoría</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.category_id} onChange={(e) => setData('category_id', e.target.value)}>
                                    <option value="">— Ninguna —</option>
                                    {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <Label>Marca</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.brand_id} onChange={(e) => setData('brand_id', e.target.value)}>
                                    <option value="">— Ninguna —</option>
                                    {brands.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <Label>Descripción</Label>
                                <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            </div>
                        </div>
                    </div>

                    {/* Precios e inventario */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <h2 className="mb-4 font-semibold text-gray-700">Precios e inventario</h2>
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div>
                                <Label>Precio venta *</Label>
                                <Input type="number" step="0.01" min="0" value={data.price} onChange={(e) => setData('price', e.target.value)} />
                                {errors.price && <p className="mt-1 text-xs text-red-500">{errors.price}</p>}
                            </div>
                            <div>
                                <Label>Costo *</Label>
                                <Input type="number" step="0.01" min="0" value={data.cost} onChange={(e) => setData('cost', e.target.value)} />
                            </div>
                            <div>
                                <Label>Stock inicial *</Label>
                                <Input type="number" min="0" value={data.stock} onChange={(e) => setData('stock', e.target.value)} />
                            </div>
                            <div>
                                <Label>Stock mínimo</Label>
                                <Input type="number" min="0" value={data.min_stock} onChange={(e) => setData('min_stock', e.target.value)} />
                            </div>
                            <div>
                                <Label>Unidad</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.unit} onChange={(e) => setData('unit', e.target.value)}>
                                    {['pza', 'kg', 'lt', 'mt', 'caja', 'paquete', 'par'].map((u) => <option key={u} value={u}>{u}</option>)}
                                </select>
                            </div>
                            <div>
                                <Label>Estado</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                    <option value="out_of_stock">Sin stock</option>
                                </select>
                            </div>
                        </div>
                        <div className="mt-3 flex items-center gap-2">
                            <input type="checkbox" id="track" checked={data.track_inventory} onChange={(e) => setData('track_inventory', e.target.checked)} className="h-4 w-4" />
                            <Label htmlFor="track">Controlar inventario</Label>
                        </div>
                    </div>

                    {/* Etiquetas */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <h2 className="mb-3 font-semibold text-gray-700">Etiquetas</h2>
                        <div className="flex flex-wrap gap-2">
                            {tags.map((t) => (
                                <button key={t.id} type="button" onClick={() => toggleTag(t.id)}
                                    className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${data.tags.includes(t.id) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
                                    {t.name}
                                </button>
                            ))}
                            {tags.length === 0 && <p className="text-sm text-gray-400">No hay etiquetas. <a href="/admin/tags/create" className="text-blue-600 underline">Crear etiqueta</a></p>}
                        </div>
                    </div>

                    {/* Imágenes */}
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <h2 className="mb-3 font-semibold text-gray-700">Imágenes</h2>
                        <input ref={fileRef} type="file" multiple accept="image/*" className="hidden"
                            onChange={(e) => setData('images', Array.from(e.target.files ?? []))} />
                        <Button type="button" variant="outline" onClick={() => fileRef.current?.click()}>
                            Seleccionar imágenes
                        </Button>
                        {data.images.length > 0 && (
                            <div className="mt-3 flex flex-wrap gap-2">
                                {data.images.map((f: File, i: number) => (
                                    <div key={i} className="relative">
                                        <img src={URL.createObjectURL(f)} className="h-20 w-20 rounded-md object-cover" />
                                        <button type="button" onClick={() => setData('images', data.images.filter((_: File, j: number) => j !== i))}
                                            className="absolute -right-1 -top-1 rounded-full bg-red-500 p-0.5 text-white hover:bg-red-600">
                                            <X className="h-3 w-3" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                        {progress && <div className="mt-2 h-1 rounded bg-gray-200"><div className="h-1 rounded bg-blue-600" style={{ width: `${progress.percentage}%` }} /></div>}
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>Guardar Producto</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
