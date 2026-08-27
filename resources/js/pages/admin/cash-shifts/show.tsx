import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Link, useForm } from '@inertiajs/react';
import { Lock, ShoppingCart, X } from 'lucide-react';
import { useState } from 'react';

interface Sale {
    id: number;
    folio: string;
    total: string;
    status: string;
    payment_method: string;
    created_at: string;
}

interface Expense {
    id: number;
    category: string;
    description: string;
    amount: string;
    payment_method: string;
    date: string;
}

interface Income {
    id: number;
    category: string;
    description: string;
    amount: string;
    payment_method: string;
    date: string;
}

interface Withdrawal {
    id: number;
    amount: string;
    reason: string;
    authorized_by: string | null;
    date: string;
}

interface Shift {
    id: number;
    status: 'open' | 'closed';
    opened_at: string;
    closed_at: string | null;
    opening_amount: string;
    closing_amount: string | null;
    expected_amount: string | null;
    difference: string | null;
    notes: string | null;
    user: { name: string };
    cash_register: { id: number; name: string; store: { name: string } };
    sales: Sale[];
    expenses: Expense[];
    incomes: Income[];
    withdrawals: Withdrawal[];
}

/** Desglose del efectivo, calculado en el servidor. */
interface Arqueo {
    ventas_efectivo: number;
    ventas_otros: number;
    ventas_mixtas: number;
    ingresos_efectivo: number;
    gastos_efectivo: number;
    retiros: number;
    esperado: number;
}

interface Props {
    shift: Shift;
    arqueo: Arqueo;
    totalSales: number;
    salesCount: number;
    salesByMethod: Record<string, number>;
    totalExpenses: number;
    expensesByMethod: Record<string, number>;
    totalIncomes: number;
    incomesByMethod: Record<string, number>;
    withdrawalsTotal: number;
}

const PM_LABEL: Record<string, string> = {
    cash: 'Efectivo',
    card: 'Tarjeta',
    transfer: 'Transferencia',
};

/** El negocio cobra en bolivianos; el formato lo pone el navegador, no una plantilla a mano. */
const bs = (v: string | number | null) =>
    v != null
        ? new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(Number(v))
        : '—';

/**
 * El arqueo, presentado como la cuenta que es.
 *
 * Antes el desglose era una fila de fichas que se envolvía —«Fondo: … + Ventas:
 * … − Gastos: …»—, así que no se leía como una operación que diera ese total, y
 * las líneas en cero se ocultaban: no había forma de saber si un concepto valía
 * cero o simplemente no se estaba contando. Aquí van todas, con su signo,
 * alineadas a la derecha y con la raya antes del resultado, que es como se
 * comprueba una cuenta.
 */
function DesgloseEfectivo({
    fondo,
    arqueo,
    total,
}: {
    fondo: string | number;
    arqueo: Arqueo;
    total: string | number | null;
}) {
    const lineas = [
        { etiqueta: 'Fondo de apertura', detalle: 'con lo que abriste el turno', valor: Number(fondo) },
        { etiqueta: 'Ventas cobradas en efectivo', detalle: null, valor: arqueo.ventas_efectivo },
        { etiqueta: 'Otros ingresos en efectivo', detalle: null, valor: arqueo.ingresos_efectivo },
        { etiqueta: 'Gastos pagados del cajón', detalle: null, valor: -arqueo.gastos_efectivo },
        { etiqueta: 'Retiros de efectivo', detalle: null, valor: -arqueo.retiros },
    ];

    return (
        <div className="text-sm">
            <dl className="space-y-1.5">
                {lineas.map((l) => {
                    const cero = l.valor === 0;

                    return (
                        <div key={l.etiqueta} className={`flex items-baseline gap-3 ${cero ? 'opacity-45' : ''}`}>
                            <dt className="min-w-0 flex-1">
                                {/* El signo va pegado a la etiqueta: dice qué hace el
                                    concepto, no si la cifra es negativa. */}
                                <span className="mr-1.5 inline-block w-3 font-mono">
                                    {l.valor < 0 ? '−' : '+'}
                                </span>
                                {l.etiqueta}
                                {l.detalle && <span className="ml-1.5 text-xs opacity-70">({l.detalle})</span>}
                            </dt>
                            <dd className="shrink-0 font-medium tabular-nums">{bs(Math.abs(l.valor))}</dd>
                        </div>
                    );
                })}
            </dl>

            <div className="mt-2.5 flex items-baseline gap-3 border-t pt-2.5 font-semibold">
                <dt className="min-w-0 flex-1">
                    <span className="mr-1.5 inline-block w-3 font-mono">=</span>
                    Debe haber en el cajón
                </dt>
                <dd className="shrink-0 tabular-nums">{bs(total)}</dd>
            </div>
        </div>
    );
}

