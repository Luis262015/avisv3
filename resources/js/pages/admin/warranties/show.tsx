import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Link, router, useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';

interface Claim { id: number; date: string; description: string; status: string; resolution: string | null; user: { name: string } }
interface Warranty {
    id: number; folio: string; serial_number: string | null; start_date: string; end_date: string;
    terms: string | null; status: string;
    product: { name: string; sku: string | null } | null;
    customer: { id: number; name: string } | null;
    sale: { id: number; folio: string } | null;
    claims: Claim[];
}

const statusColors: Record<string, string> = {
    active: 'bg-green-100 text-green-700', expired: 'bg-orange-100 text-orange-700', void: 'bg-red-100 text-red-700',
};
const statusLabels: Record<string, string> = { active: 'Vigente', expired: 'Vencida', void: 'Anulada' };
const claimStatusLabels: Record<string, string> = { open: 'Abierto', in_progress: 'En proceso', resolved: 'Resuelto', rejected: 'Rechazado' };

export default function WarrantyShow({ warranty }: { warranty: Warranty }) {
    const [showClaim, setShowClaim] = useState(false);
    const claimForm = useForm({ date: new Date().toISOString().split('T')[0], description: '' });

    const updateClaim = (claimId: number, status: string) => {
        router.patch(`/admin/warranties/${warranty.id}/claims/${claimId}`, { status, date: new Date().toISOString().split('T')[0], description: '_' });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Garantías', href: '/admin/warranties' }, { title: warranty.folio, href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-4xl space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-bold">Garantía {warranty.folio}</h1>
                            <span className={`rounded-full px-3 py-1 text-sm font-semibold ${statusColors[warranty.status]}`}>{statusLabels[warranty.status]}</span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">{warranty.product?.name} · {warranty.start_date} → {warranty.end_date}</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><Link href={`/admin/warranties/${warranty.id}/edit`}><Pencil className="mr-2 h-4 w-4" /> Editar</Link></Button>
                        {warranty.status !== 'void' && (
                            <Button variant="destructive" onClick={() => { if (confirm('¿Anular garantía?')) router.patch(`/admin/warranties/${warranty.id}/void`); }}>Anular</Button>
                        )}
                    </div>
                </div>

                <div className="rounded-lg border bg-white p-4 shadow-sm text-sm text-gray-600 space-y-1">
                    <p><span className="text-gray-400">Producto:</span> {warranty.product?.name ?? '—'}</p>
                    <p><span className="text-gray-400">Serie:</span> {warranty.serial_number ?? '—'}</p>
                    <p><span className="text-gray-400">Cliente:</span> {warranty.customer ? <Link href={`/admin/customers/${warranty.customer.id}`} className="text-blue-600 hover:underline">{warranty.customer.name}</Link> : '—'}</p>
                    {warranty.sale && <p><span className="text-gray-400">Venta:</span> <Link href={`/admin/sales/${warranty.sale.id}`} className="text-blue-600 hover:underline">{warranty.sale.folio}</Link></p>}
                    {warranty.terms && <p><span className="text-gray-400">Términos:</span> {warranty.terms}</p>}
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <span className="font-semibold text-gray-700">Reclamos</span>
                        <Button size="sm" variant="outline" onClick={() => setShowClaim((v) => !v)}>Nuevo reclamo</Button>
                    </div>

                    {showClaim && (
                        <form onSubmit={(e) => { e.preventDefault(); claimForm.post(`/admin/warranties/${warranty.id}/claims`, { onSuccess: () => { claimForm.reset('description'); setShowClaim(false); } }); }} className="border-b p-4">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                                <div>
                                    <Label>Fecha</Label>
                                    <Input type="date" value={claimForm.data.date} onChange={(e) => claimForm.setData('date', e.target.value)} />
                                </div>
                                <div className="md:col-span-3">
                                    <Label>Descripción</Label>
                                    <Input value={claimForm.data.description} onChange={(e) => claimForm.setData('description', e.target.value)} />
                                    {claimForm.errors.description && <p className="mt-1 text-xs text-red-500">{claimForm.errors.description}</p>}
                                </div>
                            </div>
                            <div className="mt-3"><Button type="submit" size="sm" disabled={claimForm.processing}>Registrar reclamo</Button></div>
                        </form>
                    )}

                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Fecha</th>
                                <th className="px-4 py-3">Descripción</th>
                                <th className="px-4 py-3">Registrado por</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3">Cambiar estado</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {warranty.claims.map((c) => (
                                <tr key={c.id}>
                                    <td className="px-4 py-3 text-gray-500">{c.date}</td>
                                    <td className="px-4 py-3">{c.description}</td>
                                    <td className="px-4 py-3 text-gray-500">{c.user.name}</td>
                                    <td className="px-4 py-3">{claimStatusLabels[c.status]}</td>
                                    <td className="px-4 py-3">
                                        <select className="rounded-md border px-2 py-1 text-xs" value={c.status} onChange={(e) => updateClaim(c.id, e.target.value)}>
                                            <option value="open">Abierto</option>
                                            <option value="in_progress">En proceso</option>
                                            <option value="resolved">Resuelto</option>
                                            <option value="rejected">Rechazado</option>
                                        </select>
                                    </td>
                                </tr>
                            ))}
                            {warranty.claims.length === 0 && (
                                <tr><td colSpan={5} className="px-4 py-6 text-center text-gray-400">Sin reclamos.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
