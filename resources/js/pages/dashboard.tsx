import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Boxes,
    CircleAlert,
    ClipboardList,
    FileWarning,
    LockOpen,
    Receipt,
    ShoppingCart,
    Wallet,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface Ventas {
    hoy_total: number;
    hoy_cantidad: number;
    ayer_total: number;
    variacion: number | null;
    ticket_promedio: number;
    serie: { fecha: string; total: number; cantidad: number }[];
}

interface Caja {
    abierto: boolean;
    sin_abrir_hoy: boolean;
    turno: {
        id: number;
        abierto_desde: string | null;
        caja: string | null;
        tienda: string | null;
        responsable: string | null;
        monto_apertura: number;
        vendido: number;
    } | null;
}

interface Inventario {
    bajo: number;
    agotados: number;
    productos: { id: number; nombre: string; sku: string | null; stock: number; min_stock: number }[];
}

interface Compras {
    pendientes: number;
    monto: number;
    atrasadas: number;
    ordenes: {
        id: number;
        folio: string;
        proveedor: string | null;
        total: number;
        estado: string;
        fecha_esperada: string | null;
        atrasada: boolean;
    }[];
}

interface Finanzas {
    por_cobrar_vencidas: number;
    por_cobrar_monto: number;
    por_pagar_vencidas: number;
    por_pagar_monto: number;
    vencen_pronto: number;
}

interface Siat {
    rechazadas: number;
    pendientes: number;
    enviadas_hoy: number;
    en_contingencia: number;
    ultimas_rechazadas: { id: number; numero: number; cliente: string; importe: number; error: string }[];
}

interface Props {
    puede: Record<'ventas' | 'caja' | 'inventario' | 'compras' | 'finanzas' | 'siat', boolean>;
    filtros: { store_id: number | null };
    tiendas: { id: number; name: string }[];
    ventas: Ventas | null;
    caja: Caja | null;
    inventario: Inventario | null;
    compras: Compras | null;
    finanzas: Finanzas | null;
    siat: Siat | null;
}