/**
 * Lo que se cobró pero no está en el cajón.
 *
 * Iba suelto en dos párrafos debajo del total, y se leía como una advertencia
 * más que como parte del arqueo. Es justo lo que explica por qué «vendí 5.000»
 * y «en el cajón hay 1.200» no se contradicen.
 */
function FueraDelCajon({ arqueo }: { arqueo: Arqueo }) {
    if (arqueo.ventas_otros <= 0 && arqueo.ventas_mixtas <= 0) {
        return null;
    }

    return (
        <div className="mt-4 border-t pt-3">
            <p className="text-xs font-semibold tracking-wide uppercase opacity-70">No entra al cajón</p>

            <dl className="mt-1.5 space-y-1 text-sm">
                {arqueo.ventas_otros > 0 && (
                    <div className="flex items-baseline gap-3">
                        <dt className="min-w-0 flex-1">Ventas con tarjeta o transferencia</dt>
                        <dd className="shrink-0 tabular-nums">{bs(arqueo.ventas_otros)}</dd>
                    </div>
                )}
                {arqueo.ventas_mixtas > 0 && (
                    <div className="flex items-baseline gap-3">
                        <dt className="min-w-0 flex-1">Ventas con pago mixto</dt>
                        <dd className="shrink-0 tabular-nums">{bs(arqueo.ventas_mixtas)}</dd>
                    </div>
                )}
            </dl>

            {arqueo.ventas_mixtas > 0 && (
                <p className="mt-2 rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900">
                    <strong>Las ventas mixtas hay que revisarlas a mano.</strong> La venta no guarda cuánto de
                    ella se cobró en efectivo, así que esa parte no se puede sumar arriba. Compruébela antes de
                    cerrar o la diferencia saldrá sin ser un error de conteo.
                </p>
            )}
        </div>
    );
}

