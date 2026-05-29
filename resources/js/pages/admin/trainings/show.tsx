import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';
import { Pencil, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';

interface Participant {
    id: number; first_name: string; last_name: string; position: string;
    pivot: { status: string; score: string | null; completed_at: string | null };
}
interface Training {
    id: number; title: string; description: string | null; provider: string | null; modality: string; status: string;
    start_date: string | null; end_date: string | null; hours: string; cost: string; notes: string | null;
    employees: Participant[];
}
interface Employee { id: number; first_name: string; last_name: string }

const modalityLabels: Record<string, string> = { internal: 'Interna', external: 'Externa', online: 'En línea' };
const pStatus = [
    { v: 'enrolled', l: 'Inscrito' }, { v: 'completed', l: 'Completado' }, { v: 'failed', l: 'Reprobado' }, { v: 'dropped', l: 'Abandonó' },
];
const d = (v: string | null) => (v ? v.substring(0, 10) : '—');

export default function TrainingShow({ training, employees }: { training: Training; employees: Employee[] }) {
    const [newId, setNewId] = useState('');

    const enrolled = new Set(training.employees.map((e) => e.id));
    const available = employees.filter((e) => !enrolled.has(e.id));

    const addParticipant = () => {
        if (!newId) return;
        router.post(`/admin/trainings/${training.id}/participants`, { employee_id: newId }, { preserveScroll: true, onSuccess: () => setNewId('') });
    };

    const updatePivot = (employeeId: number, field: string, value: string) => {
        const p = training.employees.find((e) => e.id === employeeId)!.pivot;
        router.patch(`/admin/trainings/${training.id}/participants/${employeeId}`, {
            status: field === 'status' ? value : p.status,
            score: field === 'score' ? value : p.score,
            completed_at: field === 'completed_at' ? value : p.completed_at,
        }, { preserveScroll: true });
    };

    const removeParticipant = (employeeId: number) => {
        if (confirm('¿Remover participante?')) router.delete(`/admin/trainings/${training.id}/participants/${employeeId}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Capacitación', href: '/admin/trainings' }, { title: training.title, href: '' }]}>
            <FlashMessage />
            <div className="p-6 space-y-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{training.title}</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            {modalityLabels[training.modality]} · {d(training.start_date)} → {d(training.end_date)} · {Number(training.hours).toFixed(1)} h · Bs {Number(training.cost).toFixed(2)}
                        </p>
                    </div>
                    <Button variant="outline" asChild><Link href={`/admin/trainings/${training.id}/edit`}><Pencil className="mr-2 h-4 w-4" /> Editar</Link></Button>
                </div>

                {training.description && (
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-sm text-gray-600">{training.description}</p>
                    </div>
                )}

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h2 className="font-semibold text-gray-700">Participantes ({training.employees.length})</h2>
                        <div className="flex items-center gap-2">
                            <select className="rounded-md border px-2 py-1.5 text-sm" value={newId} onChange={(e) => setNewId(e.target.value)}>
                                <option value="">Agregar empleado…</option>
                                {available.map((e) => <option key={e.id} value={e.id}>{e.first_name} {e.last_name}</option>)}
                            </select>
                            <Button size="sm" onClick={addParticipant} disabled={!newId}><UserPlus className="mr-1 h-4 w-4" /> Agregar</Button>
                        </div>
                    </div>
                    {training.employees.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-gray-400">Sin participantes.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-4 py-2">Empleado</th>
                                    <th className="px-4 py-2">Estado</th>
                                    <th className="px-4 py-2">Nota</th>
                                    <th className="px-4 py-2">Completado</th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {training.employees.map((e) => (
                                    <tr key={e.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-2 font-medium">{e.first_name} {e.last_name}<span className="ml-1 text-xs text-gray-400">{e.position}</span></td>
                                        <td className="px-4 py-2">
                                            <select className="rounded-md border px-2 py-1 text-sm" value={e.pivot.status} onChange={(ev) => updatePivot(e.id, 'status', ev.target.value)}>
                                                {pStatus.map((s) => <option key={s.v} value={s.v}>{s.l}</option>)}
                                            </select>
                                        </td>
                                        <td className="px-4 py-2">
                                            <input type="number" className="w-20 rounded-md border px-2 py-1 text-sm" defaultValue={e.pivot.score ?? ''} onBlur={(ev) => updatePivot(e.id, 'score', ev.target.value)} />
                                        </td>
                                        <td className="px-4 py-2">
                                            <input type="date" className="rounded-md border px-2 py-1 text-sm" defaultValue={d(e.pivot.completed_at) === '—' ? '' : d(e.pivot.completed_at)} onBlur={(ev) => updatePivot(e.id, 'completed_at', ev.target.value)} />
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button variant="ghost" size="sm" onClick={() => removeParticipant(e.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
