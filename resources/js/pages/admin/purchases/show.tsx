import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Link, router, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';

interface Purchase {
    id: number; folio: string; date: string; subtotal: string; tax: string; total: string; status: string; notes: string | null;
    supplier: { name: string; contact_name: string | null; phone: string | null } | null;
    user: { name: string };
    items: { id: number; quantity: string; cost: string; subtotal: string; product: { name: string; sku: string | null } }[];
}

const statusColors: Record<string, string> = { pending: 'bg-yellow-100 text-yellow-700', received: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700' };
const statusLabels: Record<string, string> = { pending: 'Pendiente', received: 'Recibida', cancelled: 'Cancelada' };

export default function PurchaseShow({ purchase }: { purchase: Purchase }) {
    const { auth } = usePage<{ auth: { roles: string[] } }>().props;
    const canEdit = auth.roles.includes('admin') || auth.roles.includes('operador');
    const fmt = (v: string) => `$${parseFloat(v).toFixed(2)}`;
    const receive = () => { if (confirm('¿Confirmar recepción? Se actualizará el inventario.')) router.patch(`/admin/purchases/${purchase.id}/receive`); };
    const cancel = () => { if (confirm('¿Cancelar esta compra?')) router.patch(`/admin/purchases/${purchase.id}/cancel`); };

    return (
        <AppLayout breadcrumbs={[{ title: 'Compras', href: '/admin/purchases' }, { title: purchase.folio, href: '' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Compra {purchase.folio}</h1>
                        <p className="text-gray-500">Registrada por {purchase.user.name} — {purchase.date}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className={`rounded-full px-3 py-1 text-sm font-semibold ${statusColors[purchase.status]}`}>{statusLabels[purchase.status]}</span>
                        {canEdit && (
                            <Button variant="outline" asChild className="gap-2">
                                <Link href={`/admin/purchases/${purchase.id}/edit`}><Pencil className="h-4 w-4" /> Editar</Link>
                            </Button>
                        )}
                        {purchase.status === 'pending' && (
                            <>
                                <Button onClick={receive}>Recibir y actualizar stock</Button>
                                <Button variant="destructive" onClick={cancel}>Cancelar</Button>
                            </>
                        )}
                    </div>
                </div>

                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    {purchase.supplier && (
                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <h2 className="mb-2 font-semibold text-gray-700">Proveedor</h2>
                            <p className="font-medium">{purchase.supplier.name}</p>
                            {purchase.supplier.contact_name && <p className="text-sm text-gray-500">{purchase.supplier.contact_name}</p>}
                            {purchase.supplier.phone && <p className="text-sm text-gray-500">{purchase.supplier.phone}</p>}
                        </div>
                    )}
                    {purchase.notes && (
                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <h2 className="mb-2 font-semibold text-gray-700">Notas</h2>
                            <p className="text-sm text-gray-600">{purchase.notes}</p>
                        </div>
                    )}
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3">SKU</th>
                                <th className="px-4 py-3 text-right">Cantidad</th>
                                <th className="px-4 py-3 text-right">Costo unit.</th>
                                <th className="px-4 py-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {purchase.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-4 py-3 font-medium">{item.product.name}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-400">{item.product.sku ?? '—'}</td>
                                    <td className="px-4 py-3 text-right">{item.quantity}</td>
                                    <td className="px-4 py-3 text-right">{fmt(item.cost)}</td>
                                    <td className="px-4 py-3 text-right font-medium">{fmt(item.subtotal)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="border-t bg-gray-50">
                            <tr>
                                <td colSpan={4} className="px-4 py-2 text-right text-sm text-gray-500">Subtotal</td>
                                <td className="px-4 py-2 text-right font-medium">{fmt(purchase.subtotal)}</td>
                            </tr>
                            <tr>
                                <td colSpan={4} className="px-4 py-2 text-right text-sm text-gray-500">IVA</td>
                                <td className="px-4 py-2 text-right font-medium">{fmt(purchase.tax)}</td>
                            </tr>
                            <tr>
                                <td colSpan={4} className="px-4 py-2 text-right font-bold">Total</td>
                                <td className="px-4 py-2 text-right text-lg font-bold">{fmt(purchase.total)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
