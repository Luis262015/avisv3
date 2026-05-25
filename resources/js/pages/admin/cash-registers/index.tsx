import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface Register { id: number; name: string; store: { name: string }; is_active: boolean }

export default function CashRegistersIndex({ registers }: { registers: PaginatedData<Register> }) {
    const destroy = (id: number) => { if (confirm('¿Eliminar caja?')) router.delete(`/admin/cash-registers/${id}`); };
    return (
        <AppLayout breadcrumbs={[{ title: 'Cajas', href: '/admin/cash-registers' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Cajas</h1>
                    <Button asChild><Link href="/admin/cash-registers/create"><Plus className="mr-2 h-4 w-4" /> Nueva Caja</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">ID</th>
                                <th className="px-4 py-3">Nombre</th>
                                <th className="px-4 py-3">Tienda</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {registers.data.map((r) => (
                                <tr key={r.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono text-xs text-gray-400">#{r.id}</td>
                                    <td className="px-4 py-3 font-medium">{r.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{r.store.name}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${r.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {r.is_active ? 'Activa' : 'Inactiva'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="ghost" size="sm" asChild><Link href={`/admin/cash-registers/${r.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                                        <Button variant="ghost" size="sm" onClick={() => destroy(r.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                    </td>
                                </tr>
                            ))}
                            {registers.data.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Sin cajas registradas.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
