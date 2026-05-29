import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';

interface Warranty {
    id: number; folio: string; serial_number: string | null; start_date: string; end_date: string; status: string;
    claims_count: number;
    product: { name: string } | null;
    customer: { name: string } | null;
}

const statusColors: Record<string, string> = {
    active: 'bg-green-100 text-green-700', expired: 'bg-orange-100 text-orange-700', void: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = { active: 'Vigente', expired: 'Vencida', void: 'Anulada' };

export default function WarrantiesIndex({ warranties }: { warranties: PaginatedData<Warranty> }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Garantías', href: '/admin/warranties' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Garantías</h1>
                    <Button asChild><Link href="/admin/warranties/create"><Plus className="mr-2 h-4 w-4" /> Nueva Garantía</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Folio</th>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3">Serie</th>
                                <th className="px-4 py-3">Cliente</th>
                                <th className="px-4 py-3">Vigencia</th>
                                <th className="px-4 py-3 text-center">Reclamos</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {warranties.data.map((w) => (
                                <tr key={w.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono font-medium">{w.folio}</td>
                                    <td className="px-4 py-3 font-medium">{w.product?.name ?? '—'}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-500">{w.serial_number ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-600">{w.customer?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{w.start_date} → {w.end_date}</td>
                                    <td className="px-4 py-3 text-center text-gray-500">{w.claims_count}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[w.status]}`}>{statusLabels[w.status]}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="ghost" size="sm" asChild><Link href={`/admin/warranties/${w.id}`}><Eye className="h-4 w-4" /></Link></Button>
                                    </td>
                                </tr>
                            ))}
                            {warranties.data.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Sin garantías.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
