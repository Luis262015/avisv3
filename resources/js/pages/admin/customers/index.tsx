import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';

interface Customer {
    id: number;
    name: string;
    document_type: 'ci' | 'nit' | 'none';
    document_number: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
    sales_count: number;
    quotes_count: number;
    sales_orders_count: number;
}

const docLabel = (c: Customer) =>
    c.document_type !== 'none' && c.document_number ? `${c.document_type.toUpperCase()} ${c.document_number}` : '—';

export default function CustomersIndex({ customers }: { customers: PaginatedData<Customer> }) {
    const destroy = (id: number) => {
        if (confirm('¿Eliminar cliente?')) router.delete(`/admin/customers/${id}`);
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Clientes', href: '/admin/customers' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Clientes</h1>
                    <Button asChild>
                        <Link href="/admin/customers/create"><Plus className="mr-2 h-4 w-4" /> Nuevo Cliente</Link>
                    </Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Nombre</th>
                                <th className="px-4 py-3">Documento</th>
                                <th className="px-4 py-3">Teléfono</th>
                                <th className="px-4 py-3">Email</th>
                                <th className="px-4 py-3 text-center">Ventas</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {customers.data.map((c) => (
                                <tr key={c.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{c.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{docLabel(c)}</td>
                                    <td className="px-4 py-3 text-gray-500">{c.phone ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{c.email ?? '—'}</td>
                                    <td className="px-4 py-3 text-center text-gray-500">{c.sales_count}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {c.is_active ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/customers/${c.id}`}><Eye className="h-4 w-4" /></Link>
                                            </Button>
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/customers/${c.id}/edit`}><Pencil className="h-4 w-4" /></Link>
                                            </Button>
                                            <Button variant="ghost" size="sm" onClick={() => destroy(c.id)}>
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {customers.data.length === 0 && (
                                <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">Sin clientes.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
