import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

interface Withdrawal {
    id: number; amount: string; reason: string; date: string;
    authorized_by: string | null;
    user: { name: string };
    cash_shift: { cash_register: { name: string } } | null;
}

export default function WithdrawalsIndex({ withdrawals }: { withdrawals: PaginatedData<Withdrawal> }) {
    const { auth } = usePage<{ auth: { roles: string[] } }>().props;
    const canEdit = auth.roles.includes('admin') || auth.roles.includes('operador');
    const total = withdrawals.data.reduce((s, w) => s + parseFloat(w.amount), 0);

    return (
        <AppLayout breadcrumbs={[{ title: 'Retiros', href: '/admin/withdrawals' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Retiros de Caja</h1>
                        <p className="text-sm text-gray-500">Total en esta página: <span className="font-semibold text-orange-600">${total.toFixed(2)}</span></p>
                    </div>
                    <Button asChild><Link href="/admin/withdrawals/create"><Plus className="mr-2 h-4 w-4" /> Registrar Retiro</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Fecha</th>
                                <th className="px-4 py-3">Motivo</th>
                                <th className="px-4 py-3">Caja</th>
                                <th className="px-4 py-3">Registrado por</th>
                                <th className="px-4 py-3">Autorizado por</th>
                                <th className="px-4 py-3 text-right">Monto</th>
                                {canEdit && <th className="px-4 py-3 text-right">Acciones</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {withdrawals.data.map((w) => (
                                <tr key={w.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 text-gray-500">{w.date}</td>
                                    <td className="px-4 py-3 font-medium">{w.reason}</td>
                                    <td className="px-4 py-3 text-gray-500">{w.cash_shift?.cash_register.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{w.user.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{w.authorized_by ?? '—'}</td>
                                    <td className="px-4 py-3 text-right font-semibold text-orange-600">${parseFloat(w.amount).toFixed(2)}</td>
                                    {canEdit && (
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/withdrawals/${w.id}/edit`}><Pencil className="h-4 w-4" /></Link>
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {withdrawals.data.length === 0 && (
                                <tr><td colSpan={canEdit ? 7 : 6} className="px-4 py-8 text-center text-gray-400">Sin retiros registrados.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
