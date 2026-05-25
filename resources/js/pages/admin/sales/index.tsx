import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus } from 'lucide-react';

interface Sale {
    id: number; folio: string; total: string; status: string; payment_method: string; created_at: string;
    user: { name: string };
    cash_shift: { cash_register: { name: string; store: { name: string } } };
}

const statusColors: Record<string, string> = { completed: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700', refunded: 'bg-orange-100 text-orange-700' };
const statusLabels: Record<string, string> = { completed: 'Completada', cancelled: 'Cancelada', refunded: 'Devuelta' };
const paymentLabels: Record<string, string> = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia', mixed: 'Mixto' };

export default function SalesIndex({ sales }: { sales: PaginatedData<Sale> }) {
    const { auth } = usePage<{ auth: { roles: string[] } }>().props;
    const canEdit = auth.roles.includes('admin') || auth.roles.includes('operador');

    return (
        <AppLayout breadcrumbs={[{ title: 'Ventas', href: '/admin/sales' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Ventas</h1>
                    <Button asChild><Link href="/admin/sales/create"><Plus className="mr-2 h-4 w-4" /> Nueva Venta</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Folio</th>
                                <th className="px-4 py-3">Fecha</th>
                                <th className="px-4 py-3">Caja</th>
                                <th className="px-4 py-3">Vendedor</th>
                                <th className="px-4 py-3">Pago</th>
                                <th className="px-4 py-3">Total</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {sales.data.map((s) => (
                                <tr key={s.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono font-medium">{s.folio}</td>
                                    <td className="px-4 py-3 text-gray-500">{new Date(s.created_at).toLocaleString('es-MX')}</td>
                                    <td className="px-4 py-3 text-gray-500">{s.cash_shift.cash_register.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{s.user.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{paymentLabels[s.payment_method]}</td>
                                    <td className="px-4 py-3 font-medium">${parseFloat(s.total).toFixed(2)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[s.status]}`}>{statusLabels[s.status]}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right flex justify-end gap-1">
                                        <Button variant="ghost" size="sm" asChild><Link href={`/admin/sales/${s.id}`}><Eye className="h-4 w-4" /></Link></Button>
                                        {canEdit && (
                                            <Button variant="ghost" size="sm" asChild><Link href={`/admin/sales/${s.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {sales.data.length === 0 && <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Sin ventas registradas.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
