import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';
import { Eye, FileText, Search } from 'lucide-react';
import { useState } from 'react';

interface Invoice {
    id: number;
    numero_factura: number;
    cuf: string;
    nit_ci: string;
    nombre_razon_social: string;
    importe_total: string;
    tipo_factura: number;
    tipo_fact_label: string;
    estado: string;
    estado_label: string;
    created_at: string;
    sale: { id: number; folio: string };
    store: { name: string };
}

interface Paginated<T> { data: T[]; current_page: number; last_page: number; total: number }

const estadoColors: Record<string, string> = {
    pendiente: 'bg-amber-100 text-amber-700',
    enviada: 'bg-green-100 text-green-700',
    anulada: 'bg-red-100 text-red-700',
    contingencia: 'bg-blue-100 text-blue-700',
};

export default function SiatInvoicesIndex({ invoices, filters }: { invoices: Paginated<Invoice>; filters: any }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [estado, setEstado] = useState(filters.estado ?? '');

    const applyFilters = () => {
        router.get('/admin/siat/invoices', { search, estado }, { preserveState: true });
    };

    const fmt = (v: string) => `Bs ${parseFloat(v).toFixed(2)}`;
    const fmtDate = (d: string) => new Date(d).toLocaleString('es-BO');

    return (
        <AppLayout breadcrumbs={[{ title: 'SIAT Bolivia', href: '' }, { title: 'Facturas Electrónicas', href: '' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Facturas Electrónicas SIAT</h1>
                        <p className="text-sm text-gray-500">Registro de facturas Bolivia v2 — {invoices.total} total</p>
                    </div>
                </div>

                {/* Filtros */}
                <div className="mb-4 flex flex-wrap gap-3">
                    <div className="relative flex-1 min-w-48">
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                        <Input
                            className="pl-9"
                            placeholder="Nro. factura, CUF, NIT/CI, nombre…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                        />
                    </div>
                    <select
                        className="rounded-md border px-3 py-2 text-sm"
                        value={estado}
                        onChange={(e) => { setEstado(e.target.value); }}
                    >
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="enviada">Enviada</option>
                        <option value="anulada">Anulada</option>
                        <option value="contingencia">Contingencia</option>
                    </select>
                    <Button onClick={applyFilters} variant="outline">Filtrar</Button>
                </div>

                <div className="rounded-lg border bg-white shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3 text-left">Nro.</th>
                                <th className="px-4 py-3 text-left">CUF</th>
                                <th className="px-4 py-3 text-left">Comprador</th>
                                <th className="px-4 py-3 text-left">Tipo</th>
                                <th className="px-4 py-3 text-right">Importe</th>
                                <th className="px-4 py-3 text-center">Estado</th>
                                <th className="px-4 py-3 text-left">Fecha</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {invoices.data.map((inv) => (
                                <tr key={inv.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-bold text-blue-700">#{inv.numero_factura}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-500">
                                        <span title={inv.cuf}>{inv.cuf.substring(0, 16)}…</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">{inv.nombre_razon_social}</p>
                                        <p className="text-xs text-gray-400">{inv.nit_ci === '0' ? 'Sin NIT/CI' : inv.nit_ci}</p>
                                    </td>
                                    <td className="px-4 py-3 text-xs">
                                        {inv.tipo_factura === 1
                                            ? <span className="rounded-full bg-blue-50 px-2 py-0.5 text-blue-700">Con CF</span>
                                            : <span className="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600">Sin CF</span>}
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold">{fmt(inv.importe_total)}</td>
                                    <td className="px-4 py-3 text-center">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${estadoColors[inv.estado] ?? 'bg-gray-100 text-gray-600'}`}>
                                            {inv.estado_label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-400">{fmtDate(inv.created_at)}</td>
                                    <td className="px-4 py-3">
                                        <Link href={`/admin/siat/invoices/${inv.id}`}>
                                            <Button size="sm" variant="ghost" className="gap-1">
                                                <Eye className="h-3.5 w-3.5" /> Ver
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {invoices.data.length === 0 && (
                        <div className="py-16 text-center">
                            <FileText className="mx-auto h-10 w-10 text-gray-200 mb-2" />
                            <p className="text-gray-400">No hay facturas registradas.</p>
                        </div>
                    )}
                </div>

                {/* Paginación */}
                {invoices.last_page > 1 && (
                    <div className="mt-4 flex justify-center gap-2">
                        {Array.from({ length: invoices.last_page }, (_, i) => i + 1).map((page) => (
                            <button
                                key={page}
                                onClick={() => router.get('/admin/siat/invoices', { ...filters, page })}
                                className={`rounded px-3 py-1 text-sm ${page === invoices.current_page ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50'}`}
                            >
                                {page}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
