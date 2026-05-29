import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { Check, Trash2, X } from 'lucide-react';

interface Leave {
    id: number; type: string; start_date: string; end_date: string; days: number; reason: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    employee: { id: number; first_name: string; last_name: string } | null;
    reviewer: { name: string } | null;
}
interface Employee { id: number; first_name: string; last_name: string }

const typeLabels: Record<string, string> = {
    vacation: 'Vacación', sick: 'Baja médica', personal: 'Personal', maternity: 'Maternidad',
    paternity: 'Paternidad', bereavement: 'Duelo', unpaid: 'Sin goce',
};
const statusBadge: Record<Leave['status'], { label: string; cls: string }> = {
    pending: { label: 'Pendiente', cls: 'bg-yellow-100 text-yellow-700' },
    approved: { label: 'Aprobada', cls: 'bg-green-100 text-green-700' },
    rejected: { label: 'Rechazada', cls: 'bg-red-100 text-red-700' },
    cancelled: { label: 'Cancelada', cls: 'bg-gray-100 text-gray-500' },
};
const d = (v: string) => v.substring(0, 10);

interface Props {
    leaves: PaginatedData<Leave>;
    employees: Employee[];
    filters: { status?: string };
}

export default function LeaveRequestsIndex({ leaves, employees, filters }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<any>({
        employee_id: '', type: 'vacation', start_date: '', end_date: '', reason: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/leave-requests', { onSuccess: () => reset() });
    };

    const approve = (id: number) => router.patch(`/admin/leave-requests/${id}/approve`, {}, { preserveScroll: true });
    const reject = (id: number) => router.patch(`/admin/leave-requests/${id}/reject`, {}, { preserveScroll: true });
    const remove = (id: number) => { if (confirm('¿Eliminar solicitud?')) router.delete(`/admin/leave-requests/${id}`); };

    return (
        <AppLayout breadcrumbs={[{ title: 'Ausencias', href: '/admin/leave-requests' }]}>
            <FlashMessage />
            <div className="p-6 space-y-6">
                <h1 className="text-2xl font-bold">Ausencias, permisos y vacaciones</h1>

                <form onSubmit={submit} className="rounded-lg border bg-white p-4 shadow-sm">
                    <h2 className="mb-3 font-semibold text-gray-700">Nueva solicitud</h2>
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-5">
                        <div>
                            <Label>Empleado *</Label>
                            <select className="w-full rounded-md border px-2 py-2 text-sm" value={data.employee_id} onChange={(e) => setData('employee_id', e.target.value)}>
                                <option value="">—</option>
                                {employees.map((m) => <option key={m.id} value={m.id}>{m.first_name} {m.last_name}</option>)}
                            </select>
                            {errors.employee_id && <p className="mt-1 text-xs text-red-500">{errors.employee_id}</p>}
                        </div>
                        <div>
                            <Label>Tipo</Label>
                            <select className="w-full rounded-md border px-2 py-2 text-sm" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                {Object.entries(typeLabels).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                        </div>
                        <div><Label>Desde *</Label><Input type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} /></div>
                        <div><Label>Hasta *</Label><Input type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} /></div>
                        <div className="flex items-end"><Button type="submit" disabled={processing} className="w-full">Registrar</Button></div>
                    </div>
                </form>

                <div className="flex gap-2">
                    <select className="rounded-md border px-3 py-2 text-sm" value={filters.status ?? ''}
                        onChange={(e) => router.get('/admin/leave-requests', { status: e.target.value }, { preserveState: true, replace: true })}>
                        <option value="">Todas</option>
                        <option value="pending">Pendientes</option>
                        <option value="approved">Aprobadas</option>
                        <option value="rejected">Rechazadas</option>
                    </select>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Empleado</th>
                                <th className="px-4 py-3">Tipo</th>
                                <th className="px-4 py-3">Periodo</th>
                                <th className="px-4 py-3 text-center">Días</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3">Revisor</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {leaves.data.map((l) => (
                                <tr key={l.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{l.employee ? `${l.employee.first_name} ${l.employee.last_name}` : '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{typeLabels[l.type]}</td>
                                    <td className="px-4 py-3 text-gray-500">{d(l.start_date)} → {d(l.end_date)}</td>
                                    <td className="px-4 py-3 text-center">{l.days}</td>
                                    <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusBadge[l.status].cls}`}>{statusBadge[l.status].label}</span></td>
                                    <td className="px-4 py-3 text-gray-500">{l.reviewer?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            {l.status === 'pending' && (
                                                <>
                                                    <Button variant="ghost" size="sm" onClick={() => approve(l.id)}><Check className="h-4 w-4 text-green-600" /></Button>
                                                    <Button variant="ghost" size="sm" onClick={() => reject(l.id)}><X className="h-4 w-4 text-red-500" /></Button>
                                                </>
                                            )}
                                            <Button variant="ghost" size="sm" onClick={() => remove(l.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {leaves.data.length === 0 && (
                                <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">Sin solicitudes.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
