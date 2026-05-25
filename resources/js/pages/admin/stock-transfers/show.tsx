import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { ArrowRight, CheckCircle, XCircle } from 'lucide-react';

interface Product { name: string; sku: string | null }
interface TransferItem { id: number; quantity: number; product: Product }
interface Transfer {
    id: number;
    folio: string;
    status: 'pending' | 'completed' | 'cancelled';
    notes: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    created_at: string;
    from_store: { name: string };
    to_store: { name: string };
    user: { name: string };
    items: TransferItem[];
}

const statusColors: Record<string, string> = {
    pending:   'bg-yellow-100 text-yellow-700 border-yellow-300',
    completed: 'bg-green-100 text-green-700 border-green-300',
    cancelled: 'bg-red-100 text-red-700 border-red-300',
};

const statusLabels: Record<string, string> = {
    pending:   'Pendiente',
    completed: 'Completada',
    cancelled: 'Cancelada',
};

export default function StockTransferShow({ transfer }: { transfer: Transfer }) {
    const complete = () => {
        if (confirm('¿Confirmar la transferencia? Se moverá el stock entre tiendas.')) {
            router.patch(`/admin/stock-transfers/${transfer.id}/complete`);
        }
    };

    const cancel = () => {
        if (confirm('¿Cancelar esta transferencia?')) {
            router.patch(`/admin/stock-transfers/${transfer.id}/cancel`);
        }
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'Transferencias', href: '/admin/stock-transfers' },
            { title: transfer.folio, href: '' },
        ]}>
            <FlashMessage />
            <div className="mx-auto max-w-3xl p-6 space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold font-mono">{transfer.folio}</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Creada por {transfer.user.name} · {new Date(transfer.created_at).toLocaleString('es-MX')}
                        </p>
                    </div>
                    <span className={`rounded-full border px-3 py-1 text-sm font-semibold ${statusColors[transfer.status]}`}>
                        {statusLabels[transfer.status]}
                    </span>
                </div>

                {/* Stores */}
                <div className="rounded-lg border bg-white p-4 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex-1 rounded-lg bg-gray-50 p-3 text-center">
                            <p className="text-xs text-gray-400 uppercase mb-1">Origen</p>
                            <p className="font-semibold">{transfer.from_store.name}</p>
                        </div>
                        <ArrowRight className="h-6 w-6 text-gray-400 shrink-0" />
                        <div className="flex-1 rounded-lg bg-blue-50 p-3 text-center">
                            <p className="text-xs text-blue-400 uppercase mb-1">Destino</p>
                            <p className="font-semibold text-blue-700">{transfer.to_store.name}</p>
                        </div>
                    </div>
                    {transfer.notes && (
                        <p className="mt-3 text-sm text-gray-600 border-t pt-3">
                            <span className="font-medium">Notas: </span>{transfer.notes}
                        </p>
                    )}
                    {transfer.completed_at && (
                        <p className="mt-2 text-xs text-green-600">
                            Completada: {new Date(transfer.completed_at).toLocaleString('es-MX')}
                        </p>
                    )}
                    {transfer.cancelled_at && (
                        <p className="mt-2 text-xs text-red-600">
                            Cancelada: {new Date(transfer.cancelled_at).toLocaleString('es-MX')}
                        </p>
                    )}
                </div>

                {/* Items */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Productos a transferir</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3">SKU</th>
                                <th className="px-4 py-3 text-right">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {transfer.items.map((item) => (
                                <tr key={item.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{item.product.name}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-gray-400">
                                        {item.product.sku ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold">{item.quantity}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Actions */}
                {transfer.status === 'pending' && (
                    <div className="flex gap-3">
                        <Button onClick={complete} className="bg-green-600 hover:bg-green-700">
                            <CheckCircle className="mr-2 h-4 w-4" /> Completar transferencia
                        </Button>
                        <Button variant="outline" onClick={cancel} className="border-red-300 text-red-600 hover:bg-red-50">
                            <XCircle className="mr-2 h-4 w-4" /> Cancelar
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