const bs = (n: number) =>
    `Bs ${n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/** "hace 6 h", "hace 20 min" — suficiente para saber si el turno lleva demasiado abierto. */
function desde(iso: string | null): string {
    if (!iso) return '—';
    const min = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
    if (min < 60) return `hace ${min} min`;
    const h = Math.floor(min / 60);
    return h < 24 ? `hace ${h} h` : `hace ${Math.floor(h / 24)} d`;
}

function Tarjeta({
    titulo,
    icono,
    href,
    children,
    tono = 'normal',
}: {
    titulo: string;
    icono: React.ReactNode;
    href?: string;
    children: React.ReactNode;
    tono?: 'normal' | 'alerta';
}) {
    const clases =
        tono === 'alerta'
            ? 'border-amber-300 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-950/30'
            : 'border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-neutral-900';

    const contenido = (
        <div className={`h-full rounded-xl border p-4 transition-shadow ${clases} ${href ? 'hover:shadow-md' : ''}`}>
            <div className="mb-2 flex items-center gap-2 text-xs font-medium tracking-wide text-neutral-500 uppercase dark:text-neutral-400">
                {icono}
                {titulo}
            </div>
            {children}
        </div>
    );

    return href ? (
        <Link href={href} className="block">
            {contenido}
        </Link>
    ) : (
        contenido
    );
}

/** Gráfico de barras de los últimos días, en SVG para no arrastrar una librería. */
function Serie({ datos }: { datos: Ventas['serie'] }) {
    const max = Math.max(...datos.map((d) => d.total), 1);
    const ancho = 100 / (datos.length * 1.5);

    return (
        <div>
            <svg viewBox="0 0 100 40" preserveAspectRatio="none" className="h-28 w-full" role="img" aria-label="Ventas por día">
                {datos.map((d, i) => {
                    const alto = (d.total / max) * 36;
                    return (
                        <rect
                            key={d.fecha}
                            x={i * (ancho * 1.5) + ancho * 0.25}
                            y={40 - alto}
                            width={ancho}
                            height={alto || 0.4}
                            rx={0.6}
                            className={d.total > 0 ? 'fill-indigo-500' : 'fill-neutral-300 dark:fill-neutral-700'}
                        >
                            <title>{`${d.fecha}: ${bs(d.total)} (${d.cantidad} ventas)`}</title>
                        </rect>
                    );
                })}
            </svg>
            <div className="mt-1 flex justify-between text-[10px] text-neutral-400">
                <span>{datos[0]?.fecha}</span>
                <span>{datos[datos.length - 1]?.fecha}</span>
            </div>
        </div>
    );
}

export default function Dashboard({ puede, filtros, tiendas, ventas, caja, inventario, compras, finanzas, siat }: Props) {
    const cambiarTienda = (valor: string) => {
        router.get('/dashboard', valor ? { store_id: valor } : {}, { preserveState: true, preserveScroll: true });
    };

    // Todo lo que merece que alguien haga algo hoy, en un solo sitio y ordenado
    // por urgencia: primero lo que ya falló, después lo que va a fallar.
    const alertas: { texto: string; href: string; grave: boolean }[] = [];

    if (siat && siat.rechazadas > 0)
        alertas.push({
            texto: `${siat.rechazadas} factura(s) rechazadas por el SIN`,
            href: '/admin/siat/invoices?estado=pendiente',
            grave: true,
        });
    if (siat && siat.en_contingencia > 0)
        alertas.push({
            texto: `${siat.en_contingencia} factura(s) en contingencia sin declarar`,
            href: '/admin/siat/contingency',
            grave: true,
        });
    if (finanzas && finanzas.por_cobrar_vencidas > 0)
        alertas.push({
            texto: `${finanzas.por_cobrar_vencidas} cuenta(s) por cobrar vencidas — ${bs(finanzas.por_cobrar_monto)}`,
            href: '/admin/receivables',
            grave: true,
        });
    if (finanzas && finanzas.por_pagar_vencidas > 0)
        alertas.push({
            texto: `${finanzas.por_pagar_vencidas} cuenta(s) por pagar vencidas — ${bs(finanzas.por_pagar_monto)}`,
            href: '/admin/payables',
            grave: true,
        });
    if (inventario && inventario.agotados > 0)
        alertas.push({ texto: `${inventario.agotados} producto(s) agotados`, href: '/admin/inventory', grave: true });
    if (compras && compras.atrasadas > 0)
        alertas.push({
            texto: `${compras.atrasadas} orden(es) de compra atrasadas`,
            href: '/admin/purchase-orders',
            grave: true,
        });
    if (inventario && inventario.bajo > inventario.agotados)
        alertas.push({
            texto: `${inventario.bajo - inventario.agotados} producto(s) bajo el mínimo`,
            href: '/admin/inventory',
            grave: false,
        });
    if (finanzas && finanzas.vencen_pronto > 0)
        alertas.push({
            texto: `${finanzas.vencen_pronto} cobro(s) vencen esta semana`,
            href: '/admin/receivables',
            grave: false,
        });
    if (siat && siat.pendientes > 0)
        alertas.push({
            texto: `${siat.pendientes} factura(s) pendientes de envío`,
            href: '/admin/siat/invoices?estado=pendiente',
            grave: false,
        });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold">Resumen de hoy</h1>
                        <p className="text-sm text-neutral-500">
                            {new Date().toLocaleDateString('es-BO', {
                                weekday: 'long',
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                            })}
                        </p>
                    </div>

                    {tiendas.length > 1 && (
                        <select
                            value={filtros.store_id ?? ''}
                            onChange={(e) => cambiarTienda(e.target.value)}
                            aria-label="Filtrar por tienda"
                            className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                        >
                            <option value="">Todas las tiendas</option>
                            {tiendas.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name}
                                </option>
                            ))}
                        </select>
                    )}
                </div>

                {/* ── Fila de indicadores ─────────────────────────────────── */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {ventas && (
                        <Tarjeta titulo="Ventas de hoy" icono={<ShoppingCart className="size-3.5" />} href="/admin/sales">
                            <p className="text-2xl font-bold">{bs(ventas.hoy_total)}</p>
                            <p className="text-sm text-neutral-500">
                                {ventas.hoy_cantidad} venta(s) · ticket {bs(ventas.ticket_promedio)}
                            </p>
                            {ventas.variacion !== null ? (
                                <p
                                    className={`mt-1 flex items-center gap-1 text-xs font-medium ${
                                        ventas.variacion >= 0 ? 'text-green-600' : 'text-red-600'
                                    }`}
                                >
                                    {ventas.variacion >= 0 ? (
                                        <ArrowUpRight className="size-3.5" />
                                    ) : (
                                        <ArrowDownRight className="size-3.5" />
                                    )}
                                    {Math.abs(ventas.variacion)}% vs. ayer
                                </p>
                            ) : (
                                <p className="mt-1 text-xs text-neutral-400">Sin ventas ayer para comparar</p>
                            )}
                        </Tarjeta>
                    )}

                    {caja && (
                        <Tarjeta
                            titulo="Caja"
                            icono={<Wallet className="size-3.5" />}
                            tono={caja.abierto ? 'normal' : 'alerta'}
                            href={caja.abierto && caja.turno ? `/admin/cash-shifts/${caja.turno.id}` : '/admin/cash-shifts/create'}
                        >
                            {caja.abierto && caja.turno ? (
                                <>
                                    <p className="text-2xl font-bold text-green-600">Abierta</p>
                                    <p className="text-sm text-neutral-500">
                                        {caja.turno.caja}
                                        {caja.turno.tienda ? ` · ${caja.turno.tienda}` : ''}
                                    </p>
                                    <p className="mt-1 text-xs text-neutral-400">
                                        {caja.turno.responsable} · {desde(caja.turno.abierto_desde)} · vendido{' '}
                                        {bs(caja.turno.vendido)}
                                    </p>
                                </>
                            ) : (
                                <>
                                    <p className="flex items-center gap-2 text-2xl font-bold text-amber-600">
                                        <LockOpen className="size-5" /> Cerrada
                                    </p>
                                    <p className="text-sm text-neutral-500">No hay ningún turno abierto</p>
                                    <p className="mt-1 text-xs text-amber-700 dark:text-amber-500">
                                        Sin turno abierto no se pueden registrar ventas
                                    </p>
                                </>
                            )}
                        </Tarjeta>
                    )}

                    {siat && (
                        <Tarjeta
                            titulo="Facturación SIAT"
                            icono={<Receipt className="size-3.5" />}
                            tono={siat.rechazadas > 0 ? 'alerta' : 'normal'}
                            href="/admin/siat/invoices"
                        >
                            <p className="text-2xl font-bold">{siat.enviadas_hoy}</p>
                            <p className="text-sm text-neutral-500">enviadas hoy</p>
                            <p className="mt-1 text-xs">
                                {siat.rechazadas > 0 ? (
                                    <span className="font-medium text-red-600">{siat.rechazadas} rechazadas</span>
                                ) : (
                                    <span className="text-neutral-400">Sin rechazos</span>
                                )}
                                {siat.pendientes > 0 && (
                                    <span className="text-neutral-500"> · {siat.pendientes} pendientes</span>
                                )}
                            </p>
                        </Tarjeta>
                    )}

                    {inventario && (
                        <Tarjeta
                            titulo="Inventario"
                            icono={<Boxes className="size-3.5" />}
                            tono={inventario.agotados > 0 ? 'alerta' : 'normal'}
                            href="/admin/inventory"
                        >
                            <p className="text-2xl font-bold">{inventario.bajo}</p>
                            <p className="text-sm text-neutral-500">producto(s) bajo el mínimo</p>
                            <p className="mt-1 text-xs">
                                {inventario.agotados > 0 ? (
                                    <span className="font-medium text-red-600">{inventario.agotados} agotados</span>
                                ) : (
                                    <span className="text-neutral-400">Ninguno agotado</span>
                                )}
                            </p>
                        </Tarjeta>
                    )}

                    {compras && (
                        <Tarjeta
                            titulo="Órdenes de compra"
                            icono={<ClipboardList className="size-3.5" />}
                            tono={compras.atrasadas > 0 ? 'alerta' : 'normal'}
                            href="/admin/purchase-orders"
                        >
                            <p className="text-2xl font-bold">{compras.pendientes}</p>
                            <p className="text-sm text-neutral-500">por recibir · {bs(compras.monto)}</p>
                            <p className="mt-1 text-xs">
                                {compras.atrasadas > 0 ? (
                                    <span className="font-medium text-red-600">{compras.atrasadas} atrasadas</span>
                                ) : (
                                    <span className="text-neutral-400">Ninguna atrasada</span>
                                )}
                            </p>
                        </Tarjeta>
                    )}
                </div>

                {/* ── Atención + gráfico ──────────────────────────────────── */}
                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border bg-white p-4 dark:bg-neutral-900">
                        <h2 className="mb-3 flex items-center gap-2 font-semibold">
                            <AlertTriangle className="size-4 text-amber-500" />
                            Requiere atención
                        </h2>

                        {alertas.length === 0 ? (
                            <p className="py-8 text-center text-sm text-neutral-500">
                                Nada pendiente. Todo en orden por ahora.
                            </p>
                        ) : (
                            <ul className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                {alertas.map((a) => (
                                    <li key={a.texto}>
                                        <Link
                                            href={a.href}
                                            className="flex items-center gap-2 py-2 text-sm hover:text-indigo-600"
                                        >
                                            <CircleAlert
                                                className={`size-4 shrink-0 ${a.grave ? 'text-red-500' : 'text-amber-500'}`}
                                            />
                                            <span>{a.texto}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {siat && siat.ultimas_rechazadas.length > 0 && (
                            <div className="mt-4 border-t border-neutral-100 pt-3 dark:border-neutral-800">
                                <p className="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500 uppercase">
                                    <FileWarning className="size-3.5" /> Últimos rechazos del SIN
                                </p>
                                <ul className="space-y-1.5">
                                    {siat.ultimas_rechazadas.map((f) => (
                                        <li key={f.id} className="text-xs">
                                            <Link href={`/admin/siat/invoices/${f.id}`} className="hover:text-indigo-600">
                                                <span className="font-medium">#{f.numero}</span> {f.cliente} ·{' '}
                                                {bs(f.importe)}
                                                <span className="block text-neutral-500">{f.error}</span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>

                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border bg-white p-4 dark:bg-neutral-900">
                        {ventas ? (
                            <>
                                <h2 className="mb-3 font-semibold">Ventas de los últimos 14 días</h2>
                                <Serie datos={ventas.serie} />

                                {inventario && inventario.productos.length > 0 && (
                                    <div className="mt-4 border-t border-neutral-100 pt-3 dark:border-neutral-800">
                                        <p className="mb-2 text-xs font-medium text-neutral-500 uppercase">
                                            Reponer primero
                                        </p>
                                        <ul className="space-y-1">
                                            {inventario.productos.slice(0, 5).map((p) => (
                                                <li key={p.id} className="flex justify-between text-xs">
                                                    <span className="truncate pr-2">{p.nombre}</span>
                                                    <span
                                                        className={
                                                            p.stock <= 0 ? 'font-medium text-red-600' : 'text-amber-600'
                                                        }
                                                    >
                                                        {p.stock} / {p.min_stock}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </>
                        ) : (
                            <p className="py-8 text-center text-sm text-neutral-500">
                                No tienes permiso para ver los datos de ventas.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
