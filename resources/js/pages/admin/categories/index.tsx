import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface Category { id: number; name: string; parent: { name: string } | null; is_active: boolean; products_count: number }

export default function CategoriesIndex({ categories }: { categories: PaginatedData<Category> }) {
    const destroy = (id: number) => { if (confirm('¿Eliminar categoría?')) router.delete(`/admin/categories/${id}`); };
    return (
        <AppLayout breadcrumbs={[{ title: 'Categorías', href: '/admin/categories' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Categorías</h1>
                    <Button asChild><Link href="/admin/categories/create"><Plus className="mr-2 h-4 w-4" /> Nueva Categoría</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Nombre</th>
                                <th className="px-4 py-3">Categoría padre</th>
                                <th className="px-4 py-3">Productos</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {categories.data.map((c) => (
                                <tr key={c.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{c.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{c.parent?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{c.products_count}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {c.is_active ? 'Activa' : 'Inactiva'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="ghost" size="sm" asChild><Link href={`/admin/categories/${c.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                                        <Button variant="ghost" size="sm" onClick={() => destroy(c.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                    </td>
                                </tr>
                            ))}
                            {categories.data.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Sin categorías.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
