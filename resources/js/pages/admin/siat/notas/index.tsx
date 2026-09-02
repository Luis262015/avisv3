import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Link, router } from '@inertiajs/react';
import { Eye, RefreshCw, Search } from 'lucide-react';
import { useState } from 'react';

interface Nota {
    id: number;
    numero_nota: number;
    documento_sector: number;
    sector_label: string;
    cuf: string;
    nit_ci: string;
    nombre_razon_social: string;
    monto_total_devuelto: string;
    monto_efectivo: string;
    estado: string;
    estado_label: string;
    created_at: string;
    store: { name: string } | null;
    sale_return: { id: number; folio: string } | null;
    invoice: { id: number; numero_factura: number } | null;
}

interface Paginated<T> { data: T[]; current_page: number; last_page: number; total: number }

const estadoColors: Record<string, string> = {
    pendiente: 'bg-amber-100 text-amber-700',
    enviada: 'bg-green-100 text-green-700',
    validada: 'bg-green-100 text-green-700',
    rechazada: 'bg-red-100 text-red-700',
    anulada: 'bg-gray-200 text-gray-700',
};

export default function SiatNotasIndex({
    notas,
    filtros,
}: {
    notas: Paginated<Nota>;
    filtros: { search?: string; estado?: string };
}) {
    const [search, setSearch] = useState(filtros.search ?? '');
    const [estado, setEstado] = useState(filtros.estado ?? '');
    const [reenviando, setReenviando] = useState<number | null>(null);

    const applyFilters = () => {
        router.get('/admin/siat/notas', { search, estado }, { preserveState: true });
    };

    const resend = (id: number) => {
        setReenviando(id);
        router.post(`/admin/siat/notas/${id}/resend`, {}, {
            preserveScroll: true,
            onFinish: () => setReenviando(null),
        });
    };

    const fmt = (v: string) => `Bs ${parseFloat(v).toFixed(2)}`;
    const fmtDate = (d: string) => new Date(d).toLocaleString('es-BO');

    return (
        <AppLayout breadcrumbs={[{ title: 'SIAT Bolivia', href: '' }, { title: 'Notas de Crédito-Débito', href: '' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold">Notas de Crédito-Débito</h1>
                    <p className="text-sm text-gray-500">
                        Documentos sector 24 y 47 — {notas.total} en total. Ajustan una factura ya emitida.
                    </p>
                </div>

                <div className="mb-4 flex flex-wrap gap-3">
                    <div className="relative min-w-48 flex-1">
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                        <Input
                            className="pl-9"
                            placeholder="Nº de nota, CUF, NIT o razón social"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                        />
                    </div>
                    <select
                        className="rounded-md border border-gray-300 px-3 text-sm"
                        value={estado}
                        onChange={(e) => setEstado(e.target.value)}
                    >
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="enviada">Enviadas</option>
                        <option value="validada">Validadas</option>
                        <option value="rechazada">Rechazadas</option>
                        <option value="anulada">Anuladas</option>
                    </select>
                    <Button onClick={applyFilters}>Filtrar</Button>
                </div>

                <div className="overflow-x-auto rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Nota</th>
                                <th className="px-4 py-3">Sector</th>
                                <th className="px-4 py-3">Factura</th>
                                <th className="px-4 py-3">Devolución</th>
                                <th className="px-4 py-3">Cliente</th>
                                <th className="px-4 py-3 text-right">Devuelto</th>
                                <th className="px-4 py-3 text-right">Crédito fiscal</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3">Emitida</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {notas.data.map((nota) => (
                                <tr key={nota.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-semibold">#{nota.numero_nota}</td>
                                    <td className="px-4 py-3 text-xs text-gray-500">
                                        {nota.documento_sector} · {nota.sector_label}
                                    </td>
                                    <td className="px-4 py-3">
                                        {nota.invoice ? (
                                            <Link
                                                href={`/admin/siat/invoices/${nota.invoice.id}`}
                                                className="text-blue-600 hover:underline"
                                            >
                                                #{nota.invoice.numero_factura}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {nota.sale_return ? (
                                            <Link
                                                href={`/admin/returns/${nota.sale_return.id}`}
                                                className="text-blue-600 hover:underline"
                                            >
                                                {nota.sale_return.folio}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div>{nota.nombre_razon_social}</div>
                                        <div className="text-xs text-gray-400">{nota.nit_ci}</div>
                                    </td>
                                    <td className="px-4 py-3 text-right">{fmt(nota.monto_total_devuelto)}</td>
                                    <td className="px-4 py-3 text-right text-gray-500">{fmt(nota.monto_efectivo)}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`rounded-full px-2 py-1 text-xs font-semibold ${
                                                estadoColors[nota.estado] ?? 'bg-gray-100 text-gray-600'
                                            }`}
                                        >
                                            {nota.estado_label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-500">{fmtDate(nota.created_at)}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            {['pendiente', 'rechazada'].includes(nota.estado) && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={reenviando === nota.id}
                                                    onClick={() => resend(nota.id)}
                                                >
                                                    <RefreshCw
                                                        className={`h-4 w-4 ${reenviando === nota.id ? 'animate-spin' : ''}`}
                                                    />
                                                </Button>
                                            )}
                                            <Link href={`/admin/siat/notas/${nota.id}`}>
                                                <Button size="sm" variant="outline">
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {notas.data.length === 0 && (
                                <tr>
                                    <td colSpan={10} className="px-4 py-10 text-center text-gray-500">
                                        Todavía no hay notas de crédito-débito. Se emiten desde una devolución cuya
                                        venta ya esté facturada.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
