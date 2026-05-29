import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { router, useForm } from '@inertiajs/react';
import { Check, CheckCircle2, Pencil, Wallet, X } from 'lucide-react';
import { useState } from 'react';

const fmt = (v: any) => `Bs ${Number(v ?? 0).toFixed(2)}`;

interface Item {
    id: number;
    employee: { id: number; first_name: string; last_name: string; position: string; employee_code: string } | null;
    base_salary: string; worked_days: number; antiquity_bonus: string; overtime_amount: string; other_earnings: string;
    gross_salary: string; afp_deduction: string; rc_iva_deduction: string; loans_deduction: string; other_deductions: string;
    total_deductions: string; net_salary: string; notes: string | null;
}

interface Payroll {
    id: number; label: string; status: 'draft' | 'approved' | 'paid' | 'cancelled'; pay_date: string | null;
    total_gross: string; total_deductions: string; total_net: string; notes: string | null;
    user: { name: string } | null; items: Item[];
}

const statusBadge: Record<Payroll['status'], { label: string; cls: string }> = {
    draft: { label: 'Borrador', cls: 'bg-gray-100 text-gray-600' },
    approved: { label: 'Aprobada', cls: 'bg-blue-100 text-blue-700' },
    paid: { label: 'Pagada', cls: 'bg-green-100 text-green-700' },
    cancelled: { label: 'Anulada', cls: 'bg-red-100 text-red-700' },
};

