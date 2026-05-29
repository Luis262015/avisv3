import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';

interface Evaluation {
    id: number;
    overall_score: string;
    delivery_score: string;
    quality_score: string;
    price_score: string;
    comments: string | null;
    evaluated_at: string;
    user: { name: string };
    purchase: { id: number; folio: string } | null;
}

interface Supplier { id: number; name: string }

function ScoreBadge({ value }: { value: string }) {
    const n = parseFloat(value);
    const color = n >= 4 ? 'bg-green-100 text-green-700' : n >= 3 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700';
    return <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${color}`}>{n.toFixed(1)}</span>;
}

export default function EvaluationsIndex({ supplier, evaluations }: { supplier: Supplier; evaluations: PaginatedData<Evaluation> }) {
    const destroy = (id: number) => {
        if (confirm('¿Eliminar evaluación?')) {
            router.delete(`/admin/suppliers/${supplier.id}/evaluations/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'Proveedores', href: '/admin/suppliers' },
            { title: supplier.name, href: `/admin/suppliers/${supplier.id}` },
            { title: 'Evaluaciones', href: '' },
        ]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Evaluaciones — {supplier.name}</h1>
                    <Button asChild>
                        <Link href={`/admin/suppliers/${supplier.id}/evaluations/create`}>
                            <Plus className="mr-2 h-4 w-4" /> Nueva Evaluación
                        </Link>
                    </Button>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Fecha</th>
                                <th className="px-4 py-3">General</th>
                                <th className="px-4 py-3">Entrega</th>
                                <th className="px-4 py-3">Calidad</th>
                                <th className="px-4 py-3">Precio</th>
                                <th className="px-4 py-3">Compra ref.</th>
                                <th className="px-4 py-3">Evaluador</th>
                                <th className="px-4 py-3">Comentarios</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {evaluations.data.map((e) => (
                                <tr key={e.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 text-gray-500">{e.evaluated_at}</td>
                                    <td className="px-4 py-3"><ScoreBadge value={e.overall_score} /></td>
                                    <td className="px-4 py-3"><ScoreBadge value={e.delivery_score} /></td>
                                    <td className="px-4 py-3"><ScoreBadge value={e.quality_score} /></td>
                                    <td className="px-4 py-3"><ScoreBadge value={e.price_score} /></td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {e.purchase ? (
                                            <Link href={`/admin/purchases/${e.purchase.id}`} className="text-blue-600 hover:underline">{e.purchase.folio}</Link>
                                        ) : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">{e.user.name}</td>
                                    <td className="px-4 py-3 text-gray-500 max-w-xs truncate">{e.comments ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <Button variant="ghost" size="sm" onClick={() => destroy(e.id)}>
                                            <Trash2 className="h-4 w-4 text-red-500" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {evaluations.data.length === 0 && (
                                <tr><td colSpan={9} className="px-4 py-8 text-center text-gray-400">Sin evaluaciones registradas.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
