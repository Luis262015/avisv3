import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';

interface Payable {
    id: number; description: string; amount: string; amount_paid: string; balance: string;
    due_date: string; status: string;
    supplier: { name: string } | null;
    user: { name: string };
}

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700',
    partial: 'bg-blue-100 text-blue-700',
    paid: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = { pending: 'Pendiente', partial: 'Parcial', paid: 'Pagada', cancelled: 'Cancelada' };

export default function PayablesIndex({ payables }: { payables: PaginatedData<Payable> }) {
    const fmt = (v: string) => `$${parseFloat(v).toFixed(2)}`;
    const today = new Date().toISOString().split('T')[0];

    return (
        <AppLayout breadcrumbs={[{ title: 'Cuentas por pagar', href: '/admin/payables' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Cuentas por Pagar</h1>
                    <Button asChild><Link href="/admin/payables/create"><Plus className="mr-2 h-4 w-4" /> Nueva Cuenta</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Proveedor</th>
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
                            {payables.data.map((p) => {
                                const overdue = p.status !== 'paid' && p.status !== 'cancelled' && p.due_date < today;
                                return (
                                    <tr key={p.id} className={`hover:bg-gray-50 ${overdue ? 'bg-red-50' : ''}`}>
                                        <td className="px-4 py-3 font-medium">{p.supplier?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-500">{p.description}</td>
                                        <td className={`px-4 py-3 ${overdue ? 'font-semibold text-red-600' : 'text-gray-500'}`}>
                                            {p.due_date}{overdue && ' ⚠'}
                                        </td>
                                        <td className="px-4 py-3 text-right">{fmt(p.amount)}</td>
                                        <td className="px-4 py-3 text-right text-green-600">{fmt(p.amount_paid)}</td>
                                        <td className="px-4 py-3 text-right font-semibold">{fmt(p.balance)}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[p.status]}`}>{statusLabels[p.status]}</span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/payables/${p.id}`}><Eye className="h-4 w-4" /></Link>
                                            </Button>
                                        </td>
                                    </tr>
                                );
                            })}
                            {payables.data.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Sin cuentas por pagar.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
