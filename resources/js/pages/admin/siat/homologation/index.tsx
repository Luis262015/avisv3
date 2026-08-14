import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, Check, RefreshCw, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

interface CatalogEntry {
    codigo: number;
    descripcion: string;
}

interface Product {
    id: number;
    name: string;
    sku: string | null;
    barcode: string | null;
    unit: string | null;
    codigo_producto_sin: number | null;
    unidad_medida_sin: number | null;
    tipo_codigo_anexo: number | null;
}

interface Props {
    products: PaginatedData<Product>;
    catalogo: { productos: CatalogEntry[]; unidades: CatalogEntry[]; error: string | null };
    setting: { id: number; actividad_economica: string; actividad_descripcion: string | null; ambiente: string } | null;
    settings: { id: number; label: string }[];
    /** Paramétrica del SIN: 1 = Nº de serie, 2 = IMEI. */
    tiposAnexo: { value: number; label: string }[];
    stats: { total: number; homologados: number; con_anexo: number };
    filters: { search?: string; estado?: string };
}

/**
 * Selector con búsqueda para la lista de Productos y Servicios del SIN, que llega
 * con cientos de entradas: un <select> nativo obliga a recorrerlas a ojo.
 */
function CodigoSinSelect({
    value,
    options,
    onChange,
    disabled,
}: {
    value: number | null;
    options: CatalogEntry[];
    onChange: (codigo: number | null) => void;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const box = useRef<HTMLDivElement>(null);

    const selected = options.find((o) => o.codigo === value) ?? null;

    const matches = useMemo(() => {
        const q = query.trim().toLowerCase();
        const base = q
            ? options.filter((o) => o.descripcion.toLowerCase().includes(q) || String(o.codigo).includes(q))
            : options;

        return base.slice(0, 60);
    }, [options, query]);

    useEffect(() => {
        if (!open) return;

        const close = (e: MouseEvent) => {
            if (box.current && !box.current.contains(e.target as Node)) setOpen(false);
        };

        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    // Sin catálogo (SIN caído o ambiente simulado) queda un campo numérico simple:
    // vale más poder escribir el código a mano que dejar la pantalla inservible.
    if (options.length === 0) {
        return (
            <Input
                type="number"
                className="h-8 w-full text-sm"
                placeholder="Código SIN"
                value={value ?? ''}
                disabled={disabled}
                onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
            />
        );
    }

    return (
        <div className="relative" ref={box}>
            <button
                type="button"
                disabled={disabled}
                onClick={() => { setOpen((v) => !v); setQuery(''); }}
                className="w-full truncate rounded-md border px-2 py-1.5 text-left text-sm hover:bg-gray-50 disabled:opacity-50"
                title={selected ? `${selected.codigo} — ${selected.descripcion}` : undefined}
            >
                {selected
                    ? <span><span className="font-mono text-xs text-gray-500">{selected.codigo}</span> {selected.descripcion}</span>
                    : <span className="text-gray-400">Sin homologar…</span>}
            </button>

            {open && (
                <div className="absolute z-20 mt-1 w-[26rem] max-w-[80vw] rounded-md border bg-white shadow-lg">
                    <div className="border-b p-2">
                        <Input
                            autoFocus
                            className="h-8 text-sm"
                            placeholder="Buscar en la paramétrica del SIN…"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                    </div>
                    <ul className="max-h-64 overflow-y-auto py-1 text-sm">
                        {matches.map((o) => (
                            <li key={o.codigo}>
                                <button
                                    type="button"
                                    onClick={() => { onChange(o.codigo); setOpen(false); }}
                                    className={`block w-full px-3 py-1.5 text-left hover:bg-blue-50 ${o.codigo === value ? 'bg-blue-50 font-medium' : ''}`}
                                >
                                    <span className="font-mono text-xs text-gray-500">{o.codigo}</span> {o.descripcion}
                                </button>
                            </li>
                        ))}
                        {matches.length === 0 && (
                            <li className="px-3 py-4 text-center text-xs text-gray-400">Sin coincidencias.</li>
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}

function UnidadSelect({
    value,
    options,
    onChange,
    disabled,
}: {
    value: number | null;
    options: CatalogEntry[];
    onChange: (codigo: number | null) => void;
    disabled?: boolean;
}) {
    if (options.length === 0) {
        return (
            <Input
                type="number"
                className="h-8 w-28 text-sm"
                placeholder="Unidad"
                value={value ?? ''}
                disabled={disabled}
                onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
            />
        );
    }

    return (
        <select
            className="w-full rounded-md border px-2 py-1.5 text-sm disabled:opacity-50"
            value={value ?? ''}
            disabled={disabled}
            onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
        >
            <option value="">Sin unidad…</option>
            {options.map((o) => (
                <option key={o.codigo} value={o.codigo}>{o.codigo} — {o.descripcion}</option>
            ))}
        </select>
    );
}

function ProductRow({
    product,
    catalogo,
    tiposAnexo,
    settingId,
    selected,
    onToggle,
}: {
    product: Product;
    catalogo: Props['catalogo'];
    tiposAnexo: Props['tiposAnexo'];
    settingId: number | null;
    selected: boolean;
    onToggle: (id: number) => void;
}) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        setting_id: settingId,
        codigo_producto_sin: product.codigo_producto_sin,
        unidad_medida_sin: product.unidad_medida_sin,
        tipo_codigo_anexo: product.tipo_codigo_anexo,
    });

    const homologado = product.codigo_producto_sin !== null && product.unidad_medida_sin !== null;
    const error =
        errors.codigo_producto_sin ??
        errors.unidad_medida_sin ??
        errors.tipo_codigo_anexo ??
        (errors as Record<string, string>).setting_id;

    return (
        <tr className="align-top hover:bg-gray-50">
            <td className="px-3 py-2">
                <input
                    type="checkbox"
                    className="mt-1.5 h-4 w-4 rounded border-gray-300"
                    checked={selected}
                    onChange={() => onToggle(product.id)}
                />
            </td>
            <td className="px-3 py-2">
                <p className="font-medium">{product.name}</p>
                <p className="text-xs text-gray-400">
                    {product.sku ?? 'sin SKU'}
                    {product.unit ? ` · ${product.unit}` : ''}
                </p>
                {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
            </td>
            <td className="w-[26rem] px-3 py-2">
                <CodigoSinSelect
                    value={data.codigo_producto_sin}
                    options={catalogo.productos}
                    onChange={(v) => setData('codigo_producto_sin', v)}
                />
            </td>
            <td className="w-56 px-3 py-2">
                <UnidadSelect
                    value={data.unidad_medida_sin}
                    options={catalogo.unidades}
                    onChange={(v) => setData('unidad_medida_sin', v)}
                />
            </td>
            {/* Casi todo el catálogo va sin anexo; lo llevan los aparatos que el
                SIN quiere identificados uno a uno. */}
            <td className="w-40 px-3 py-2">
                <select
                    className="w-full rounded-md border px-2 py-1.5 text-sm"
                    value={data.tipo_codigo_anexo ?? ''}
                    onChange={(e) => setData('tipo_codigo_anexo', e.target.value === '' ? null : Number(e.target.value))}
                >
                    <option value="">No lleva</option>
                    {tiposAnexo.map((t) => (
                        <option key={t.value} value={t.value}>{t.label}</option>
                    ))}
                </select>
            </td>
            <td className="px-3 py-2 text-right">
                {isDirty ? (
                    <Button
                        size="sm"
                        disabled={processing}
                        onClick={() => put(`/admin/siat/homologation/${product.id}`, { preserveScroll: true })}
                    >
                        Guardar
                    </Button>
                ) : homologado ? (
                    <span className="inline-flex items-center gap-1 text-xs text-green-600">
                        <Check className="h-3.5 w-3.5" /> Homologado
                    </span>
                ) : (
                    <span className="text-xs text-amber-600">Pendiente</span>
                )}
            </td>
        </tr>
    );
}

function BulkBar({
    ids,
    catalogo,
    settingId,
    onDone,
}: {
    ids: number[];
    catalogo: Props['catalogo'];
    settingId: number | null;
    onDone: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm<{
        setting_id: number | null;
        product_ids: number[];
        codigo_producto_sin: number | null;
        unidad_medida_sin: number | null;
    }>({
        setting_id: settingId,
        product_ids: ids,
        codigo_producto_sin: null,
        unidad_medida_sin: null,
    });

    useEffect(() => setData('product_ids', ids), [ids]); // eslint-disable-line react-hooks/exhaustive-deps

    const listo = data.codigo_producto_sin !== null && data.unidad_medida_sin !== null;

    return (
        <div className="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
            <div className="flex flex-wrap items-center gap-3">
                <span className="text-sm font-medium text-blue-900">{ids.length} seleccionado(s):</span>
                <div className="min-w-72 flex-1">
                    <CodigoSinSelect
                        value={data.codigo_producto_sin}
                        options={catalogo.productos}
                        onChange={(v) => setData('codigo_producto_sin', v)}
                    />
                </div>
                <div className="w-64">
                    <UnidadSelect
                        value={data.unidad_medida_sin}
                        options={catalogo.unidades}
                        onChange={(v) => setData('unidad_medida_sin', v)}
                    />
                </div>
                <Button
                    size="sm"
                    disabled={!listo || processing}
                    onClick={() => post('/admin/siat/homologation/bulk', { preserveScroll: true, onSuccess: onDone })}
                >
                    Aplicar a {ids.length}
                </Button>
                <Button size="sm" variant="ghost" onClick={onDone}>Cancelar</Button>
            </div>
            {(errors.codigo_producto_sin || errors.unidad_medida_sin) && (
                <p className="mt-2 text-xs text-red-600">
                    {errors.codigo_producto_sin ?? errors.unidad_medida_sin}
                </p>
            )}
        </div>
    );
}

export default function SiatHomologationIndex({ products, catalogo, setting, settings, tiposAnexo, stats, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [estado, setEstado] = useState(filters.estado ?? '');
    const [selection, setSelection] = useState<number[]>([]);

    const settingId = setting?.id ?? null;
    const pendientes = stats.total - stats.homologados;

    const navigate = (extra: Record<string, unknown> = {}) => {
        router.get('/admin/siat/homologation',
            { search, estado, setting_id: settingId, ...extra },
            { preserveState: true, preserveScroll: true });
    };

    const toggle = (id: number) =>
        setSelection((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

    const allVisible = products.data.length > 0 && products.data.every((p) => selection.includes(p.id));

    return (
        <AppLayout breadcrumbs={[{ title: 'SIAT Bolivia', href: '' }, { title: 'Homologación de productos', href: '' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Homologación de productos</h1>
                        <p className="text-sm text-gray-500">
                            Cada producto que se facture necesita un código de la paramétrica del SIN y una unidad de medida.
                        </p>
                        {setting && (
                            <p className="mt-1 text-xs text-gray-400">
                                Actividad económica {setting.actividad_economica}
                                {setting.actividad_descripcion ? ` — ${setting.actividad_descripcion}` : ''}
                            </p>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        {settings.length > 1 && (
                            <select
                                className="rounded-md border px-3 py-2 text-sm"
                                value={settingId ?? ''}
                                onChange={(e) => navigate({ setting_id: Number(e.target.value) })}
                            >
                                {settings.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
                            </select>
                        )}
                        <Button
                            variant="outline"
                            className="gap-2"
                            disabled={!settingId}
                            onClick={() => router.post('/admin/siat/homologation/refresh',
                                { setting_id: settingId }, { preserveScroll: true })}
                        >
                            <RefreshCw className="h-4 w-4" /> Actualizar catálogos
                        </Button>
                    </div>
                </div>

                {/* Avance */}
                <div className="mb-4 rounded-lg border bg-white p-4 shadow-sm">
                    <div className="mb-2 flex items-center justify-between text-sm">
                        <span className="text-gray-600">
                            {stats.homologados} de {stats.total} productos homologados
                            {stats.con_anexo > 0 && (
                                <span className="text-gray-400"> · {stats.con_anexo} con anexo (serie/IMEI)</span>
                            )}
                        </span>
                        {pendientes > 0 && (
                            <span className="text-amber-600">{pendientes} pendiente(s) — no se pueden facturar</span>
                        )}
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-gray-100">
                        <div
                            className="h-full rounded-full bg-green-500 transition-all"
                            style={{ width: `${stats.total ? (stats.homologados / stats.total) * 100 : 0}%` }}
                        />
                    </div>
                </div>

                {catalogo.error && (
                    <div className="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                        <div className="text-sm text-amber-800">
                            <p className="font-medium">No se pudo cargar el catálogo del SIN</p>
                            <p className="text-xs">{catalogo.error}</p>
                        </div>
                    </div>
                )}

                {selection.length > 0 && (
                    <BulkBar
                        ids={selection}
                        catalogo={catalogo}
                        settingId={settingId}
                        onDone={() => setSelection([])}
                    />
                )}

                {/* Filtros */}
                <div className="mb-4 flex flex-wrap gap-3">
                    <div className="relative min-w-48 flex-1">
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                        <Input
                            className="pl-9"
                            placeholder="Nombre, SKU o código de barras…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && navigate()}
                        />
                    </div>
                    <select
                        className="rounded-md border px-3 py-2 text-sm"
                        value={estado}
                        onChange={(e) => { setEstado(e.target.value); navigate({ estado: e.target.value }); }}
                    >
                        <option value="">Todos</option>
                        <option value="pendientes">Solo pendientes</option>
                        <option value="homologados">Solo homologados</option>
                    </select>
                    <Button variant="outline" onClick={() => navigate()}>Filtrar</Button>
                </div>

                <div className="overflow-visible rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-3 py-3">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-gray-300"
                                        checked={allVisible}
                                        onChange={() => setSelection(allVisible ? [] : products.data.map((p) => p.id))}
                                    />
                                </th>
                                <th className="px-3 py-3 text-left">Producto</th>
                                <th className="px-3 py-3 text-left">Código Producto/Servicio SIN</th>
                                <th className="px-3 py-3 text-left">Unidad de medida</th>
                                <th className="px-3 py-3 text-left" title="Números de serie o IMEI que se declaran al SIN aparte de la factura">
                                    Anexo
                                </th>
                                <th className="px-3 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {products.data.map((p) => (
                                <ProductRow
                                    key={p.id}
                                    product={p}
                                    catalogo={catalogo}
                                    tiposAnexo={tiposAnexo}
                                    settingId={settingId}
                                    selected={selection.includes(p.id)}
                                    onToggle={toggle}
                                />
                            ))}
                            {products.data.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-400">Sin productos.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {products.last_page > 1 && (
                    <div className="mt-4 flex flex-wrap justify-center gap-2">
                        {Array.from({ length: products.last_page }, (_, i) => i + 1).map((page) => (
                            <button
                                key={page}
                                onClick={() => navigate({ page })}
                                className={`rounded px-3 py-1 text-sm ${page === products.current_page ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50'}`}
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
