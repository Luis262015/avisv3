import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Power, Trash2 } from 'lucide-react';

interface Promotion {
    id: number; name: string; code: string | null; type: string; value: string; combo_price: string | null; scope: string;
    min_purchase: string; starts_at: string | null; ends_at: string | null;
    usage_limit: number | null; used_count: number; is_active: boolean; sales_count: number; combo_items_count: number;
}

const typeLabels: Record<string, string> = { percentage: 'Porcentaje', fixed: 'Monto fijo', buy_x_get_y: 'Lleva X paga Y', combo: 'Combo' };
const scopeLabels: Record<string, string> = { all: 'Toda la venta', product: 'Productos', category: 'Categorías' };

export default function PromotionsIndex({ promotions }: { promotions: PaginatedData<Promotion> }) {
    const toggle = (id: number) => router.patch(`/admin/promotions/${id}/toggle`);
    const destroy = (id: number) => { if (confirm('¿Eliminar promoción?')) router.delete(`/admin/promotions/${id}`); };

    const valueLabel = (p: Promotion) =>
        p.type === 'percentage' ? `${parseFloat(p.value).toFixed(0)}%`
            : p.type === 'fixed' ? `$${parseFloat(p.value).toFixed(2)}`
                : p.type === 'combo' ? `$${parseFloat(p.combo_price ?? '0').toFixed(2)}`
                    : 'Lleva X paga Y';

    return (
        <AppLayout breadcrumbs={[{ title: 'Promociones', href: '/admin/promotions' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Descuentos y Promociones</h1>
                    <Button asChild><Link href="/admin/promotions/create"><Plus className="mr-2 h-4 w-4" /> Nueva Promoción</Link></Button>
                </div>
                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Nombre</th>
                                <th className="px-4 py-3">Cupón</th>
                                <th className="px-4 py-3">Tipo</th>
                                <th className="px-4 py-3">Valor</th>
                                <th className="px-4 py-3">Alcance</th>
                                <th className="px-4 py-3">Vigencia</th>
                                <th className="px-4 py-3 text-center">Usos</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {promotions.data.map((p) => (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{p.name}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-500">{p.code ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{typeLabels[p.type]}</td>
                                    <td className="px-4 py-3 font-medium">{valueLabel(p)}</td>
                                    <td className="px-4 py-3 text-gray-500">{p.type === 'combo' ? `${p.combo_items_count} productos` : scopeLabels[p.scope]}</td>
                                    <td className="px-4 py-3 text-gray-500">
                                        {p.starts_at ?? '—'} → {p.ends_at ?? '∞'}
                                    </td>
                                    <td className="px-4 py-3 text-center text-gray-500">
                                        {p.used_count}{p.usage_limit ? `/${p.usage_limit}` : ''}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {p.is_active ? 'Activa' : 'Inactiva'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" title="Activar/Desactivar" onClick={() => toggle(p.id)}><Power className="h-4 w-4" /></Button>
                                            <Button variant="ghost" size="sm" asChild><Link href={`/admin/promotions/${p.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                                            <Button variant="ghost" size="sm" onClick={() => destroy(p.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {promotions.data.length === 0 && (
                                <tr><td colSpan={9} className="px-4 py-8 text-center text-gray-400">Sin promociones.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
