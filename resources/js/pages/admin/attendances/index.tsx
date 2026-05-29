import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { router, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';

interface Attendance {
    id: number; status: string; check_in: string | null; check_out: string | null;
    worked_hours: string; overtime_hours: string; notes: string | null;
}
interface Row { id: number; name: string; position: string; attendance: Attendance | null }

const statuses = [
    { v: 'present', l: 'Presente' }, { v: 'late', l: 'Atraso' }, { v: 'absent', l: 'Falta' },
    { v: 'leave', l: 'Permiso' }, { v: 'holiday', l: 'Feriado' }, { v: 'rest', l: 'Descanso' },
];

function AttendanceRow({ row, date }: { row: Row; date: string }) {
    const a = row.attendance;
    const { data, setData, post, processing } = useForm<any>({
        employee_id: row.id,
        date,
        status: a?.status ?? 'present',
        check_in: a?.check_in ?? '',
        check_out: a?.check_out ?? '',
        worked_hours: a?.worked_hours ?? '8',
        overtime_hours: a?.overtime_hours ?? '0',
        notes: a?.notes ?? '',
    });

    return (
        <tr className="hover:bg-gray-50">
            <td className="px-3 py-2">
                <div className="font-medium">{row.name}</div>
                <div className="text-xs text-gray-400">{row.position}</div>
            </td>
            <td className="px-3 py-2">
                <select className="rounded-md border px-2 py-1.5 text-sm" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                    {statuses.map((s) => <option key={s.v} value={s.v}>{s.l}</option>)}
                </select>
            </td>
            <td className="px-3 py-2"><Input className="w-28" type="time" value={data.check_in} onChange={(e) => setData('check_in', e.target.value)} /></td>
            <td className="px-3 py-2"><Input className="w-28" type="time" value={data.check_out} onChange={(e) => setData('check_out', e.target.value)} /></td>
            <td className="px-3 py-2"><Input className="w-20" type="number" step="0.5" value={data.worked_hours} onChange={(e) => setData('worked_hours', e.target.value)} /></td>
            <td className="px-3 py-2"><Input className="w-20" type="number" step="0.5" value={data.overtime_hours} onChange={(e) => setData('overtime_hours', e.target.value)} /></td>
            <td className="px-3 py-2 text-right">
                <Button size="sm" variant={a ? 'outline' : 'default'} disabled={processing}
                    onClick={() => post('/admin/attendances', { preserveScroll: true, preserveState: true })}>
                    <Save className="mr-1 h-4 w-4" /> {a ? 'Actualizar' : 'Guardar'}
                </Button>
            </td>
        </tr>
    );
}

export default function AttendancesIndex({ date, employees }: { date: string; employees: Row[] }) {
    const changeDate = (d: string) => router.get('/admin/attendances', { date: d }, { preserveState: true, replace: true });

    return (
        <AppLayout breadcrumbs={[{ title: 'Asistencia', href: '/admin/attendances' }]}>
            <FlashMessage />
            <div className="p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Tiempo y asistencia</h1>
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-gray-500">Fecha:</span>
                        <Input type="date" value={date} onChange={(e) => changeDate(e.target.value)} className="w-44" />
                    </div>
                </div>
                <div className="overflow-x-auto rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-3 py-3">Empleado</th>
                                <th className="px-3 py-3">Estado</th>
                                <th className="px-3 py-3">Entrada</th>
                                <th className="px-3 py-3">Salida</th>
                                <th className="px-3 py-3">Horas</th>
                                <th className="px-3 py-3">H. extra</th>
                                <th className="px-3 py-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {employees.map((row) => <AttendanceRow key={row.id} row={row} date={date} />)}
                            {employees.length === 0 && (
                                <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">Sin empleados activos.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
