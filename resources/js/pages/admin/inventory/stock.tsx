import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeftRight, History, Search, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';

/**
 * Una fila por producto **visto desde una tienda**. Aparece todo el catálogo,
 * también lo que esta tienda nunca ha tenido: eso sale con 0, que no es lo mismo
 * que no existir.
 */
interface StockRow {
    id: number;
    name: string;
    sku: string | null;
    barcode: string | null;
    unit: string | null;
    category: string | null;
    price: number;
    stock_tienda: number;
    stock_total: number;
    min_efectivo: number;
    min_propio: number | null;
    track_inventory: boolean;
}

interface Resumen {
    productos: number;
    con_stock: number;
    sin_stock: number;
    sin_control: number;
    bajo_minimo: number;
    unidades: number;
    valor: number;
}

interface Filters {
    store_id?: number | null;
    search?: string;
    category_id?: number | null;
    estado?: string;
}

interface Props {
    rows: PaginatedData<StockRow>;
    resumen: Resumen | null;
    stores: { id: number; name: string }[];
    categories: { id: number; name: string }[];
    filters: Filters;
}

/** Qué le pasa a esta fila en esta tienda. El orden importa: lo peor manda. */
function estadoDe(r: StockRow): { label: string; clase: string } {
    if (!r.track_inventory) return { label: 'Sin control', clase: 'bg-neutral-100 text-neutral-500' };
    if (r.stock_tienda <= 0) return { label: 'Sin stock', clase: 'bg-red-100 text-red-700' };
    if (r.min_efectivo > 0 && r.stock_tienda <= r.min_efectivo) {
        return { label: 'Bajo mínimo', clase: 'bg-amber-100 text-amber-700' };
    }
    return { label: 'Normal', clase: 'bg-green-100 text-green-700' };
}