export default function CashShiftShow({
    shift,
    arqueo,
    totalSales,
    salesCount,
    salesByMethod,
    totalExpenses,
    expensesByMethod,
    totalIncomes,
    incomesByMethod,
    withdrawalsTotal,
}: Props) {
    const [showClose, setShowClose] = useState(false);
    const { data, setData, patch, processing, errors } = useForm({
        closing_amount: '',
        notes: shift.notes ?? '',
    });

    // La página entera venía en dólares; el negocio cobra en bolivianos.
    const fmt = bs;

    // Lo calcula el servidor: solo cuenta lo que pasó por el cajón. Recalcularlo
    // aquí es como se coló el fallo de contar las ventas con tarjeta como efectivo.
    const expectedAmount = arqueo.esperado;

    const closingNum = data.closing_amount !== '' ? parseFloat(data.closing_amount) : null;
    const liveDiff   = closingNum !== null ? closingNum - expectedAmount : null;

    /**
     * Lo que se guardó al cerrar frente a lo que sale rehaciendo la cuenta hoy.
     *
     * Los turnos cerrados antes de corregir el arqueo llevan guardado un esperado
     * que contaba las ventas con tarjeta como efectivo, así que arrastran un
     * faltante que nunca existió. Sin decirlo, el desglose de abajo contradiría al
     * número de arriba y el turno parecería descuadrado dos veces.
     */
    const esperadoGuardado = shift.expected_amount !== null ? parseFloat(shift.expected_amount) : null;

    const desfaseAlCerrar =
        esperadoGuardado !== null && Math.abs(esperadoGuardado - expectedAmount) >= 0.01
            ? esperadoGuardado - expectedAmount
            : null;

    // Si el desfase es justo lo cobrado fuera del cajón, la causa es esa y se puede decir.
    const desfaseCoincideConNoEfectivo =
        desfaseAlCerrar !== null && Math.abs(desfaseAlCerrar - arqueo.ventas_otros) < 0.01;

    /**
     * La diferencia se recalcula, no se lee de la columna.
     *
     * `shift.difference` se guardó con la cuenta de entonces. Mostrarlo junto a un
     * desglose que hoy suma otra cosa daría dos descuadres distintos en la misma
     * pantalla; el histórico se cuenta en la nota de abajo.
     */
    const contado = shift.closing_amount !== null ? parseFloat(shift.closing_amount) : null;
    const diferencia = contado !== null ? contado - expectedAmount : null;

    function handleClose(e: React.FormEvent) {
        e.preventDefault();
        patch(`/admin/cash-shifts/${shift.id}/close`);
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Turnos', href: '/admin/cash-shifts' },
                { title: `Turno #${shift.id}`, href: '' },
            ]}
        >
            <FlashMessage />
            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Turno #{shift.id}</h1>
                        <p className="text-gray-500">
                            {shift.cash_register.name} — {shift.cash_register.store.name}
                        </p>
                        <p className="text-xs text-gray-400">
                            {shift.user.name} · Abierto el {new Date(shift.opened_at).toLocaleString('es-MX')}
                            {shift.closed_at &&
                                ` · Cerrado el ${new Date(shift.closed_at).toLocaleString('es-MX')}`}
                        </p>
                    </div>
                    {shift.status === 'open' && (
                        <div className="flex gap-2">
                            <Button asChild variant="outline">
                                <Link href="/admin/sales/create">
                                    <ShoppingCart className="mr-2 h-4 w-4" />
                                    Nueva Venta
                                </Link>
                            </Button>
                            <Button variant="destructive" onClick={() => setShowClose(true)}>
                                <Lock className="mr-2 h-4 w-4" />
                                Cerrar Turno
                            </Button>
                        </div>
                    )}
                </div>

                {/* Tarjetas resumen */}
                <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Fondo inicial</p>
                        <p className="mt-1 text-xl font-bold">{fmt(shift.opening_amount)}</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Ventas totales</p>
                        <p className="mt-1 text-xl font-bold">{fmt(totalSales)}</p>
                        <p className="text-xs text-gray-400">
                            {salesCount} completada{salesCount !== 1 ? 's' : ''}
                        </p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Movimientos</p>
                        <p className="text-sm font-semibold text-green-600">+{fmt(totalIncomes)} ingresos</p>
                        <p className="text-sm font-semibold text-red-600">-{fmt(totalExpenses)} gastos</p>
                        <p className="text-sm font-semibold text-orange-600">-{fmt(withdrawalsTotal)} retiros</p>
                    </div>
                    <div className="rounded-lg border bg-white p-4 shadow-sm">
                        <p className="text-xs text-gray-500">Estado</p>
                        <p className={`mt-1 text-xl font-bold ${shift.status === 'open' ? 'text-amber-600' : 'text-gray-500'}`}>
                            {shift.status === 'open' ? 'Abierto' : 'Cerrado'}
                        </p>
                    </div>
                </div>

                {/* Arqueo del turno abierto: cuánto debe haber y de dónde sale. */}
                {shift.status === 'open' && (
                    <div className="mb-6 overflow-hidden rounded-lg border border-blue-200 bg-white shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-4 border-b border-blue-100 bg-blue-50 p-4">
                            <div>
                                <p className="text-xs font-semibold tracking-wide text-blue-600 uppercase">
                                    Debe haber en el cajón
                                </p>
                                <p className="mt-1 text-3xl font-bold text-blue-900 tabular-nums">
                                    {bs(expectedAmount)}
                                </p>
                                {/* El número solo sirve si se sabe qué hacer con él. */}
                                <p className="mt-1 text-sm text-blue-700">
                                    Cuenta el efectivo del cajón y compáralo con este importe.
                                </p>
                            </div>
                            <Button variant="destructive" onClick={() => setShowClose(true)} className="shrink-0">
                                <Lock className="mr-2 h-4 w-4" />
                                Cerrar Turno
                            </Button>
                        </div>

                        <div className="p-4 text-gray-700">
                            <DesgloseEfectivo
                                fondo={shift.opening_amount}
                                arqueo={arqueo}
                                total={expectedAmount}
                            />
                            <FueraDelCajon arqueo={arqueo} />
                        </div>
                    </div>
                )}

                {/* Cierre del turno (turno cerrado) */}
                {shift.status === 'closed' && (
                    <div className="mb-6 overflow-hidden rounded-lg border bg-white shadow-sm">
                        <div className="grid grid-cols-1 divide-y border-b sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                            <div className="p-4">
                                <p className="text-xs text-gray-500">Contado al cerrar</p>
                                <p className="mt-1 text-xl font-bold tabular-nums">{bs(shift.closing_amount)}</p>
                            </div>
                            <div className="p-4">
                                <p className="text-xs text-gray-500">Debía haber</p>
                                <p className="mt-1 text-xl font-bold tabular-nums">{bs(expectedAmount)}</p>
                            </div>
                            <div className="p-4">
                                {/* «Diferencia» no dice de qué lado cae; sobra o falta sí. */}
                                <p className="text-xs text-gray-500">
                                    {diferencia === null || diferencia === 0
                                        ? 'Diferencia'
                                        : diferencia < 0
                                          ? 'Faltante'
                                          : 'Sobrante'}
                                </p>
                                <p
                                    className={`mt-1 text-xl font-bold tabular-nums ${
                                        diferencia === null || diferencia === 0
                                            ? 'text-gray-700'
                                            : diferencia < 0
                                              ? 'text-red-600'
                                              : 'text-amber-600'
                                    }`}
                                >
                                    {diferencia === 0 ? 'Cuadra' : bs(Math.abs(diferencia ?? 0))}
                                </p>
                            </div>
                        </div>

                        {/* El desglose desaparecía al cerrar, y entonces la diferencia era
                            un número sin nada con lo que contrastarlo. Se totaliza con el
                            recálculo de hoy, no con lo que se guardó: si no, las líneas de
                            arriba no sumarían el total de abajo. */}
                        <div className="p-4 text-gray-700">
                            <DesgloseEfectivo fondo={shift.opening_amount} arqueo={arqueo} total={expectedAmount} />
                            <FueraDelCajon arqueo={arqueo} />

                            {desfaseAlCerrar !== null && (
                                <div className="mt-4 rounded border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                                    <p className="font-semibold">Este turno se cerró con otra cuenta.</p>
                                    <p className="mt-1">
                                        Al cerrarlo se guardó {bs(shift.expected_amount)} como esperado y quedó
                                        registrado un descuadre de {bs(Math.abs(parseFloat(shift.difference ?? '0')))};
                                        rehaciendo el cálculo hoy salen {bs(expectedAmount)}.
                                        {desfaseCoincideConNoEfectivo
                                            ? ` La diferencia es exactamente lo cobrado con tarjeta y transferencia
                                               (${bs(arqueo.ventas_otros)}), que entonces se contaba como si hubiera
                                               entrado al cajón. Con el criterio actual el arqueo cuadra: se contó
                                               lo que debía haber.`
                                            : ' Puede que se hayan modificado ventas o gastos del turno después de cerrarlo.'}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Tablas de movimientos */}
                <div className="space-y-6">
                    {/* Ventas */}
                    <Section
                        title="Ventas del turno"
                        badge={salesCount}
                        right={salesCount > 0 ? `Total: ${fmt(totalSales)}` : undefined}
                    >
                        {shift.sales.length > 0 ? (
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">Folio</th>
                                        <th className="px-4 py-3">Hora</th>
                                        <th className="px-4 py-3">Método</th>
                                        <th className="px-4 py-3">Total</th>
                                        <th className="px-4 py-3">Estado</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {shift.sales.map((sale) => (
                                        <tr key={sale.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 font-mono font-medium">
                                                <Link
                                                    href={`/admin/sales/${sale.id}`}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {sale.folio}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-gray-500">
                                                {new Date(sale.created_at).toLocaleTimeString('es-MX')}
                                            </td>
                                            <td className="px-4 py-3 text-gray-500">
                                                {PM_LABEL[sale.payment_method] ?? sale.payment_method}
                                            </td>
                                            <td className="px-4 py-3 font-medium">{fmt(sale.total)}</td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                        sale.status === 'completed'
                                                            ? 'bg-green-100 text-green-700'
                                                            : 'bg-red-100 text-red-700'
                                                    }`}
                                                >
                                                    {sale.status === 'completed' ? 'Completada' : 'Cancelada'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <EmptyRow cols={5} text="Sin ventas en este turno." />
                        )}
                    </Section>

                    {/* Ingresos */}
                    {shift.incomes.length > 0 && (
                        <Section
                            title="Ingresos"
                            badge={shift.incomes.length}
                            right={`Total: ${fmt(totalIncomes)}`}
                            accentColor="text-green-700"
                        >
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">Categoría</th>
                                        <th className="px-4 py-3">Descripción</th>
                                        <th className="px-4 py-3">Método</th>
                                        <th className="px-4 py-3">Monto</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {shift.incomes.map((inc) => (
                                        <tr key={inc.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 capitalize text-gray-600">{inc.category}</td>
                                            <td className="px-4 py-3 text-gray-600">{inc.description}</td>
                                            <td className="px-4 py-3 text-gray-500">
                                                {PM_LABEL[inc.payment_method] ?? inc.payment_method}
                                            </td>
                                            <td className="px-4 py-3 font-medium text-green-700">
                                                +{fmt(inc.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Section>
                    )}

                    {/* Gastos */}
                    {shift.expenses.length > 0 && (
                        <Section
                            title="Gastos"
                            badge={shift.expenses.length}
                            right={`Total: ${fmt(totalExpenses)}`}
                            accentColor="text-red-700"
                        >
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">Categoría</th>
                                        <th className="px-4 py-3">Descripción</th>
                                        <th className="px-4 py-3">Método</th>
                                        <th className="px-4 py-3">Monto</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {shift.expenses.map((exp) => (
                                        <tr key={exp.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 capitalize text-gray-600">{exp.category}</td>
                                            <td className="px-4 py-3 text-gray-600">{exp.description}</td>
                                            <td className="px-4 py-3 text-gray-500">
                                                {PM_LABEL[exp.payment_method] ?? exp.payment_method}
                                            </td>
                                            <td className="px-4 py-3 font-medium text-red-700">
                                                -{fmt(exp.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Section>
                    )}

                    {/* Retiros */}
                    {shift.withdrawals.length > 0 && (
                        <Section
                            title="Retiros"
                            badge={shift.withdrawals.length}
                            right={`Total: ${fmt(withdrawalsTotal)}`}
                            accentColor="text-orange-700"
                        >
                            <table className="w-full text-sm">
                                <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-4 py-3">Motivo</th>
                                        <th className="px-4 py-3">Autorizado por</th>
                                        <th className="px-4 py-3">Fecha</th>
                                        <th className="px-4 py-3">Monto</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {shift.withdrawals.map((w) => (
                                        <tr key={w.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 text-gray-600">{w.reason}</td>
                                            <td className="px-4 py-3 text-gray-500">{w.authorized_by ?? '—'}</td>
                                            <td className="px-4 py-3 text-gray-500">{w.date}</td>
                                            <td className="px-4 py-3 font-medium text-orange-700">
                                                -{fmt(w.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Section>
                    )}
                </div>
            </div>

            {/* Modal de cierre */}
            {showClose && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-lg bg-white shadow-xl">
                        <div className="flex items-start justify-between border-b px-6 py-4">
                            <div>
                                <h2 className="text-lg font-bold">Cierre de Turno #{shift.id}</h2>
                                <p className="text-sm text-gray-500">
                                    {shift.cash_register.name} — {shift.cash_register.store.name}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowClose(false)}
                                className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={handleClose} className="space-y-4 p-6">
                            {/* Desglose por método de pago */}
                            <div className="rounded-md border bg-gray-50 p-4 text-sm">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    Desglose del turno
                                </p>
                                <div className="space-y-1 text-gray-600">
                                    {/* Fondo inicial */}
                                    <ModalRow label="Fondo inicial" value={fmt(shift.opening_amount)} />

                                    {/* Solo el efectivo suma al cajón; el resto se lista
                                        para cuadrar la jornada, pero atenuado y sin signo. */}
                                    {Object.entries(salesByMethod).map(([method, amount]) => (
                                        <ModalRow
                                            key={`sale-${method}`}
                                            label={
                                                method === 'cash'
                                                    ? '+ Ventas (Efectivo)'
                                                    : `Ventas (${PM_LABEL[method] ?? method}) — no entra al cajón`
                                            }
                                            value={fmt(amount)}
                                            color={method === 'cash' ? undefined : 'text-gray-400'}
                                        />
                                    ))}

                                    {Object.entries(incomesByMethod).map(([method, amount]) => (
                                        <ModalRow
                                            key={`inc-${method}`}
                                            label={
                                                method === 'cash'
                                                    ? '+ Ingresos (Efectivo)'
                                                    : `Ingresos (${PM_LABEL[method] ?? method}) — no entra al cajón`
                                            }
                                            value={fmt(amount)}
                                            color={method === 'cash' ? 'text-green-700' : 'text-gray-400'}
                                        />
                                    ))}

                                    {Object.entries(expensesByMethod).map(([method, amount]) => (
                                        <ModalRow
                                            key={`exp-${method}`}
                                            label={
                                                method === 'cash'
                                                    ? '- Gastos (Efectivo)'
                                                    : `Gastos (${PM_LABEL[method] ?? method}) — no sale del cajón`
                                            }
                                            value={method === 'cash' ? `-${fmt(amount)}` : fmt(amount)}
                                            color={method === 'cash' ? 'text-red-700' : 'text-gray-400'}
                                        />
                                    ))}

                                    {/* Retiros */}
                                    {withdrawalsTotal > 0 && (
                                        <ModalRow
                                            label="- Retiros"
                                            value={`-${fmt(withdrawalsTotal)}`}
                                            color="text-orange-700"
                                        />
                                    )}
                                </div>
                                <div className="mt-2 flex justify-between border-t pt-2 font-bold text-gray-800">
                                    <span>Efectivo esperado</span>
                                    <span>{fmt(expectedAmount)}</span>
                                </div>
                                {arqueo.ventas_mixtas > 0 && (
                                    <p className="mt-2 rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800">
                                        {fmt(arqueo.ventas_mixtas)} en ventas mixtas quedan fuera: la venta no
                                        registra qué parte se cobró en efectivo.
                                    </p>
                                )}
                            </div>

                            {/* Monto contado */}
                            <div>
                                <Label htmlFor="closing_amount" className="mb-1.5 block">
                                    Monto contado / declarado ($) *
                                </Label>
                                <Input
                                    id="closing_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    autoFocus
                                    placeholder="0.00"
                                    value={data.closing_amount}
                                    onChange={(e) => setData('closing_amount', e.target.value)}
                                    className="text-lg"
                                />
                                {errors.closing_amount && (
                                    <p className="mt-1 text-xs text-red-500">{errors.closing_amount}</p>
                                )}
                            </div>

                            {/* Diferencia en tiempo real */}
                            {liveDiff !== null && (
                                <div
                                    className={`flex items-center justify-between rounded-md border px-4 py-3 text-sm font-semibold ${
                                        liveDiff < 0
                                            ? 'border-red-200 bg-red-50 text-red-700'
                                            : liveDiff > 0
                                              ? 'border-yellow-200 bg-yellow-50 text-yellow-700'
                                              : 'border-green-200 bg-green-50 text-green-700'
                                    }`}
                                >
                                    <span>Diferencia</span>
                                    <span>
                                        {liveDiff > 0 ? '+' : ''}
                                        {fmt(liveDiff)}
                                    </span>
                                </div>
                            )}

                            {/* Notas */}
                            <div>
                                <Label htmlFor="close_notes" className="mb-1.5 block">
                                    Notas de cierre
                                </Label>
                                <textarea
                                    id="close_notes"
                                    className="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    rows={2}
                                    placeholder="Observaciones opcionales..."
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                />
                            </div>

                            <div className="flex gap-2 pt-1">
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing || !data.closing_amount}
                                    className="flex-1"
                                >
                                    {processing ? 'Cerrando turno...' : 'Confirmar Cierre'}
                                </Button>
                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => setShowClose(false)}
                                >
                                    Cancelar
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

/* ── Componentes auxiliares ── */

function Section({
    title,
    badge,
    right,
    accentColor,
    children,
}: {
    title: string;
    badge?: number;
    right?: string;
    accentColor?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-lg border bg-white shadow-sm">
            <div className={`flex items-center justify-between border-b px-4 py-3 ${accentColor ?? ''}`}>
                <h2 className="font-semibold">
                    {title}
                    {badge !== undefined && (
                        <span className="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-normal text-gray-500">
                            {badge}
                        </span>
                    )}
                </h2>
                {right && <span className="text-sm font-medium">{right}</span>}
            </div>
            {children}
        </div>
    );
}

function EmptyRow({ cols, text }: { cols: number; text: string }) {
    return (
        <table className="w-full">
            <tbody>
                <tr>
                    <td colSpan={cols} className="px-4 py-8 text-center text-sm text-gray-400">
                        {text}
                    </td>
                </tr>
            </tbody>
        </table>
    );
}

function ModalRow({
    label,
    value,
    color,
}: {
    label: string;
    value: string;
    color?: string;
}) {
    return (
        <div className={`flex justify-between ${color ?? ''}`}>
            <span>{label}</span>
            <span>{value}</span>
        </div>
    );
}
