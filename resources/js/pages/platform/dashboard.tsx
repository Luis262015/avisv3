import { DataTable, EmptyState, Panel, SubscriptionStatusBadge, vencimiento } from '@/components/platform/ui';
import { Button } from '@/components/ui/button';
import PlatformLayout from '@/layouts/platform-layout';
import { money, shortDate } from '@/lib/format';
import { type Payment, type Subscription } from '@/types';
import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface TenantRow {
    id: number;
    name: string;
    slug: string;
    created_at: string;
    subscription: Subscription | null;
}

interface DashboardProps {
    stats: {
        tenants_active: number;
        tenants_suspended: number;
        paying: number;
        trialing: number;
        overdue: number;
        mrr: number;
        pending_payments: number;
        signups_30d: number;
    };
    revenue: { month: string; label: string; total: number }[];
    planMix: { name: string; count: number }[];
    pendingPayments: Payment[];
    expiring: TenantRow[];
    recent: TenantRow[];
}

export default function PlatformDashboard({ stats, revenue, planMix, pendingPayments, expiring, recent }: DashboardProps) {
    const maximo = Math.max(...revenue.map((r) => r.total), 1);
    const totalPlanes = planMix.reduce((suma, p) => suma + p.count, 0);

    return (
        <PlatformLayout
            title="Resumen"
            description="Cómo va la renta del sistema hoy."
            actions={
                <Button asChild>
                    <Link href="/plataforma/empresas/create">
                        <Plus aria-hidden="true" /> Nueva empresa
                    </Link>
                </Button>
            }
        >
            {/* Lo que pide atención primero: es por lo que se abre esta pantalla. */}
            {stats.pending_payments > 0 && (
                <Link
                    href="/plataforma/pagos?status=pending"
                    className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900"
                >
                    <span className="font-semibold">
                        {stats.pending_payments === 1 ? 'Hay 1 pago esperando revisión.' : `Hay ${stats.pending_payments} pagos esperando revisión.`}
                    </span>
                    <span className="text-sm font-semibold underline underline-offset-4">Revisar pagos</span>
                </Link>
            )}

            <dl className="bg-card grid divide-y rounded-xl border sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <Dato titulo="Ingreso mensual recurrente" valor={money(stats.mrr)} nota={`${stats.paying} ${stats.paying === 1 ? 'empresa paga' : 'empresas pagan'}`} />
                <Dato titulo="Empresas activas" valor={String(stats.tenants_active)} nota={`${stats.signups_30d} ${stats.signups_30d === 1 ? 'nueva' : 'nuevas'} en 30 días`} />
                <Dato titulo="En prueba" valor={String(stats.trialing)} nota="Aún no pagan" />
                <Dato
                    titulo="Con la renta vencida"
                    valor={String(stats.overdue)}
                    nota={stats.tenants_suspended > 0 ? `${stats.tenants_suspended} suspendidas` : 'Sin acceso hasta que paguen'}
                    alerta={stats.overdue > 0}
                />
            </dl>

            <div className="mt-6 grid gap-6 lg:grid-cols-[1.6fr_1fr]">
                <Panel title="Cobros aprobados" description="Últimos seis meses">
                    {/* Barras decorativas; las cifras exactas van en la tabla de abajo,
                        que es lo que lee un lector de pantalla. */}
                    <div className="px-5 pt-6 pb-4">
                        <div className="flex h-40 items-end gap-3" aria-hidden="true">
                            {revenue.map((r) => (
                                <div key={r.month} className="flex h-full flex-1 flex-col justify-end">
                                    <div
                                        className="bg-chart-2 min-h-1 rounded-t-md"
                                        style={{ height: `${Math.max((r.total / maximo) * 100, r.total > 0 ? 4 : 1)}%` }}
                                    />
                                </div>
                            ))}
                        </div>
                        <table className="mt-2 w-full table-fixed text-center text-xs">
                            <caption className="sr-only">Cobros aprobados por mes</caption>
                            <thead>
                                <tr>
                                    {revenue.map((r) => (
                                        <th key={r.month} scope="col" className="text-muted-foreground py-1 font-medium">
                                            {r.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    {revenue.map((r) => (
                                        <td key={r.month} className="font-semibold">
                                            {r.total > 0 ? money(r.total) : '—'}
                                        </td>
                                    ))}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Panel>

                <Panel title="Empresas por plan">
                    {totalPlanes === 0 ? (
                        <EmptyState title="Todavía no hay empresas" />
                    ) : (
                        <ul className="space-y-4 p-5">
                            {planMix.map((p) => (
                                <li key={p.name}>
                                    <div className="mb-1.5 flex justify-between text-sm">
                                        <span className="font-medium">{p.name}</span>
                                        <span className="text-muted-foreground">
                                            {p.count} {p.count === 1 ? 'empresa' : 'empresas'}
                                        </span>
                                    </div>
                                    <div className="bg-muted h-2 overflow-hidden rounded-full" aria-hidden="true">
                                        <div className="bg-primary h-full rounded-full" style={{ width: `${(p.count / totalPlanes) * 100}%` }} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Panel
                    title="Pagos por revisar"
                    actions={
                        <Link href="/plataforma/pagos" className="text-brand-teal-ink text-sm font-semibold underline underline-offset-4">
                            Ver todos
                        </Link>
                    }
                >
                    {pendingPayments.length === 0 ? (
                        <EmptyState title="Nada por revisar">Cuando una empresa suba un comprobante, aparecerá aquí.</EmptyState>
                    ) : (
                        <ul className="divide-y">
                            {pendingPayments.map((p) => (
                                <li key={p.id} className="flex items-center justify-between gap-4 px-5 py-3.5">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{p.tenant?.name}</p>
                                        <p className="text-muted-foreground text-sm">
                                            {p.plan?.name} · {p.billing_cycle_label} · {shortDate(p.created_at)}
                                        </p>
                                    </div>
                                    <span className="shrink-0 font-semibold">{money(p.amount, p.currency)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>

                <Panel title="Por vencer" description="Pruebas y periodos que terminan pronto">
                    {expiring.length === 0 ? (
                        <EmptyState title="Ninguna empresa vence esta semana" />
                    ) : (
                        <ul className="divide-y">
                            {expiring.map((t) => (
                                <li key={t.id} className="flex items-center justify-between gap-4 px-5 py-3.5">
                                    <div className="min-w-0">
                                        <Link href={`/plataforma/empresas/${t.id}`} className="block truncate font-medium hover:underline">
                                            {t.name}
                                        </Link>
                                        <p className="text-muted-foreground text-sm">
                                            {t.subscription?.plan?.name} · {vencimiento(t.subscription)}
                                        </p>
                                    </div>
                                    <SubscriptionStatusBadge subscription={t.subscription} />
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>

            <Panel title="Últimas altas" className="mt-6">
                {recent.length === 0 ? (
                    <EmptyState title="Aún no se registró ninguna empresa">
                        Las empresas pueden darse de alta solas desde el sitio, o puedes crearlas tú con «Nueva empresa».
                    </EmptyState>
                ) : (
                    <DataTable label="Últimas empresas registradas">
                        <thead>
                            <tr>
                                <th scope="col">Empresa</th>
                                <th scope="col">Plan</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Alta</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recent.map((t) => (
                                <tr key={t.id}>
                                    <td>
                                        <Link href={`/plataforma/empresas/${t.id}`} className="font-medium hover:underline">
                                            {t.name}
                                        </Link>
                                        <span className="text-muted-foreground block text-xs">{t.slug}</span>
                                    </td>
                                    <td>{t.subscription?.plan?.name ?? '—'}</td>
                                    <td>
                                        <SubscriptionStatusBadge subscription={t.subscription} />
                                    </td>
                                    <td>{shortDate(t.created_at)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                )}
            </Panel>
        </PlatformLayout>
    );
}

function Dato({ titulo, valor, nota, alerta = false }: { titulo: string; valor: string; nota: string; alerta?: boolean }) {
    return (
        <div className="px-5 py-5">
            <dt className="text-muted-foreground text-sm">{titulo}</dt>
            <dd className={`font-display mt-1.5 text-3xl font-semibold tracking-[-0.02em] ${alerta ? 'text-red-700' : ''}`}>{valor}</dd>
            <dd className="text-muted-foreground mt-1 text-xs">{nota}</dd>
        </div>
    );
}