export default function PayrollShow({ payroll }: { payroll: Payroll }) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const isDraft = payroll.status === 'draft';

    const edit = useForm<any>({
        worked_days: 30, antiquity_bonus: 0, overtime_amount: 0, other_earnings: 0,
        loans_deduction: 0, other_deductions: 0, notes: '',
    });

    const startEdit = (i: Item) => {
        setEditingId(i.id);
        edit.setData({
            worked_days: i.worked_days, antiquity_bonus: i.antiquity_bonus, overtime_amount: i.overtime_amount,
            other_earnings: i.other_earnings, loans_deduction: i.loans_deduction, other_deductions: i.other_deductions,
            notes: i.notes ?? '',
        });
    };

    const saveEdit = (itemId: number) => {
        edit.patch(`/admin/payrolls/${payroll.id}/items/${itemId}`, { preserveScroll: true, onSuccess: () => setEditingId(null) });
    };

    const approve = () => router.patch(`/admin/payrolls/${payroll.id}/approve`, {}, { preserveScroll: true });
    const pay = () => { if (confirm('¿Marcar planilla como pagada?')) router.patch(`/admin/payrolls/${payroll.id}/pay`, {}, { preserveScroll: true }); };

    return (
        <AppLayout breadcrumbs={[{ title: 'Nómina', href: '/admin/payrolls' }, { title: payroll.label, href: '' }]}>
            <FlashMessage />
            <div className="p-6 space-y-6">
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold">Planilla {payroll.label}</h1>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusBadge[payroll.status].cls}`}>{statusBadge[payroll.status].label}</span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">Generada por {payroll.user?.name ?? '—'}{payroll.pay_date ? ` · Pago: ${payroll.pay_date.substring(0, 10)}` : ''}</p>
                    </div>
                    <div className="flex gap-2">
                        {isDraft && <Button onClick={approve}><CheckCircle2 className="mr-2 h-4 w-4" /> Aprobar</Button>}
                        {payroll.status === 'approved' && <Button onClick={pay}><Wallet className="mr-2 h-4 w-4" /> Marcar pagada</Button>}
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-4">
                    {[
                        { label: 'Total ganado', value: fmt(payroll.total_gross), color: 'text-gray-700' },
                        { label: 'Deducciones', value: fmt(payroll.total_deductions), color: 'text-red-600' },
                        { label: 'Líquido pagable', value: fmt(payroll.total_net), color: 'text-green-700' },
                    ].map((s) => (
                        <div key={s.label} className="rounded-lg border bg-white p-4 shadow-sm">
                            <p className="text-xs uppercase text-gray-500">{s.label}</p>
                            <p className={`text-xl font-bold ${s.color}`}>{s.value}</p>
                        </div>
                    ))}
                </div>

                <div className="overflow-x-auto rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-3 py-3">Empleado</th>
                                <th className="px-3 py-3 text-right">Haber</th>
                                <th className="px-3 py-3 text-center">Días</th>
                                <th className="px-3 py-3 text-right">Antigüedad</th>
                                <th className="px-3 py-3 text-right">H. extra</th>
                                <th className="px-3 py-3 text-right">Otros</th>
                                <th className="px-3 py-3 text-right">Ganado</th>
                                <th className="px-3 py-3 text-right">AFP</th>
                                <th className="px-3 py-3 text-right">RC-IVA</th>
                                <th className="px-3 py-3 text-right">Préstamos</th>
                                <th className="px-3 py-3 text-right">Líquido</th>
                                {isDraft && <th className="px-3 py-3" />}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {payroll.items.map((i) => editingId === i.id ? (
                                <tr key={i.id} className="bg-blue-50/40">
                                    <td className="px-3 py-2 font-medium">{i.employee?.first_name} {i.employee?.last_name}</td>
                                    <td className="px-3 py-2 text-right text-gray-500">{fmt(i.base_salary)}</td>
                                    <td className="px-3 py-2"><Input className="w-16" type="number" value={edit.data.worked_days} onChange={(e) => edit.setData('worked_days', Number(e.target.value))} /></td>
                                    <td className="px-3 py-2"><Input className="w-24" type="number" step="0.01" value={edit.data.antiquity_bonus} onChange={(e) => edit.setData('antiquity_bonus', e.target.value)} /></td>
                                    <td className="px-3 py-2"><Input className="w-24" type="number" step="0.01" value={edit.data.overtime_amount} onChange={(e) => edit.setData('overtime_amount', e.target.value)} /></td>
                                    <td className="px-3 py-2"><Input className="w-24" type="number" step="0.01" value={edit.data.other_earnings} onChange={(e) => edit.setData('other_earnings', e.target.value)} /></td>
                                    <td className="px-3 py-2 text-right">—</td>
                                    <td className="px-3 py-2 text-right">—</td>
                                    <td className="px-3 py-2 text-right">—</td>
                                    <td className="px-3 py-2"><Input className="w-24" type="number" step="0.01" value={edit.data.loans_deduction} onChange={(e) => edit.setData('loans_deduction', e.target.value)} /></td>
                                    <td className="px-3 py-2 text-right">—</td>
                                    <td className="px-3 py-2">
                                        <div className="flex gap-1">
                                            <Button variant="ghost" size="sm" onClick={() => saveEdit(i.id)}><Check className="h-4 w-4 text-green-600" /></Button>
                                            <Button variant="ghost" size="sm" onClick={() => setEditingId(null)}><X className="h-4 w-4" /></Button>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                <tr key={i.id} className="hover:bg-gray-50">
                                    <td className="px-3 py-2 font-medium">
                                        {i.employee?.first_name} {i.employee?.last_name}
                                        <span className="ml-1 font-mono text-xs text-gray-400">{i.employee?.employee_code}</span>
                                    </td>
                                    <td className="px-3 py-2 text-right text-gray-500">{fmt(i.base_salary)}</td>
                                    <td className="px-3 py-2 text-center text-gray-500">{i.worked_days}</td>
                                    <td className="px-3 py-2 text-right text-gray-500">{fmt(i.antiquity_bonus)}</td>
                                    <td className="px-3 py-2 text-right text-gray-500">{fmt(i.overtime_amount)}</td>
                                    <td className="px-3 py-2 text-right text-gray-500">{fmt(i.other_earnings)}</td>
                                    <td className="px-3 py-2 text-right font-medium">{fmt(i.gross_salary)}</td>
                                    <td className="px-3 py-2 text-right text-red-600">{fmt(i.afp_deduction)}</td>
                                    <td className="px-3 py-2 text-right text-red-600">{fmt(i.rc_iva_deduction)}</td>
                                    <td className="px-3 py-2 text-right text-red-600">{fmt(i.loans_deduction)}</td>
                                    <td className="px-3 py-2 text-right font-semibold text-green-700">{fmt(i.net_salary)}</td>
                                    {isDraft && (
                                        <td className="px-3 py-2">
                                            <Button variant="ghost" size="sm" onClick={() => startEdit(i)}><Pencil className="h-4 w-4" /></Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {payroll.items.length === 0 && (
                                <tr><td colSpan={isDraft ? 12 : 11} className="px-4 py-8 text-center text-gray-400">Sin empleados en la planilla.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
