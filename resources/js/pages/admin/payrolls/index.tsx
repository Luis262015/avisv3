import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Eye, Plus, Trash2 } from 'lucide-react';

interface Payroll {
    id: number;
    label: string;
    status: 'draft' | 'approved' | 'paid' | 'cancelled';
    pay_date: string | null;
    total_gross: string;
    total_deductions: string;
    total_net: string;
    items_count: number;
    user: { name: string } | null;
}

const statusBadge: Record<Payroll['status'], { label: string; cls: string }> = {
    draft: { label: 'Borrador', cls: 'bg-gray-100 text-gray-600' },
    approved: { label: 'Aprobada', cls: 'bg-blue-100 text-blue-700' },
    paid: { label: 'Pagada', cls: 'bg-green-100 text-green-700' },
    cancelled: { label: 'Anulada', cls: 'bg-red-100 text-red-700' },
};

const fmt = (v: any) => `Bs ${Number(v ?? 0).toFixed(2)}`;

export default function PayrollsIndex({ payrolls }: { payrolls: PaginatedData<Payroll> }) {
    const remove = (id: number) => {
        if (confirm('¿Eliminar planilla?')) router.delete(`/admin/payrolls/${id}`);
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Nómina', href: '/admin/payrolls' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Planillas de sueldos</h1>
                    <Button asChild>
                        <Link href="/admin/payrolls/create"><Plus className="mr-2 h-4 w-4" /> Generar planilla</Link>
                    </Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Periodo</th>
                                <th className="px-4 py-3 text-center">Empleados</th>
                                <th className="px-4 py-3 text-right">Total ganado</th>
                                <th className="px-4 py-3 text-right">Deducciones</th>
                                <th className="px-4 py-3 text-right">Líquido</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {payrolls.data.map((p) => (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{p.label}</td>
                                    <td className="px-4 py-3 text-center text-gray-500">{p.items_count}</td>
                                    <td className="px-4 py-3 text-right text-gray-600">{fmt(p.total_gross)}</td>
                                    <td className="px-4 py-3 text-right text-red-600">{fmt(p.total_deductions)}</td>
                                    <td className="px-4 py-3 text-right font-semibold text-green-700">{fmt(p.total_net)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusBadge[p.status].cls}`}>{statusBadge[p.status].label}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" asChild><Link href={`/admin/payrolls/${p.id}`}><Eye className="h-4 w-4" /></Link></Button>
                                            {p.status !== 'paid' && (
                                                <Button variant="ghost" size="sm" onClick={() => remove(p.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {payrolls.data.length === 0 && (
                                <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">Sin planillas.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
