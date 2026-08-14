import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeftRight, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';

interface Movement {
    id: number;
    type: string;
    quantity: string;
    stock_before: number;
    stock_after: number;
    reason: string | null;
    created_at: string;
    product: { id: number; name: string; sku: string | null };
    user: { name: string } | null;
    store: { id: number; name: string } | null;
}

/** Una fila por par tienda-producto: el mismo producto puede estar bajo en dos tiendas. */
interface LowStockRow {
    id: number;
    product_id: number;
    name: string;
    sku: string | null;
    store_id: number;
    store_name: string;
    stock: number;
    min_stock: number;
    min_propio: number | null;
}

interface Filters {
    store_id?: number | string;
    product_id?: number | string;
    type?: string;
    from?: string;
    to?: string;
}

interface Props {
    movements: PaginatedData<Movement>;
    lowStock: LowStockRow[];
    stores: { id: number; name: string }[];
    products: { id: number; name: string; sku: string | null }[];
    filters: Filters;
}

const typeColors: Record<string, string> = {
    in: 'bg-green-100 text-green-700',
    out: 'bg-red-100 text-red-700',
    adjustment: 'bg-blue-100 text-blue-700',
    return: 'bg-purple-100 text-purple-700',
    transfer_in: 'bg-teal-100 text-teal-700',
    transfer_out: 'bg-orange-100 text-orange-700',
};

const typeLabels: Record<string, string> = {
    in: 'Entrada',
    out: 'Salida',
    adjustment: 'Ajuste',
    return: 'Devolución',
    transfer_in: 'Recibido',
    transfer_out: 'Enviado',
};