export default function InventoryStock({ rows, resumen, stores, categories, filters }: Props) {
    const [f, setF] = useState<Filters>(filters ?? {});
    const [ajustando, setAjustando] = useState<StockRow | null>(null);
    const [minimo, setMinimo] = useState<StockRow | null>(null);

    const storeId = f.store_id ?? stores[0]?.id ?? null;
    const tienda = stores.find((s) => s.id === Number(storeId));

    const ajuste = useForm({ product_id: '', store_id: '', new_stock: '', reason: '' });
    const min = useForm({ product_id: '', store_id: '', min_stock: '' });

    const aplicar = (siguiente: Filters) => {
        setF(siguiente);
        const limpio = Object.fromEntries(
            Object.entries(siguiente).filter(([, v]) => v !== '' && v != null),
        );
        router.get('/admin/inventory/stock', limpio, { preserveState: true, preserveScroll: true });
    };

    const abrirAjuste = (r: StockRow) => {
        setAjustando(r);
        ajuste.setData({
            product_id: String(r.id),
            store_id: String(storeId),
            new_stock: String(r.stock_tienda),
            reason: '',
        });
    };

    const abrirMinimo = (r: StockRow) => {
        setMinimo(r);
        min.setData({
            product_id: String(r.id),
            store_id: String(storeId),
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
        // Vacío significa "sin criterio propio": vuelve a regir el mínimo general.
        router.post(
            '/admin/inventory/min-stock',
            {
                product_id: min.data.product_id,
                store_id: min.data.store_id,
                min_stock: min.data.min_stock === '' ? null : min.data.min_stock,
            },
            { preserveScroll: true, onSuccess: () => setMinimo(null) },
        );
    };

    const bs = (v: number) => `Bs ${v.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Inventario', href: '/admin/inventory' },
                { title: 'Existencias por tienda', href: '' },
            ]}
        >
            <FlashMessage />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold">Existencias por tienda</h1>
                        <p className="text-sm text-neutral-500">
                            Todo el catálogo visto desde una tienda. Lo que nunca ha entrado aquí aparece con 0,
                            no desaparece.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/admin/inventory">
                            <Button variant="outline" className="gap-2">
                                <History className="h-4 w-4" /> Movimientos
                            </Button>
                        </Link>
                        <Link href="/admin/stock-transfers">
                            <Button variant="outline" className="gap-2">
                                <ArrowLeftRight className="h-4 w-4" /> Transferencias
                            </Button>
                        </Link>
                    </div>
                </div>

                {stores.length === 0 ? (
                    <div className="rounded-lg border bg-white p-10 text-center text-neutral-500 dark:bg-neutral-900">
                        No hay tiendas activas.
                    </div>
                ) : (
                    <>
                        {/* ── Filtros ────────────────────────────────────────── */}
                        <div className="rounded-lg border bg-white p-4 shadow-sm dark:bg-neutral-900">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                                <div>
                                    <Label className="text-xs">Tienda</Label>
                                    <select
                                        className="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:bg-neutral-800"
                                        value={storeId ?? ''}
                                        onChange={(e) => aplicar({ ...f, store_id: Number(e.target.value) })}
                                    >
                                        {stores.map((s) => (
                                            <option key={s.id} value={s.id}>{s.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <Label className="text-xs">Buscar</Label>
                                    <div className="relative mt-1">
                                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-neutral-400" />
                                        <Input
                                            className="pl-8"
                                            placeholder="Nombre, SKU o código de barras…"
                                            defaultValue={f.search ?? ''}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    aplicar({ ...f, search: (e.target as HTMLInputElement).value });
                                                }
                                            }}
                                        />
                                    </div>
                                </div>
                                <div>
                                    <Label className="text-xs">Categoría</Label>
                                    <select
                                        className="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:bg-neutral-800"
                                        value={f.category_id ?? ''}
                                        onChange={(e) =>
                                            aplicar({ ...f, category_id: e.target.value === '' ? null : Number(e.target.value) })
                                        }
                                    >
                                        <option value="">Todas</option>
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <Label className="text-xs">Estado</Label>
                                    <select
                                        className="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:bg-neutral-800"
                                        value={f.estado ?? ''}
                                        onChange={(e) => aplicar({ ...f, estado: e.target.value })}
                                    >
                                        <option value="">Todos</option>
                                        <option value="bajo">Bajo mínimo</option>
                                        <option value="sin_stock">Sin stock</option>
                                        <option value="con_stock">Con stock</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {/* ── Resumen ────────────────────────────────────────── */}
                        {resumen && (
                            <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                                {[
                                    { t: 'Productos', v: String(resumen.productos), sub: `${resumen.sin_control} sin control` },
                                    { t: 'Con stock', v: String(resumen.con_stock), sub: `${resumen.sin_stock} sin stock`, c: 'text-green-600' },
                                    { t: 'Bajo mínimo', v: String(resumen.bajo_minimo), sub: 'requieren reposición', c: resumen.bajo_minimo > 0 ? 'text-amber-600' : undefined },
                                    { t: 'Unidades', v: resumen.unidades.toLocaleString('es-BO'), sub: tienda?.name ?? '' },
                                    { t: 'Valor a costo', v: bs(resumen.valor), sub: 'lo que cuesta reponerlo' },
                                ].map((k) => (
                                    <div key={k.t} className="rounded-lg border bg-white p-4 shadow-sm dark:bg-neutral-900">
                                        <p className="text-xs text-neutral-500">{k.t}</p>
                                        <p className={`text-xl font-bold ${k.c ?? ''}`}>{k.v}</p>
                                        <p className="text-xs text-neutral-400">{k.sub}</p>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* ── Tabla ──────────────────────────────────────────── */}
                        <div className="overflow-hidden rounded-lg border bg-white shadow-sm dark:bg-neutral-900">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-800">
                                        <tr>
                                            <th className="px-4 py-3 text-left">Producto</th>
                                            <th className="px-4 py-3 text-left">Categoría</th>
                                            <th className="px-4 py-3 text-right">Stock aquí</th>
                                            <th className="px-4 py-3 text-right">Mínimo</th>
                                            <th className="px-4 py-3 text-right" title="Suma de todas las tiendas">
                                                Total empresa
                                            </th>
                                            <th className="px-4 py-3 text-center">Estado</th>
                                            <th className="px-4 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {rows.data.map((r) => {
                                            const estado = estadoDe(r);
                                            // Hay existencias en otra tienda: se resuelve transfiriendo, no comprando.
                                            const enOtras = r.stock_total - r.stock_tienda;

                                            return (
                                                <tr key={r.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                                    <td className="px-4 py-2.5">
                                                        <p className="font-medium">{r.name}</p>
                                                        <p className="text-xs text-neutral-400">
                                                            {r.sku ?? 'sin SKU'}
                                                            {r.unit ? ` · ${r.unit}` : ''}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-2.5 text-neutral-500">{r.category ?? '—'}</td>
                                                    <td className="px-4 py-2.5 text-right">
                                                        {r.track_inventory ? (
                                                            <span className="text-base font-bold">{r.stock_tienda}</span>
                                                        ) : (
                                                            <span className="text-neutral-400">—</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-right">
                                                        {r.track_inventory ? (
                                                            <>
                                                                {r.min_efectivo}
                                                                {r.min_propio !== null && (
                                                                    <span
                                                                        className="ml-1 text-xs text-blue-500"
                                                                        title="Mínimo propio de esta tienda"
                                                                    >
                                                                        ●
                                                                    </span>
                                                                )}
                                                            </>
                                                        ) : (
                                                            <span className="text-neutral-400">—</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-right text-neutral-500">
                                                        {r.track_inventory ? (
                                                            <>
                                                                {r.stock_total}
                                                                {r.stock_tienda <= 0 && enOtras > 0 && (
                                                                    <p className="text-xs text-teal-600">
                                                                        {enOtras} en otras tiendas
                                                                    </p>
                                                                )}
                                                            </>
                                                        ) : (
                                                            <span className="text-neutral-400">—</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-center">
                                                        <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${estado.clase}`}>
                                                            {estado.label}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-2.5 text-right">
                                                        {r.track_inventory && (
                                                            <div className="flex justify-end gap-1">
                                                                <Button size="sm" variant="outline" onClick={() => abrirAjuste(r)}>
                                                                    Ajustar
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    title="Mínimo de esta tienda"
                                                                    onClick={() => abrirMinimo(r)}
                                                                >
                                                                    <SlidersHorizontal className="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        {rows.data.length === 0 && (
                                            <tr>
                                                <td colSpan={7} className="px-4 py-10 text-center text-neutral-400">
                                                    Ningún producto coincide con el filtro.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {rows.last_page > 1 && (
                                <div className="flex items-center justify-between border-t px-4 py-3">
                                    <p className="text-xs text-neutral-500">
                                        {rows.from}–{rows.to} de {rows.total}
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={rows.current_page <= 1}
                                            onClick={() => router.get('/admin/inventory/stock', { ...f, page: rows.current_page - 1 }, { preserveState: true })}
                                        >
                                            Anterior
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={rows.current_page >= rows.last_page}
                                            onClick={() => router.get('/admin/inventory/stock', { ...f, page: rows.current_page + 1 }, { preserveState: true })}
                                        >
                                            Siguiente
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </>
                )}
            </div>

            {/* ── Ajuste ─────────────────────────────────────────────────── */}
            {ajustando && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl dark:bg-neutral-900">
                        <h2 className="mb-1 text-lg font-bold">Ajustar existencias</h2>
                        <p className="mb-4 text-sm text-neutral-500">
                            {ajustando.name} — {tienda?.name} · actual: {ajustando.stock_tienda}
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
                            {minimo.name} — {tienda?.name}
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
                                    producto ({minimo.min_efectivo}).
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
