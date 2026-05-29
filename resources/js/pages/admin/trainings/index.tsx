import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';

interface Training {
    id: number; title: string; provider: string | null; modality: string; status: string;
    start_date: string | null; hours: string; cost: string; employees_count: number;
}

const modalityLabels: Record<string, string> = { internal: 'Interna', external: 'Externa', online: 'En línea' };
const statusBadge: Record<string, { label: string; cls: string }> = {
    planned: { label: 'Planificada', cls: 'bg-gray-100 text-gray-600' },
    in_progress: { label: 'En curso', cls: 'bg-blue-100 text-blue-700' },
    completed: { label: 'Completada', cls: 'bg-green-100 text-green-700' },
    cancelled: { label: 'Cancelada', cls: 'bg-red-100 text-red-700' },
};
const d = (v: string | null) => (v ? v.substring(0, 10) : '—');

export default function TrainingsIndex({ trainings }: { trainings: PaginatedData<Training> }) {
    const remove = (id: number) => { if (confirm('¿Eliminar capacitación?')) router.delete(`/admin/trainings/${id}`); };

    return (
        <AppLayout breadcrumbs={[{ title: 'Capacitación', href: '/admin/trainings' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Capacitación y formación</h1>
                    <Button asChild><Link href="/admin/trainings/create"><Plus className="mr-2 h-4 w-4" /> Nueva capacitación</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Título</th>
                                <th className="px-4 py-3">Modalidad</th>
                                <th className="px-4 py-3">Inicio</th>
                                <th className="px-4 py-3 text-center">Participantes</th>
                                <th className="px-4 py-3 text-right">Horas</th>
                                <th className="px-4 py-3 text-right">Costo</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {trainings.data.map((t) => (
                                <tr key={t.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{t.title}{t.provider ? <span className="ml-1 text-xs text-gray-400">· {t.provider}</span> : null}</td>
                                    <td className="px-4 py-3 text-gray-500">{modalityLabels[t.modality]}</td>
                                    <td className="px-4 py-3 text-gray-500">{d(t.start_date)}</td>
                                    <td className="px-4 py-3 text-center text-gray-500">{t.employees_count}</td>
                                    <td className="px-4 py-3 text-right text-gray-500">{Number(t.hours).toFixed(1)}</td>
                                    <td className="px-4 py-3 text-right text-gray-500">Bs {Number(t.cost).toFixed(2)}</td>
                                    <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusBadge[t.status].cls}`}>{statusBadge[t.status].label}</span></td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" asChild><Link href={`/admin/trainings/${t.id}`}><Eye className="h-4 w-4" /></Link></Button>
                                            <Button variant="ghost" size="sm" asChild><Link href={`/admin/trainings/${t.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                                            <Button variant="ghost" size="sm" onClick={() => remove(t.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {trainings.data.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Sin capacitaciones.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
