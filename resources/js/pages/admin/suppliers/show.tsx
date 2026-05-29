import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';
import { Building2, Clock, CreditCard, ExternalLink, Pencil, Plus, Star, Trash2 } from 'lucide-react';

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

interface Supplier {
    id: number; name: string; contact_name: string | null; email: string | null;
    phone: string | null; address: string | null; website: string | null;
    payment_terms: string | null; lead_time_days: number | null;
    bank_account: string | null; rfc: string | null; tax_id: string | null;
    avg_rating: string | null; is_active: boolean; notes: string | null;
    evaluations: Evaluation[];
}

interface Stats {
    total_purchases: number;
    total_amount: number;
    pending_payables: number;
}

function ScoreBadge({ value }: { value: string }) {
    const n = parseFloat(value);
    const color = n >= 4 ? 'bg-green-100 text-green-700' : n >= 3 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700';
    return <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${color}`}>{n.toFixed(1)}</span>;
}

export default function SupplierShow({ supplier, stats }: { supplier: Supplier; stats: Stats }) {
    const fmt = (v: number) => `$${v.toFixed(2)}`;
    const destroyEval = (id: number) => {
        if (confirm('¿Eliminar evaluación?')) {
            router.delete(`/admin/suppliers/${supplier.id}/evaluations/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Proveedores', href: '/admin/suppliers' }, { title: supplier.name, href: '' }]}>
            <FlashMessage />
            <div className="p-6 space-y-6">

                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold">{supplier.name}</h1>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${supplier.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                {supplier.is_active ? 'Activo' : 'Inactivo'}
                            </span>
                            {supplier.avg_rating && (
                                <span className="flex items-center gap-1 text-sm font-semibold text-amber-600">
                                    <Star className="h-4 w-4 fill-amber-400 text-amber-400" />
                                    {parseFloat(supplier.avg_rating).toFixed(1)}
                                </span>
                            )}
                        </div>
                        {supplier.contact_name && <p className="mt-1 text-gray-500">{supplier.contact_name}</p>}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/admin/suppliers/${supplier.id}/edit`}><Pencil className="mr-2 h-4 w-4" /> Editar</Link>
                        </Button>
                        <Button asChild>
                            <Link href={`/admin/suppliers/${supplier.id}/evaluations/create`}><Plus className="mr-2 h-4 w-4" /> Nueva Evaluación</Link>
                        </Button>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    {[
                        { label: 'Total compras', value: stats.total_purchases.toString(), icon: Building2, color: 'text-blue-600' },
                        { label: 'Monto comprado', value: fmt(stats.total_amount), icon: CreditCard, color: 'text-green-600' },
                        { label: 'Saldo pendiente', value: fmt(stats.pending_payables), icon: Clock, color: 'text-red-600' },
                    ].map((s) => (
                        <div key={s.label} className="rounded-lg border bg-white p-4 shadow-sm flex items-center gap-4">
                            <s.icon className={`h-8 w-8 ${s.color}`} />
                            <div>
                                <p className="text-xs text-gray-500 uppercase">{s.label}</p>
                                <p className={`text-xl font-bold ${s.color}`}>{s.value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Info cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-2">
                        <h2 className="font-semibold text-gray-700">Contacto</h2>
                        {supplier.email && <p className="text-sm text-gray-600">✉ {supplier.email}</p>}
                        {supplier.phone && <p className="text-sm text-gray-600">📞 {supplier.phone}</p>}
                        {supplier.address && <p className="text-sm text-gray-600">📍 {supplier.address}</p>}
                        {supplier.website && (
                            <a href={supplier.website} target="_blank" rel="noreferrer" className="flex items-center gap-1 text-sm text-blue-600 hover:underline">
                                <ExternalLink className="h-3.5 w-3.5" /> {supplier.website}
                            </a>
                        )}
                        {!supplier.email && !supplier.phone && !supplier.address && !supplier.website && (
                            <p className="text-sm text-gray-400">Sin datos de contacto.</p>
                        )}
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-2">
                        <h2 className="font-semibold text-gray-700">Condiciones comerciales</h2>
                        {supplier.payment_terms && <p className="text-sm text-gray-600"><span className="font-medium">Plazo de pago:</span> {supplier.payment_terms}</p>}
                        {supplier.lead_time_days != null && <p className="text-sm text-gray-600"><span className="font-medium">Tiempo de entrega:</span> {supplier.lead_time_days} días</p>}
                        {supplier.rfc && <p className="text-sm text-gray-600"><span className="font-medium">RFC/NIT:</span> {supplier.rfc}</p>}
                        {supplier.tax_id && <p className="text-sm text-gray-600"><span className="font-medium">Tax ID:</span> {supplier.tax_id}</p>}
                        {supplier.bank_account && <p className="text-sm text-gray-600"><span className="font-medium">Cuenta:</span> {supplier.bank_account}</p>}
                        {!supplier.payment_terms && supplier.lead_time_days == null && <p className="text-sm text-gray-400">Sin condiciones registradas.</p>}
                    </div>
                </div>

                {/* Evaluations */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h2 className="font-semibold text-gray-700">Evaluaciones</h2>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/admin/suppliers/${supplier.id}/evaluations`}>Ver todas</Link>
                        </Button>
                    </div>
                    {supplier.evaluations.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-gray-400">Sin evaluaciones registradas.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-4 py-3">Fecha</th>
                                    <th className="px-4 py-3">General</th>
                                    <th className="px-4 py-3">Entrega</th>
                                    <th className="px-4 py-3">Calidad</th>
                                    <th className="px-4 py-3">Precio</th>
                                    <th className="px-4 py-3">Compra</th>
                                    <th className="px-4 py-3">Evaluador</th>
                                    <th className="px-4 py-3">Comentarios</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {supplier.evaluations.map((e) => (
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
                                            <Button variant="ghost" size="sm" onClick={() => destroyEval(e.id)}>
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {supplier.notes && (
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <h2 className="mb-2 font-semibold text-gray-700">Notas</h2>
                        <p className="text-sm text-gray-600">{supplier.notes}</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
