import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { router, useForm } from '@inertiajs/react';
import { Star, Trash2, X } from 'lucide-react';
import { useRef } from 'react';

interface Option { id: number; name: string }
interface ProductImage { id: number; url: string; is_primary: boolean; sort_order: number }
interface Product {
    id: number; name: string; sku: string | null; barcode: string | null;
    category_id: number | null; brand_id: number | null; description: string | null;
    price: string; cost: string; stock: number; min_stock: number; unit: string;
    status: string; track_inventory: boolean;
    tags: { id: number }[]; images: ProductImage[];
}

export default function ProductEdit({ product, categories, brands, tags }: { product: Product; categories: Option[]; brands: Option[]; tags: Option[] }) {
    const fileRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, progress } = useForm<any>({
        _method: 'PUT',
        name: product.name, sku: product.sku ?? '', barcode: product.barcode ?? '',
        category_id: product.category_id?.toString() ?? '', brand_id: product.brand_id?.toString() ?? '',
        description: product.description ?? '', price: product.price, cost: product.cost,
        min_stock: product.min_stock, unit: product.unit,
        status: product.status, track_inventory: product.track_inventory,
        tags: product.tags.map((t) => t.id), images: [] as File[],
    });

    const toggleTag = (id: number) => {
        const t = data.tags.includes(id) ? data.tags.filter((t: number) => t !== id) : [...data.tags, id];
        setData('tags', t);
    };
    const setPrimary = (imageId: number) => router.patch(`/admin/product-images/${imageId}/primary`);
    const destroyImage = (imageId: number) => { if (confirm('¿Eliminar imagen?')) router.delete(`/admin/product-images/${imageId}`); };

    return (
        <AppLayout breadcrumbs={[{ title: 'Productos', href: '/admin/products' }, { title: 'Editar', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-3xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Editar Producto</h1>
                <form onSubmit={(e) => { e.preventDefault(); post(`/admin/products/${product.id}`, { forceFormData: true }); }} className="space-y-6">
                    <div className="rounded-lg border bg-card p-4 shadow-sm">
                        <h2 className="mb-4 font-semibold text-gray-700">Información básica</h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="md:col-span-2">
                                <Label htmlFor="campo-nombre">Nombre *</Label>
                                <Input id="campo-nombre" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="campo-sku">SKU</Label>
                                <Input id="campo-sku" value={data.sku} onChange={(e) => setData('sku', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="campo-codigo-de-barras">Código de barras</Label>
                                <Input id="campo-codigo-de-barras" value={data.barcode} onChange={(e) => setData('barcode', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="campo-categoria">Categoría</Label>
                                <select id="campo-categoria" className="w-full rounded-md border px-3 py-2 text-sm" value={data.category_id} onChange={(e) => setData('category_id', e.target.value)}>
                                    <option value="">— Ninguna —</option>
                                    {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="campo-marca">Marca</Label>
                                <select id="campo-marca" className="w-full rounded-md border px-3 py-2 text-sm" value={data.brand_id} onChange={(e) => setData('brand_id', e.target.value)}>
                                    <option value="">— Ninguna —</option>
                                    {brands.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <Label htmlFor="campo-descripcion">Descripción</Label>
                                <textarea id="campo-descripcion" className="w-full rounded-md border px-3 py-2 text-sm" rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-lg border bg-card p-4 shadow-sm">
                        <h2 className="mb-4 font-semibold text-gray-700">Precios e inventario</h2>
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div>
                                <Label htmlFor="campo-precio-venta">Precio venta *</Label>
                                <Input id="campo-precio-venta" type="number" step="0.01" min="0" value={data.price} onChange={(e) => setData('price', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="campo-costo">Costo</Label>
                                <Input id="campo-costo" type="number" step="0.01" min="0" value={data.cost} onChange={(e) => setData('cost', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="campo-stock-minimo-general">Stock mínimo general</Label>
                                <Input id="campo-stock-minimo-general" type="number" min="0" value={data.min_stock} onChange={(e) => setData('min_stock', e.target.value)} />
                                <p className="mt-1 text-xs text-neutral-500">
                                    Cada tienda puede fijar el suyo desde Inventario.
                                </p>
                            </div>
                            <div>
                                <Label>Existencias</Label>
                                <p className="mt-1 text-sm">
                                    <span className="text-lg font-semibold">{product.stock}</span>
                                    <span className="text-neutral-500"> en total</span>
                                </p>
                                <a href="/admin/inventory" className="text-xs text-indigo-600 hover:underline">
                                    Ver y ajustar por tienda →
                                </a>
                            </div>
                            <div>
                                <Label htmlFor="campo-unidad">Unidad</Label>
                                <select id="campo-unidad" className="w-full rounded-md border px-3 py-2 text-sm" value={data.unit} onChange={(e) => setData('unit', e.target.value)}>
                                    {['pza', 'kg', 'lt', 'mt', 'caja', 'paquete', 'par'].map((u) => <option key={u} value={u}>{u}</option>)}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="campo-estado">Estado</Label>
                                <select id="campo-estado" className="w-full rounded-md border px-3 py-2 text-sm" value={data.status} onChange={(e) => setData('status', e.target.value)}>
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

                    <div className="rounded-lg border bg-card p-4 shadow-sm">
                        <h2 className="mb-3 font-semibold text-gray-700">Etiquetas</h2>
                        <div className="flex flex-wrap gap-2">
                            {tags.map((t) => (
                                <button key={t.id} type="button" onClick={() => toggleTag(t.id)}
                                    className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${data.tags.includes(t.id) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
                                    {t.name}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Imágenes existentes */}
                    {product.images.length > 0 && (
                        <div className="rounded-lg border bg-card p-4 shadow-sm">
                            <h2 className="mb-3 font-semibold text-gray-700">Imágenes actuales</h2>
                            <div className="flex flex-wrap gap-3">
                                {product.images.map((img) => (
                                    <div key={img.id} className="relative">
                                        <img src={img.url} className="h-24 w-24 rounded-md object-cover" />
                                        {img.is_primary && (
                                            <span className="absolute bottom-1 left-1 rounded bg-yellow-400 px-1 py-0.5 text-xs font-bold text-foreground">Principal</span>
                                        )}
                                        <div className="absolute right-1 top-1 flex flex-col gap-1">
                                            {!img.is_primary && (
                                                <button type="button" onClick={() => setPrimary(img.id)} className="rounded bg-card p-0.5 shadow hover:bg-yellow-50">
                                                    <Star className="h-3 w-3 text-yellow-500" />
                                                </button>
                                            )}
                                            <button aria-label="Eliminar" type="button" onClick={() => destroyImage(img.id)} className="rounded bg-card p-0.5 shadow hover:bg-red-50">
                                                <Trash2 aria-hidden="true" className="h-3 w-3 text-red-500" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="rounded-lg border bg-card p-4 shadow-sm">
                        <h2 className="mb-3 font-semibold text-gray-700">Agregar imágenes</h2>
                        <input ref={fileRef} type="file" multiple accept="image/*" className="hidden"
                            onChange={(e) => setData('images', Array.from(e.target.files ?? []))} />
                        <Button type="button" variant="outline" onClick={() => fileRef.current?.click()}>Seleccionar imágenes</Button>
                        {data.images.length > 0 && (
                            <div className="mt-3 flex flex-wrap gap-2">
                                {data.images.map((f: File, i: number) => (
                                    <div key={i} className="relative">
                                        <img src={URL.createObjectURL(f)} className="h-20 w-20 rounded-md object-cover" />
                                        <button aria-label="Quitar" type="button" onClick={() => setData('images', data.images.filter((_: File, j: number) => j !== i))}
                                            className="absolute -right-1 -top-1 rounded-full bg-red-500 p-0.5 text-white">
                                            <X aria-hidden="true" className="h-3 w-3" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                        {progress && <div className="mt-2 h-1 rounded bg-gray-200"><div className="h-1 rounded bg-blue-600" style={{ width: `${progress.percentage}%` }} /></div>}
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>Actualizar Producto</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
