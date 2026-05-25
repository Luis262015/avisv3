import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface Brand { id: number; name: string; description: string | null; is_active: boolean; products_count: number }

export default function BrandsIndex({ brands }: { brands: PaginatedData<Brand> }) {
    const destroy = (id: number) => {
        if (confirm('¿Eliminar esta marca?')) router.delete(`/admin/brands/${id}`);
    };
    return (
        <AppLayout breadcrumbs={[{ title: 'Marcas', href: '/admin/brands' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Marcas</h1>
                    <Button asChild><Link href="/admin/brands/create"><Plus className="mr-2 h-4 w-4" /> Nueva Marca</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Nombre</th>
                                <th className="px-4 py-3">Productos</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {brands.data.map((b) => (
                                <tr key={b.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{b.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{b.products_count}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${b.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {b.is_active ? 'Activa' : 'Inactiva'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="ghost" size="sm" asChild><Link href={`/admin/brands/${b.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                                        <Button variant="ghost" size="sm" onClick={() => destroy(b.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                    </td>
                                </tr>
                            ))}
                            {brands.data.length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">Sin marcas.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
