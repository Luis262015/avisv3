import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus } from 'lucide-react';

interface Purchase {
    id: number; folio: string; date: string; total: string;
    status: string; payment_status: string;
    invoice_number: string | null;
    supplier: { name: string } | null;
    user: { name: string };
}

const statusColors: Record<string, string> = {
    pending:   'bg-yellow-100 text-yellow-700',
    partial:   'bg-blue-100 text-blue-700',
    received:  'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = {
    pending: 'Pendiente', partial: 'Parcial', received: 'Recibida', cancelled: 'Cancelada',
};

const paymentColors: Record<string, string> = {
    unpaid:  'bg-red-100 text-red-700',
    partial: 'bg-yellow-100 text-yellow-700',
    paid:    'bg-green-100 text-green-700',
};
const paymentLabels: Record<string, string> = {
    unpaid: 'No pagada', partial: 'Parcial', paid: 'Pagada',
};

export default function PurchasesIndex({ purchases }: { purchases: PaginatedData<Purchase> }) {
    const { auth } = usePage<{ auth: { roles: string[] } }>().props;
    const canEdit = auth.roles.includes('admin') || auth.roles.includes('operador');

    return (
        <AppLayout breadcrumbs={[{ title: 'Compras', href: '/admin/purchases' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Compras</h1>
                    <Button asChild>
                        <Link href="/admin/purchases/create"><Plus className="mr-2 h-4 w-4" /> Nueva Compra</Link>
                    </Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Folio</th>
                                <th className="px-4 py-3">Factura</th>
                                <th className="px-4 py-3">Fecha</th>
                                <th className="px-4 py-3">Proveedor</th>
                                <th className="px-4 py-3 text-right">Total</th>
                                <th className="px-4 py-3">Recepción</th>
                                <th className="px-4 py-3">Pago</th>
                                <th className="px-4 py-3">Registrada por</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {purchases.data.map((p) => (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono font-medium">{p.folio}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-400">{p.invoice_number ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{p.date}</td>
                                    <td className="px-4 py-3 text-gray-600">{p.supplier?.name ?? 'Sin proveedor'}</td>
                                    <td className="px-4 py-3 text-right font-medium">${parseFloat(p.total).toFixed(2)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[p.status]}`}>
                                            {statusLabels[p.status]}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${paymentColors[p.payment_status]}`}>
                                            {paymentLabels[p.payment_status]}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">{p.user.name}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/purchases/${p.id}`}><Eye className="h-4 w-4" /></Link>
                                            </Button>
                                            {canEdit && (
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={`/admin/purchases/${p.id}/edit`}><Pencil className="h-4 w-4" /></Link>
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {purchases.data.length === 0 && (
                                <tr><td colSpan={9} className="px-4 py-8 text-center text-gray-400">Sin compras registradas.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
