import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

interface Expense {
    id: number; category: string; description: string; amount: string;
    payment_method: string; reference: string | null; date: string;
    user: { name: string };
    cash_shift: { cash_register: { name: string } } | null;
}

const paymentLabels: Record<string, string> = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia' };

export default function ExpensesIndex({ expenses }: { expenses: PaginatedData<Expense> }) {
    const { auth } = usePage<{ auth: { roles: string[] } }>().props;
    const canEdit = auth.roles.includes('admin') || auth.roles.includes('operador');
    const total = expenses.data.reduce((s, e) => s + parseFloat(e.amount), 0);

    return (
        <AppLayout breadcrumbs={[{ title: 'Gastos', href: '/admin/expenses' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Gastos</h1>
                        <p className="text-sm text-gray-500">Total en esta página: <span className="font-semibold text-red-600">${total.toFixed(2)}</span></p>
                    </div>
                    <Button asChild><Link href="/admin/expenses/create"><Plus className="mr-2 h-4 w-4" /> Registrar Gasto</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Fecha</th>
                                <th className="px-4 py-3">Categoría</th>
                                <th className="px-4 py-3">Descripción</th>
                                <th className="px-4 py-3">Caja</th>
                                <th className="px-4 py-3">Registrado por</th>
                                <th className="px-4 py-3">Pago</th>
                                <th className="px-4 py-3 text-right">Monto</th>
                                {canEdit && <th className="px-4 py-3 text-right">Acciones</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {expenses.data.map((e) => (
                                <tr key={e.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 text-gray-500">{e.date}</td>
                                    <td className="px-4 py-3"><span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{e.category}</span></td>
                                    <td className="px-4 py-3 font-medium">{e.description}</td>
                                    <td className="px-4 py-3 text-gray-500">{e.cash_shift?.cash_register.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{e.user.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{paymentLabels[e.payment_method]}</td>
                                    <td className="px-4 py-3 text-right font-semibold text-red-600">${parseFloat(e.amount).toFixed(2)}</td>
                                    {canEdit && (
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/expenses/${e.id}/edit`}><Pencil className="h-4 w-4" /></Link>
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {expenses.data.length === 0 && (
                                <tr><td colSpan={canEdit ? 8 : 7} className="px-4 py-8 text-center text-gray-400">Sin gastos registrados.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
