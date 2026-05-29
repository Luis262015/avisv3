import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';

interface SaleReturn {
    id: number; folio: string; date: string; refund_amount: string; refund_method: string; status: string;
    sale: { id: number; folio: string } | null;
    customer: { name: string } | null;
    user: { name: string };
}

const statusColors: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-600', approved: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = {
    pending: 'Pendiente', approved: 'Aprobada', completed: 'Completada', rejected: 'Rechazada',
};
const methodLabels: Record<string, string> = {
    cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia', store_credit: 'Crédito en tienda',
};

export default function ReturnsIndex({ returns }: { returns: PaginatedData<SaleReturn> }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Devoluciones', href: '/admin/returns' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Devoluciones</h1>
                    <Button asChild><Link href="/admin/returns/create"><Plus className="mr-2 h-4 w-4" /> Nueva Devolución</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Folio</th>
                                <th className="px-4 py-3">Fecha</th>
                                <th className="px-4 py-3">Venta</th>
                                <th className="px-4 py-3">Cliente</th>
                                <th className="px-4 py-3 text-right">Reembolso</th>
                                <th className="px-4 py-3">Método</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {returns.data.map((r) => (
                                <tr key={r.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono font-medium">{r.folio}</td>
                                    <td className="px-4 py-3 text-gray-500">{r.date}</td>
                                    <td className="px-4 py-3">
                                        {r.sale ? <Link href={`/admin/sales/${r.sale.id}`} className="font-mono text-blue-600 hover:underline">{r.sale.folio}</Link> : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{r.customer?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-right font-medium">${parseFloat(r.refund_amount).toFixed(2)}</td>
                                    <td className="px-4 py-3 text-gray-500">{methodLabels[r.refund_method]}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[r.status]}`}>{statusLabels[r.status]}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="ghost" size="sm" asChild><Link href={`/admin/returns/${r.id}`}><Eye className="h-4 w-4" /></Link></Button>
                                    </td>
                                </tr>
                            ))}
                            {returns.data.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Sin devoluciones.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
