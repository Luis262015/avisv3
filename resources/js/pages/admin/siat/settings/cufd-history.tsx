import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';
import { CheckCircle, Clock } from 'lucide-react';

interface CufdCode {
    id: number;
    codigo: string;
    codigo_control: string;
    fecha_vigencia: string;
    consecutivo: number;
    estado: string;
    created_at: string;
}

interface Setting {
    id: number;
    nit: string;
    razon_social: string;
    store: { name: string };
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export default function CufdHistory({ setting, codes }: { setting: Setting; codes: Paginated<CufdCode> }) {
    const fmt = (d: string) => new Date(d).toLocaleString('es-BO');

    return (
        <AppLayout breadcrumbs={[
            { title: 'SIAT Bolivia', href: '/admin/siat/settings' },
            { title: setting.razon_social, href: `/admin/siat/settings/${setting.id}/edit` },
            { title: 'Historial CUFD', href: '' },
        ]}>
            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold">Historial de CUFDs</h1>
                    <p className="text-sm text-gray-500">NIT: {setting.nit} — {setting.store.name}</p>
                </div>

                <div className="rounded-lg border bg-white shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3 text-left">Código CUFD</th>
                                <th className="px-4 py-3 text-left">Ctrl</th>
                                <th className="px-4 py-3 text-left">Vigencia</th>
                                <th className="px-4 py-3 text-right">Facturas</th>
                                <th className="px-4 py-3 text-center">Estado</th>
                                <th className="px-4 py-3 text-left">Generado</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {codes.data.map((c) => (
                                <tr key={c.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono text-xs max-w-xs truncate">
                                        <span title={c.codigo}>{c.codigo.substring(0, 24)}…</span>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-400">{c.codigo_control}</td>
                                    <td className="px-4 py-3 text-xs">{fmt(c.fecha_vigencia)}</td>
                                    <td className="px-4 py-3 text-right font-semibold">{c.consecutivo}</td>
                                    <td className="px-4 py-3 text-center">
                                        {c.estado === 'activo'
                                            ? <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700"><CheckCircle className="h-3 w-3" /> Activo</span>
                                            : <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500"><Clock className="h-3 w-3" /> Vencido</span>}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-400">{fmt(c.created_at)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {codes.data.length === 0 && (
                        <p className="px-4 py-8 text-center text-gray-400">No hay CUFDs generados aún.</p>
                    )}
                </div>

                <div className="mt-4">
                    <Link href="/admin/siat/settings" className="text-sm text-blue-600 hover:underline">← Volver a configuraciones</Link>
                </div>
            </div>
        </AppLayout>
    );
}
