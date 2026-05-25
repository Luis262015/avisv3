import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface Tag { id: number; name: string; slug: string; products_count: number }

export default function TagsIndex({ tags }: { tags: PaginatedData<Tag> }) {
    const destroy = (id: number) => { if (confirm('¿Eliminar etiqueta?')) router.delete(`/admin/tags/${id}`); };
    return (
        <AppLayout breadcrumbs={[{ title: 'Etiquetas', href: '/admin/tags' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Etiquetas</h1>
                    <Button asChild><Link href="/admin/tags/create"><Plus className="mr-2 h-4 w-4" /> Nueva Etiqueta</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Nombre</th>
                                <th className="px-4 py-3">Slug</th>
                                <th className="px-4 py-3">Productos</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {tags.data.map((t) => (
                                <tr key={t.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{t.name}</td>
                                    <td className="px-4 py-3 text-gray-500 font-mono text-xs">{t.slug}</td>
                                    <td className="px-4 py-3 text-gray-500">{t.products_count}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="ghost" size="sm" asChild><Link href={`/admin/tags/${t.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                                        <Button variant="ghost" size="sm" onClick={() => destroy(t.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                    </td>
                                </tr>
                            ))}
                            {tags.data.length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">Sin etiquetas.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
