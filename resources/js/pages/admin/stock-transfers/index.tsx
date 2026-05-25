import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight, Plus } from 'lucide-react';

interface Transfer {
    id: number;
    folio: string;
    status: 'pending' | 'completed' | 'cancelled';
    notes: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    created_at: string;
    from_store: { name: string };
    to_store: { name: string };
    user: { name: string };
}

const statusColors: Record<string, string> = {
    pending:   'bg-yellow-100 text-yellow-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};

const statusLabels: Record<string, string> = {
    pending:   'Pendiente',
    completed: 'Completada',
    cancelled: 'Cancelada',
};

export default function StockTransfersIndex({ transfers }: { transfers: PaginatedData<Transfer> }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Transferencias de stock', href: '/admin/stock-transfers' }]}>
            <FlashMessage />
            <div className="p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Transferencias de stock</h1>
                    <Link href="/admin/stock-transfers/create">
                        <Button><Plus className="mr-1 h-4 w-4" /> Nueva transferencia</Button>
                    </Link>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Folio</th>
                                <th className="px-4 py-3">Origen</th>
                                <th className="px-4 py-3" />
                                <th className="px-4 py-3">Destino</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3">Solicitado por</th>
                                <th className="px-4 py-3">Fecha</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {transfers.data.map((t) => (
                                <tr key={t.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono font-medium">{t.folio}</td>
                                    <td className="px-4 py-3">{t.from_store.name}</td>
                                    <td className="px-4 py-3 text-gray-400"><ArrowRight className="h-4 w-4" /></td>
                                    <td className="px-4 py-3">{t.to_store.name}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[t.status]}`}>
                                            {statusLabels[t.status]}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">{t.user.name}</td>
                                    <td className="px-4 py-3 text-xs text-gray-400">
                                        {new Date(t.created_at).toLocaleString('es-MX')}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link href={`/admin/stock-transfers/${t.id}`} className="text-blue-600 hover:underline text-xs">
                                            Ver detalle
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {transfers.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center text-gray-400">
                                        Sin transferencias registradas.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination links */}
                {transfers.links && (
                    <div className="flex gap-1 justify-end">
                        {transfers.links.map((link, i) => (
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`rounded px-3 py-1 text-sm border ${link.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="rounded px-3 py-1 text-sm border bg-white text-gray-300"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            )
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
