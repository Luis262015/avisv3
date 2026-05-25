import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';

interface Receivable {
    id: number; customer_name: string; description: string;
    amount: string; amount_paid: string; balance: string;
    due_date: string; status: string;
    user: { name: string };
}

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700',
    partial: 'bg-blue-100 text-blue-700',
    paid: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = {
    pending: 'Pendiente', partial: 'Parcial', paid: 'Pagada', cancelled: 'Cancelada',
};

export default function ReceivablesIndex({ receivables }: { receivables: PaginatedData<Receivable> }) {
    const fmt = (v: string) => `$${parseFloat(v).toFixed(2)}`;
    const today = new Date().toISOString().split('T')[0];

    return (
        <AppLayout breadcrumbs={[{ title: 'Cuentas por cobrar', href: '/admin/receivables' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Cuentas por Cobrar</h1>
                    <Button asChild><Link href="/admin/receivables/create"><Plus className="mr-2 h-4 w-4" /> Nueva Cuenta</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Cliente</th>
                                <th className="px-4 py-3">Descripción</th>
                                <th className="px-4 py-3">Vencimiento</th>
                                <th className="px-4 py-3 text-right">Total</th>
                                <th className="px-4 py-3 text-right">Pagado</th>
                                <th className="px-4 py-3 text-right">Saldo</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {receivables.data.map((r) => {
                                const overdue = r.status !== 'paid' && r.status !== 'cancelled' && r.due_date < today;
                                return (
                                    <tr key={r.id} className={`hover:bg-gray-50 ${overdue ? 'bg-red-50' : ''}`}>
                                        <td className="px-4 py-3 font-medium">{r.customer_name}</td>
                                        <td className="px-4 py-3 text-gray-500">{r.description}</td>
                                        <td className={`px-4 py-3 ${overdue ? 'font-semibold text-red-600' : 'text-gray-500'}`}>
                                            {r.due_date}{overdue && ' ⚠'}
                                        </td>
                                        <td className="px-4 py-3 text-right">{fmt(r.amount)}</td>
                                        <td className="px-4 py-3 text-right text-green-600">{fmt(r.amount_paid)}</td>
                                        <td className="px-4 py-3 text-right font-semibold">{fmt(r.balance)}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[r.status]}`}>{statusLabels[r.status]}</span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/receivables/${r.id}`}><Eye className="h-4 w-4" /></Link>
                                            </Button>
                                        </td>
                                    </tr>
                                );
                            })}
                            {receivables.data.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Sin cuentas por cobrar.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