export default function InventoryIndex({ movements, lowStock, stores, products, filters }: Props) {
    const [ajustando, setAjustando] = useState<LowStockRow | null>(null);
    const [minimo, setMinimo] = useState<LowStockRow | null>(null);
    const [f, setF] = useState<Filters>(filters ?? {});

    const ajuste = useForm({ product_id: '', store_id: '', new_stock: '', reason: '' });
    const min = useForm<{ product_id: string; store_id: string; min_stock: string }>({
        product_id: '',
        store_id: '',
        min_stock: '',
    });

    const aplicarFiltros = (siguiente: Filters) => {
        setF(siguiente);
        const limpio = Object.fromEntries(Object.entries(siguiente).filter(([, v]) => v !== '' && v != null));
        router.get('/admin/inventory', limpio, { preserveState: true, preserveScroll: true });
    };

    const abrirAjuste = (r: LowStockRow) => {
        setAjustando(r);
        ajuste.setData({
            product_id: String(r.product_id),
            store_id: String(r.store_id),
            new_stock: String(r.stock),
            reason: '',
        });
    };

    const abrirMinimo = (r: LowStockRow) => {
        setMinimo(r);
        min.setData({
            product_id: String(r.product_id),
            store_id: String(r.store_id),
            min_stock: r.min_propio === null ? '' : String(r.min_propio),
        });
    };

    const guardarAjuste = (e: React.FormEvent) => {
        e.preventDefault();
        ajuste.post('/admin/inventory/adjust', {
            preserveScroll: true,
            onSuccess: () => {
                setAjustando(null);
                ajuste.reset();
            },
        });
    };

    const guardarMinimo = (e: React.FormEvent) => {
        e.preventDefault();
        // Vacío significa "sin criterio propio": se manda null para volver al general.
        router.post(
            '/admin/inventory/min-stock',
            {
                product_id: min.data.product_id,
                store_id: min.data.store_id,
                min_stock: min.data.min_stock === '' ? null : min.data.min_stock,
            },
            {
                preserveScroll: true,
                onSuccess: () => setMinimo(null),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Inventario', href: '/admin/inventory' }]}>
            <FlashMessage />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold">Inventario</h1>
                        <p className="text-sm text-neutral-500">
                            Las existencias son por tienda. El total del producto es la suma de todas.
                        </p>
                    </div>
                    <Link href="/admin/stock-transfers">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeftRight className="h-4 w-4" /> Transferencias
                        </Button>
                    </Link>
                </div>

                {lowStock.length > 0 && (
                    <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-900/60 dark:bg-amber-950/30">
                        <div className="mb-3 flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-amber-600" />
                            <h2 className="font-semibold text-amber-800 dark:text-amber-300">
                                Bajo el mínimo ({lowStock.length})
                            </h2>
                        </div>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {lowStock.map((r) => (
                                <div
                                    key={`${r.store_id}-${r.product_id}`}
                                    className="rounded-lg border bg-white p-3 shadow-sm dark:border-neutral-700 dark:bg-neutral-900"
                                >
                                    <p className="text-sm font-medium">{r.name}</p>
                                    <p className="text-xs text-neutral-500">{r.store_name}</p>
                                    <p className={`mt-1 text-xs ${r.stock <= 0 ? 'font-semibold text-red-600' : 'text-amber-700'}`}>
                                        Stock: {r.stock} / Mín: {r.min_stock}
                                        {r.min_propio !== null && <span className="text-neutral-400"> (propio)</span>}
                                    </p>
                                    <div className="mt-2 flex gap-1">
                                        <Button size="sm" variant="outline" className="flex-1 text-xs" onClick={() => abrirAjuste(r)}>
                                            Ajustar
                                        </Button>
                                        <Button size="sm" variant="ghost" className="px-2" title="Mínimo de esta tienda" onClick={() => abrirMinimo(r)}>
                                            <SlidersHorizontal className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* ── Historial ──────────────────────────────────────────── */}
                <div className="rounded-lg border bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <div className="border-b px-4 py-3 dark:border-neutral-700">
                        <p className="mb-3 font-semibold">Historial de movimientos</p>
                        <div className="grid grid-cols-2 gap-2 md:grid-cols-5">
                            <select
                                className="rounded-md border px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                value={f.store_id ?? ''}
                                onChange={(e) => aplicarFiltros({ ...f, store_id: e.target.value })}
                                aria-label="Tienda"
                            >
                                <option value="">Todas las tiendas</option>
                                {stores.map((s) => (
                                    <option key={s.id} value={s.id}>{s.name}</option>
                                ))}
                            </select>
                            <select
                                className="rounded-md border px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                value={f.product_id ?? ''}
                                onChange={(e) => aplicarFiltros({ ...f, product_id: e.target.value })}
                                aria-label="Producto"
                            >
                                <option value="">Todos los productos</option>
                                {products.map((p) => (
                                    <option key={p.id} value={p.id}>{p.name}</option>
                                ))}
                            </select>
                            <select
                                className="rounded-md border px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                value={f.type ?? ''}
                                onChange={(e) => aplicarFiltros({ ...f, type: e.target.value })}
                                aria-label="Tipo de movimiento"
                            >
                                <option value="">Todos los tipos</option>
                                {Object.entries(typeLabels).map(([k, v]) => (
                                    <option key={k} value={k}>{v}</option>
                                ))}
                            </select>
                            <Input
                                type="date"
                                value={f.from ?? ''}
                                onChange={(e) => aplicarFiltros({ ...f, from: e.target.value })}
                                aria-label="Desde"
                            />
                            <Input
                                type="date"
                                value={f.to ?? ''}
                                onChange={(e) => aplicarFiltros({ ...f, to: e.target.value })}
                                aria-label="Hasta"
                            />
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500 dark:border-neutral-700 dark:bg-neutral-800">
                                <tr>
                                    <th className="px-4 py-3">Producto</th>
                                    <th className="px-4 py-3">Tienda</th>
                                    <th className="px-4 py-3">Tipo</th>
                                    <th className="px-4 py-3 text-right">Cantidad</th>
                                    <th className="px-4 py-3 text-right">Antes</th>
                                    <th className="px-4 py-3 text-right">Después</th>
                                    <th className="px-4 py-3">Motivo</th>
                                    <th className="px-4 py-3">Usuario</th>
                                    <th className="px-4 py-3">Fecha</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y dark:divide-neutral-800">
                                {movements.data.map((m) => (
                                    <tr key={m.id} className="hover:bg-gray-50 dark:hover:bg-neutral-800/50">
                                        <td className="px-4 py-3">
                                            <p className="font-medium">{m.product.name}</p>
                                            {m.product.sku && <p className="font-mono text-xs text-gray-400">{m.product.sku}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-gray-500">{m.store?.name ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${typeColors[m.type] ?? 'bg-gray-100 text-gray-600'}`}>
                                                {typeLabels[m.type] ?? m.type}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium">{m.quantity}</td>
                                        <td className="px-4 py-3 text-right text-gray-500">{m.stock_before}</td>
                                        <td className="px-4 py-3 text-right font-medium">{m.stock_after}</td>
                                        <td className="px-4 py-3 text-gray-500">{m.reason ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-500">{m.user?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-xs text-gray-400">
                                            {new Date(m.created_at).toLocaleString('es-BO')}
                                        </td>
                                    </tr>
                                ))}
                                {movements.data.length === 0 && (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-8 text-center text-gray-400">
                                            Sin movimientos para este filtro.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {movements.last_page > 1 && (
                        <div className="flex items-center justify-between border-t px-4 py-3 text-sm dark:border-neutral-700">
                            <span className="text-neutral-500">
                                Página {movements.current_page} de {movements.last_page} · {movements.total} movimientos
                            </span>
                            <div className="flex gap-2">
                                {movements.current_page > 1 && (
                                    <Button variant="outline" size="sm" onClick={() => router.get('/admin/inventory', { ...f, page: movements.current_page - 1 }, { preserveState: true })}>
                                        Anterior
                                    </Button>
                                )}
                                {movements.current_page < movements.last_page && (
                                    <Button variant="outline" size="sm" onClick={() => router.get('/admin/inventory', { ...f, page: movements.current_page + 1 }, { preserveState: true })}>
                                        Siguiente
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* ── Ajuste ─────────────────────────────────────────────────── */}
            {ajustando && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl dark:bg-neutral-900">
                        <h2 className="mb-1 text-lg font-bold">Ajustar existencias</h2>
                        <p className="mb-4 text-sm text-neutral-500">
                            {ajustando.name} — {ajustando.store_name} · actual: {ajustando.stock}
                        </p>
                        <form onSubmit={guardarAjuste} className="space-y-4">
                            <div>
                                <Label>Nuevo stock *</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={ajuste.data.new_stock}
                                    onChange={(e) => ajuste.setData('new_stock', e.target.value)}
                                />
                                {ajuste.errors.new_stock && <p className="mt-1 text-xs text-red-500">{ajuste.errors.new_stock}</p>}
                            </div>
                            <div>
                                <Label>Motivo *</Label>
                                <Input
                                    value={ajuste.data.reason}
                                    onChange={(e) => ajuste.setData('reason', e.target.value)}
                                    placeholder="Conteo físico, merma, rotura…"
                                />
                                {ajuste.errors.reason && <p className="mt-1 text-xs text-red-500">{ajuste.errors.reason}</p>}
                            </div>
                            {ajuste.errors.store_id && <p className="text-xs text-red-500">{ajuste.errors.store_id}</p>}
                            <div className="flex gap-2">
                                <Button type="submit" disabled={ajuste.processing}>Guardar ajuste</Button>
                                <Button type="button" variant="outline" onClick={() => setAjustando(null)}>Cancelar</Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ── Mínimo por tienda ──────────────────────────────────────── */}
            {minimo && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl dark:bg-neutral-900">
                        <h2 className="mb-1 text-lg font-bold">Mínimo de esta tienda</h2>
                        <p className="mb-4 text-sm text-neutral-500">
                            {minimo.name} — {minimo.store_name}
                        </p>
                        <form onSubmit={guardarMinimo} className="space-y-4">
                            <div>
                                <Label>Mínimo propio</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={min.data.min_stock}
                                    onChange={(e) => min.setData('min_stock', e.target.value)}
                                    placeholder="Vacío = usar el general"
                                />
                                <p className="mt-1 text-xs text-neutral-500">
                                    Déjalo vacío para que esta tienda vuelva a regirse por el mínimo general del
                                    producto.
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit">Guardar</Button>
                                <Button type="button" variant="outline" onClick={() => setMinimo(null)}>Cancelar</Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
