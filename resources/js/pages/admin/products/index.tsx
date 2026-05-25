import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { AlertTriangle, Pencil, Plus, Trash2 } from 'lucide-react';

interface Product {
    id: number; name: string; sku: string | null; price: string; stock: number; min_stock: number;
    status: string; deleted_at: string | null;
    category: { name: string } | null;
    brand: { name: string } | null;
    primary_image: { url: string } | null;
}

const statusColors: Record<string, string> = {
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-100 text-gray-500',
    out_of_stock: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = { active: 'Activo', inactive: 'Inactivo', out_of_stock: 'Sin stock' };

export default function ProductsIndex({ products }: { products: PaginatedData<Product> }) {
    const destroy = (id: number) => { if (confirm('¿Eliminar producto?')) router.delete(`/admin/products/${id}`); };
    return (
        <AppLayout breadcrumbs={[{ title: 'Productos', href: '/admin/products' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Productos</h1>
                    <Button asChild><Link href="/admin/products/create"><Plus className="mr-2 h-4 w-4" /> Nuevo Producto</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3">SKU</th>
                                <th className="px-4 py-3">Categoría</th>
                                <th className="px-4 py-3">Precio</th>
                                <th className="px-4 py-3">Stock</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {products.data.map((p) => (
                                <tr key={p.id} className={`hover:bg-gray-50 ${p.deleted_at ? 'opacity-50' : ''}`}>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            {p.primary_image ? (
                                                <img src={p.primary_image.url} alt={p.name} className="h-10 w-10 rounded-md object-cover" />
                                            ) : (
                                                <div className="flex h-10 w-10 items-center justify-center rounded-md bg-gray-100 text-gray-400 text-xs">N/A</div>
                                            )}
                                            <div>
                                                <p className="font-medium">{p.name}</p>
                                                {p.brand && <p className="text-xs text-gray-400">{p.brand.name}</p>}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-500">{p.sku ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{p.category?.name ?? '—'}</td>
                                    <td className="px-4 py-3 font-medium">${parseFloat(p.price).toFixed(2)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`flex items-center gap-1 ${p.stock <= p.min_stock && p.min_stock > 0 ? 'text-red-600' : ''}`}>
                                            {p.stock <= p.min_stock && p.min_stock > 0 && <AlertTriangle className="h-3 w-3" />}
                                            {p.stock}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[p.status]}`}>
                                            {statusLabels[p.status]}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {!p.deleted_at && (
                                            <>
                                                <Button variant="ghost" size="sm" asChild><Link href={`/admin/products/${p.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                                                <Button variant="ghost" size="sm" onClick={() => destroy(p.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {products.data.length === 0 && <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">Sin productos.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
