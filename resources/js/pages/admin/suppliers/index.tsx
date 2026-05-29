import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Star, Trash2 } from 'lucide-react';

interface Supplier {
    id: number;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    payment_terms: string | null;
    is_active: boolean;
    purchases_count: number;
    avg_rating: string | null;
}

export default function SuppliersIndex({ suppliers }: { suppliers: PaginatedData<Supplier> }) {
    const destroy = (id: number) => {
        if (confirm('¿Eliminar proveedor?')) router.delete(`/admin/suppliers/${id}`);
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Proveedores', href: '/admin/suppliers' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Proveedores</h1>
                    <Button asChild>
                        <Link href="/admin/suppliers/create">
                            <Plus className="mr-2 h-4 w-4" /> Nuevo Proveedor
                        </Link>
                    </Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Empresa</th>
                                <th className="px-4 py-3">Contacto</th>
                                <th className="px-4 py-3">Teléfono</th>
                                <th className="px-4 py-3">Plazo pago</th>
                                <th className="px-4 py-3">Compras</th>
                                <th className="px-4 py-3">Calificación</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {suppliers.data.map((s) => (
                                <tr key={s.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{s.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{s.contact_name ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{s.phone ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{s.payment_terms ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{s.purchases_count}</td>
                                    <td className="px-4 py-3">
                                        {s.avg_rating ? (
                                            <span className="flex items-center gap-1 font-medium text-amber-600">
                                                <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                                {parseFloat(s.avg_rating).toFixed(1)}
                                            </span>
                                        ) : (
                                            <span className="text-gray-400">—</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${s.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {s.is_active ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/suppliers/${s.id}`}><Eye className="h-4 w-4" /></Link>
                                            </Button>
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/suppliers/${s.id}/edit`}><Pencil className="h-4 w-4" /></Link>
                                            </Button>
                                            <Button variant="ghost" size="sm" onClick={() => destroy(s.id)}>
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {suppliers.data.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Sin proveedores.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
