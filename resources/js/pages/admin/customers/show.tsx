import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';

interface Customer {
    id: number; name: string; document_type: 'ci' | 'nit' | 'none'; document_number: string | null;
    phone: string | null; email: string | null; address: string | null; notes: string | null; is_active: boolean;
}
interface SaleRow { id: number; folio: string; total: string; status: string; created_at: string }
interface QuoteRow { id: number; folio: string; total: string; status: string; date: string }

export default function CustomerShow({ customer, stats, sales, quotes }: {
    customer: Customer;
    stats: { total_sales: number; total_amount: string };
    sales: SaleRow[];
    quotes: QuoteRow[];
}) {
    const doc = customer.document_type !== 'none' && customer.document_number
        ? `${customer.document_type.toUpperCase()} ${customer.document_number}` : '—';

    return (
        <AppLayout breadcrumbs={[{ title: 'Clientes', href: '/admin/customers' }, { title: customer.name, href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-4xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">{customer.name}</h1>
                    <Button variant="outline" asChild>
                        <Link href={`/admin/customers/${customer.id}/edit`}><Pencil className="mr-2 h-4 w-4" /> Editar</Link>
                    </Button>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase text-gray-400">Ventas completadas</p>
                        <p className="text-2xl font-bold">{stats.total_sales}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase text-gray-400">Total comprado</p>
                        <p className="text-2xl font-bold">${parseFloat(stats.total_amount).toFixed(2)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase text-gray-400">Estado</p>
                        <p className="text-2xl font-bold">{customer.is_active ? 'Activo' : 'Inactivo'}</p>
                    </div>
                </div>

                <div className="rounded-lg border bg-white p-4 shadow-sm">
                    <h2 className="mb-3 font-semibold text-gray-700">Datos de contacto</h2>
                    <div className="grid grid-cols-2 gap-3 text-sm">
                        <p><span className="text-gray-400">Documento:</span> {doc}</p>
                        <p><span className="text-gray-400">Teléfono:</span> {customer.phone ?? '—'}</p>
                        <p><span className="text-gray-400">Email:</span> {customer.email ?? '—'}</p>
                        <p><span className="text-gray-400">Dirección:</span> {customer.address ?? '—'}</p>
                    </div>
                    {customer.notes && <p className="mt-3 text-sm text-gray-500">{customer.notes}</p>}
                </div>

                <div className="rounded-lg border bg-white p-4 shadow-sm">
                    <h2 className="mb-3 font-semibold text-gray-700">Últimas ventas</h2>
                    <table className="w-full text-sm">
                        <thead className="border-b text-left text-xs uppercase text-gray-400">
                            <tr><th className="py-2">Folio</th><th className="py-2">Fecha</th><th className="py-2 text-right">Total</th><th className="py-2">Estado</th></tr>
                        </thead>
                        <tbody className="divide-y">
                            {sales.map((s) => (
                                <tr key={s.id} className="hover:bg-gray-50">
                                    <td className="py-2 font-mono">
                                        <Link href={`/admin/sales/${s.id}`} className="text-blue-600 hover:underline">{s.folio}</Link>
                                    </td>
                                    <td className="py-2 text-gray-500">{new Date(s.created_at).toLocaleDateString()}</td>
                                    <td className="py-2 text-right font-medium">${parseFloat(s.total).toFixed(2)}</td>
                                    <td className="py-2 text-gray-500">{s.status}</td>
                                </tr>
                            ))}
                            {sales.length === 0 && <tr><td colSpan={4} className="py-4 text-center text-gray-400">Sin ventas.</td></tr>}
                        </tbody>
                    </table>
                </div>

                <div className="rounded-lg border bg-white p-4 shadow-sm">
                    <h2 className="mb-3 font-semibold text-gray-700">Cotizaciones</h2>
                    <table className="w-full text-sm">
                        <thead className="border-b text-left text-xs uppercase text-gray-400">
                            <tr><th className="py-2">Folio</th><th className="py-2">Fecha</th><th className="py-2 text-right">Total</th><th className="py-2">Estado</th></tr>
                        </thead>
                        <tbody className="divide-y">
                            {quotes.map((q) => (
                                <tr key={q.id} className="hover:bg-gray-50">
                                    <td className="py-2 font-mono">
                                        <Link href={`/admin/quotes/${q.id}`} className="text-blue-600 hover:underline">{q.folio}</Link>
                                    </td>
                                    <td className="py-2 text-gray-500">{q.date}</td>
                                    <td className="py-2 text-right font-medium">${parseFloat(q.total).toFixed(2)}</td>
                                    <td className="py-2 text-gray-500">{q.status}</td>
                                </tr>
                            ))}
                            {quotes.length === 0 && <tr><td colSpan={4} className="py-4 text-center text-gray-400">Sin cotizaciones.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
