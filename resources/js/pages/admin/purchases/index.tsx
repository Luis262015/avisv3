import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

interface Purchase {
    id: number; folio: string; date: string; total: string;
    status: string; payment_status: string;
    invoice_number: string | null;
    supplier: { name: string } | null;
    store: { name: string } | null;
    user: { name: string };
}

interface Option { id: number; name: string }

interface Filters {
    search?: string; status?: string; payment_status?: string;
    supplier_id?: string; store_id?: string; from?: string; to?: string;
}

const statusColors: Record<string, string> = {
    pending:   'bg-yellow-100 text-yellow-700',
    partial:   'bg-blue-100 text-blue-700',
    received:  'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = {
    pending: 'Pendiente', partial: 'Parcial', received: 'Recibida', cancelled: 'Cancelada',
};

const paymentColors: Record<string, string> = {
    unpaid:    'bg-red-100 text-red-700',
    partial:   'bg-yellow-100 text-yellow-700',
    paid:      'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-600',
};
const paymentLabels: Record<string, string> = {
    unpaid: 'No pagada', partial: 'Parcial', paid: 'Pagada', cancelled: 'Anulada',
};

const inputClass = 'rounded-md border px-3 py-2 text-sm';

export default function PurchasesIndex({
    purchases, filters, suppliers, stores,
}: {
    purchases: PaginatedData<Purchase>;
    filters: Filters;
    suppliers: Option[];
    stores: Option[];
}) {
    const { auth } = usePage<{ auth: { roles: string[] } }>().props;
    const canEdit = auth.roles.includes('admin') || auth.roles.includes('operador');

    const [form, setForm] = useState<Filters>(filters ?? {});

    const apply = (next: Filters) => {
        setForm(next);
        router.get('/admin/purchases', next as Record<string, string>, {
            preserveState: true,
            replace: true,
        });
    };

    const set = (key: keyof Filters, value: string) => apply({ ...form, [key]: value || undefined });

    return (
        <AppLayout breadcrumbs={[{ title: 'Compras', href: '/admin/purchases' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Compras</h1>
                    <Button asChild>
                        <Link href="/admin/purchases/create"><Plus className="mr-2 h-4 w-4" /> Nueva Compra</Link>
                    </Button>
                </div>

                <div className="mb-4 flex flex-wrap gap-2 rounded-lg border bg-white p-3 shadow-sm">
                    <input
                        className={`${inputClass} min-w-48 flex-1`}
                        placeholder="Buscar folio o factura…"
                        value={form.search ?? ''}
                        onChange={(e) => setForm({ ...form, search: e.target.value })}
                        onKeyDown={(e) => e.key === 'Enter' && apply(form)}
                    />
                    <select className={inputClass} value={form.status ?? ''} onChange={(e) => set('status', e.target.value)}>
                        <option value="">Toda recepción</option>
                        {Object.entries(statusLabels).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                    <select className={inputClass} value={form.payment_status ?? ''} onChange={(e) => set('payment_status', e.target.value)}>
                        <option value="">Todo pago</option>
                        {Object.entries(paymentLabels).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                    <select className={inputClass} value={form.supplier_id ?? ''} onChange={(e) => set('supplier_id', e.target.value)}>
                        <option value="">Todo proveedor</option>
                        {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                    <select className={inputClass} value={form.store_id ?? ''} onChange={(e) => set('store_id', e.target.value)}>
                        <option value="">Toda tienda</option>
                        {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                    <input type="date" className={inputClass} value={form.from ?? ''} onChange={(e) => set('from', e.target.value)} />
                    <input type="date" className={inputClass} value={form.to ?? ''} onChange={(e) => set('to', e.target.value)} />
                    <Button variant="ghost" size="sm" onClick={() => apply({})}>Limpiar</Button>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Folio</th>
                                <th className="px-4 py-3">Factura</th>
                                <th className="px-4 py-3">Fecha</th>
                                <th className="px-4 py-3">Proveedor</th>
                                <th className="px-4 py-3">Tienda</th>
                                <th className="px-4 py-3 text-right">Total</th>
                                <th className="px-4 py-3">Recepción</th>
                                <th className="px-4 py-3">Pago</th>
                                <th className="px-4 py-3">Registrada por</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {purchases.data.map((p) => (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono font-medium">{p.folio}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-400">{p.invoice_number ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{p.date}</td>
                                    <td className="px-4 py-3 text-gray-600">{p.supplier?.name ?? 'Sin proveedor'}</td>
                                    <td className="px-4 py-3 text-gray-600">{p.store?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-right font-medium">${parseFloat(p.total).toFixed(2)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[p.status]}`}>
                                            {statusLabels[p.status]}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${paymentColors[p.payment_status]}`}>
                                            {paymentLabels[p.payment_status]}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">{p.user.name}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/purchases/${p.id}`}><Eye className="h-4 w-4" /></Link>
                                            </Button>
                                            {/* Solo las compras pendientes son editables: una vez recibida,
                                                el stock y la CxP ya dependen de sus líneas. */}
                                            {canEdit && p.status === 'pending' && (
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={`/admin/purchases/${p.id}/edit`}><Pencil className="h-4 w-4" /></Link>
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {purchases.data.length === 0 && (
                                <tr><td colSpan={10} className="px-4 py-8 text-center text-gray-400">Sin compras registradas.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
