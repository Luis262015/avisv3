import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

interface Income {
    id: number; category: string; description: string; amount: string;
    payment_method: string; reference: string | null; date: string;
    user: { name: string };
    cash_shift: { cash_register: { name: string } } | null;
}

const paymentLabels: Record<string, string> = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia' };

export default function IncomesIndex({ incomes }: { incomes: PaginatedData<Income> }) {
    const { auth } = usePage<{ auth: { roles: string[] } }>().props;
    const canEdit = auth.roles.includes('admin') || auth.roles.includes('operador');
    const total = incomes.data.reduce((s, i) => s + parseFloat(i.amount), 0);

    return (
        <AppLayout breadcrumbs={[{ title: 'Ingresos', href: '/admin/incomes' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Ingresos</h1>
                        <p className="text-sm text-gray-500">Total en esta página: <span className="font-semibold text-green-600">${total.toFixed(2)}</span></p>
                    </div>
                    <Button asChild><Link href="/admin/incomes/create"><Plus className="mr-2 h-4 w-4" /> Registrar Ingreso</Link></Button>
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
                            {incomes.data.map((i) => (
                                <tr key={i.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 text-gray-500">{i.date}</td>
                                    <td className="px-4 py-3"><span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">{i.category}</span></td>
                                    <td className="px-4 py-3 font-medium">{i.description}</td>
                                    <td className="px-4 py-3 text-gray-500">{i.cash_shift?.cash_register.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{i.user.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{paymentLabels[i.payment_method]}</td>
                                    <td className="px-4 py-3 text-right font-semibold text-green-600">${parseFloat(i.amount).toFixed(2)}</td>
                                    {canEdit && (
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/incomes/${i.id}/edit`}><Pencil className="h-4 w-4" /></Link>
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {incomes.data.length === 0 && (
                                <tr><td colSpan={canEdit ? 8 : 7} className="px-4 py-8 text-center text-gray-400">Sin ingresos registrados.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
