import { Field, NativeSelect, TextArea } from '@/components/field';
import { DataTable, EmptyState, Panel, PaymentStatusBadge, SubscriptionStatusBadge, TenantStatusBadge, vencimiento } from '@/components/platform/ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PlatformLayout from '@/layouts/platform-layout';
import { limit, longDate, money, shortDate } from '@/lib/format';
import { type Payment, type Plan, type Subscription } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, ExternalLink } from 'lucide-react';
import { type FormEventHandler, type ReactNode } from 'react';

interface Tenant {
    id: number;
    name: string;
    slug: string;
    domain: string | null;
    host: string;
    url: string;
    database: string;
    is_legacy: boolean;
    nit: string | null;
    owner_name: string;
    owner_email: string;
    phone: string | null;
    city: string | null;
    status: 'provisioning' | 'active' | 'suspended' | 'cancelled';
    status_label: string;
    suspension_reason: string | null;
    notes: string | null;
    created_at: string;
    subscription: Subscription | null;
}

interface Props {
    tenant: Tenant;
    usage: { key: string; label: string; used: number; limit: number | null }[];
    payments: Payment[];
    plans: Plan[];
    methods: { value: string; label: string }[];
}

export default function TenantShow({ tenant, usage, payments, plans, methods }: Props) {
    const base = `/plataforma/empresas/${tenant.id}`;
    const subscription = tenant.subscription;

    return (
        <PlatformLayout
            title={tenant.name}
            description={`Alta el ${longDate(tenant.created_at)}`}
            actions={
                <Button asChild variant="outline">
                    <a href={tenant.url} target="_blank" rel="noopener noreferrer" aria-label={`Abrir ${tenant.host} (se abre en una pestaña nueva)`}>
                        {tenant.host} <ExternalLink aria-hidden="true" />
                    </a>
                </Button>
            }
        >
            <p className="mb-6 -mt-4">
                <Link href="/plataforma/empresas" className="text-brand-teal-ink text-sm font-semibold underline underline-offset-4">
                    Volver a empresas
                </Link>
            </p>

            {tenant.status === 'suspended' && (
                <p role="alert" className="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900">
                    <strong className="font-semibold">Cuenta suspendida.</strong> {tenant.suspension_reason}
                </p>
            )}

            <div className="grid gap-6 lg:grid-cols-[1.45fr_1fr]">
                <div className="grid content-start gap-6">
                    <Datos tenant={tenant} base={base} />

                    <Panel title="Pagos" actions={<RegistrarPago tenant={tenant} plans={plans} methods={methods} />}>
                        {payments.length === 0 ? (
                            <EmptyState title="Esta empresa aún no registra pagos" />
                        ) : (
                            <DataTable label="Pagos de la empresa">
                                <thead>
                                    <tr>
                                        <th scope="col">Fecha</th>
                                        <th scope="col">Concepto</th>
                                        <th scope="col">Estado</th>
                                        <th scope="col" className="text-right!">
                                            Importe
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payments.map((p) => (
                                        <tr key={p.id}>
                                            <td>{shortDate(p.created_at)}</td>
                                            <td>
                                                {p.plan?.name} · {p.billing_cycle_label}
                                                <span className="text-muted-foreground block text-xs">
                                                    {p.method_label}
                                                    {p.reference && ` · ${p.reference}`}
                                                    {p.period_end && ` · cubre hasta ${shortDate(p.period_end)}`}
                                                </span>
                                            </td>
                                            <td>
                                                <PaymentStatusBadge status={p.status} label={p.status_label} />
                                            </td>
                                            <td className="text-right font-semibold">{money(p.amount, p.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </DataTable>
                        )}
                    </Panel>
                </div>

                <div className="grid content-start gap-6">
                    <Panel title="Suscripción">
                        <div className="p-5">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="font-display text-2xl font-semibold">{subscription?.plan?.name ?? 'Sin plan'}</p>
                                <SubscriptionStatusBadge subscription={subscription} />
                            </div>
                            {subscription && (
                                <p className="text-muted-foreground mt-1.5 text-sm">
                                    {subscription.plan?.is_free ? 'Plan gratuito' : `Pago ${subscription.billing_cycle_label.toLowerCase()}`} · {vencimiento(subscription)}
                                    {subscription.ends_at && ` (${shortDate(subscription.ends_at)})`}
                                </p>
                            )}
                        </div>

                        <Desplegable titulo="Cambiar de plan">
                            <CambiarPlan base={base} plans={plans} subscription={subscription} />
                        </Desplegable>
                        {subscription && !subscription.plan?.is_free && (
                            <Desplegable titulo="Mover la fecha de vencimiento">
                                <Extender base={base} />
                            </Desplegable>
                        )}
                    </Panel>

                    <Panel title="Uso del plan">
                        {usage.length === 0 ? (
                            <EmptyState title="Sin datos de uso">No se pudo leer la base de la empresa.</EmptyState>
                        ) : (
                            <ul className="space-y-4 p-5">
                                {usage.map((u) => {
                                    const lleno = u.limit !== null && u.used >= u.limit;

                                    return (
                                        <li key={u.key}>
                                            <div className="mb-1.5 flex justify-between gap-3 text-sm">
                                                <span className="font-medium">{u.label}</span>
                                                <span className={lleno ? 'font-semibold text-red-700' : 'text-muted-foreground'}>
                                                    {u.used.toLocaleString('es-BO')}
                                                {u.limit === null ? ' · sin límite' : ` de ${limit(u.limit)}`}
                                                </span>
                                            </div>
                                            {u.limit !== null && (
                                                <div className="bg-muted h-2 overflow-hidden rounded-full" aria-hidden="true">
                                                    <div
                                                        className={`h-full rounded-full ${lleno ? 'bg-red-600' : 'bg-chart-2'}`}
                                                        style={{ width: `${Math.min((u.used / u.limit) * 100, 100)}%` }}
                                                    />
                                                </div>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </Panel>

                    <Panel title="Acceso y baja">
                        <div className="grid gap-5 p-5">
                            {tenant.status === 'suspended' ? (
                                <div>
                                    <p className="text-muted-foreground mb-3 text-sm">La empresa recupera el acceso de inmediato.</p>
                                    <Button variant="outline" onClick={() => router.post(`${base}/reactivar`, {}, { preserveScroll: true })}>
                                        Reactivar cuenta
                                    </Button>
                                </div>
                            ) : (
                                <Suspender base={base} />
                            )}

                            {!tenant.is_legacy && <Eliminar tenant={tenant} base={base} />}
                        </div>
                    </Panel>
                </div>
            </div>
        </PlatformLayout>
    );
}

/** Formulario que no hace falta ver siempre: se abre cuando se va a usar. */
function Desplegable({ titulo, children }: { titulo: string; children: ReactNode }) {
    return (
        <details className="group border-t">
            <summary className="flex min-h-12 cursor-pointer list-none items-center justify-between px-5 text-sm font-semibold [&::-webkit-details-marker]:hidden">
                {titulo}
                <ChevronDown className="text-muted-foreground size-4 transition-transform duration-200 group-open:rotate-180" aria-hidden="true" />
            </summary>
            <div className="px-5 pb-5">{children}</div>
        </details>
    );
}

function Datos({ tenant, base }: { tenant: Tenant; base: string }) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        name: tenant.name,
        nit: tenant.nit ?? '',
        owner_name: tenant.owner_name,
        owner_email: tenant.owner_email,
        phone: tenant.phone ?? '',
        city: tenant.city ?? '',
        domain: tenant.domain ?? '',
        notes: tenant.notes ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(base, { preserveScroll: true });
    };

    return (
        <Panel title="Datos de la empresa" actions={<TenantStatusBadge status={tenant.status} label={tenant.status_label} />}>
            <form onSubmit={submit} noValidate className="grid gap-5 p-5 sm:grid-cols-2">
                <Field label="Nombre" error={errors.name} className="sm:col-span-2">
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                </Field>
                <Field label="NIT" optional error={errors.nit}>
                    <Input value={data.nit} inputMode="numeric" onChange={(e) => setData('nit', e.target.value.replace(/\D/g, ''))} />
                </Field>
                <Field label="Ciudad" optional error={errors.city}>
                    <Input value={data.city} onChange={(e) => setData('city', e.target.value)} />
                </Field>
                <Field label="Contacto" error={errors.owner_name}>
                    <Input value={data.owner_name} onChange={(e) => setData('owner_name', e.target.value)} />
                </Field>
                <Field label="Celular" optional error={errors.phone}>
                    <Input type="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                </Field>
                <Field label="Correo de contacto" error={errors.owner_email}>
                    <Input type="email" value={data.owner_email} onChange={(e) => setData('owner_email', e.target.value)} />
                </Field>
                <Field label="Dominio propio" optional error={errors.domain} hint="Además de su dirección habitual. Debe apuntar a este servidor.">
                    <Input value={data.domain} placeholder="ventas.miempresa.com" autoCapitalize="none" onChange={(e) => setData('domain', e.target.value)} />
                </Field>
                <Field label="Notas internas" optional error={errors.notes} className="sm:col-span-2" hint="Solo las ve la plataforma.">
                    <TextArea value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                </Field>

                <div className="flex flex-wrap items-center gap-4 sm:col-span-2">
                    <Button type="submit" disabled={processing || !isDirty}>
                        Guardar cambios
                    </Button>
                    <p className="text-muted-foreground text-xs">
                        Base de datos: <code className="font-mono">{tenant.database}</code>
                        {tenant.is_legacy && ' (compartida con la plataforma)'}
                    </p>
                </div>
            </form>
        </Panel>
    );
}

function CambiarPlan({ base, plans, subscription }: { base: string; plans: Plan[]; subscription: Subscription | null }) {
    const { data, setData, post, processing, errors } = useForm({
        plan_id: String(subscription?.plan?.id ?? plans[0]?.id ?? ''),
        cycle: subscription?.billing_cycle ?? 'monthly',
        until: '',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(`${base}/plan`, { preserveScroll: true });
            }}
            noValidate
            className="grid gap-4"
        >
            <p className="text-muted-foreground text-sm">El cambio es inmediato y no genera cobro: úsalo para cortesías, migraciones o correcciones.</p>
            <Field label="Plan" error={errors.plan_id}>
                <NativeSelect value={data.plan_id} onChange={(e) => setData('plan_id', e.target.value)}>
                    {plans.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.name}
                        </option>
                    ))}
                </NativeSelect>
            </Field>
            <Field label="Periodo" error={errors.cycle}>
                <NativeSelect value={data.cycle} onChange={(e) => setData('cycle', e.target.value as 'monthly' | 'yearly')}>
                    <option value="monthly">Mensual</option>
                    <option value="yearly">Anual</option>
                </NativeSelect>
            </Field>
            <Field label="Vigente hasta" optional error={errors.until} hint="Vacío: un periodo completo desde hoy.">
                <Input type="date" value={data.until} onChange={(e) => setData('until', e.target.value)} />
            </Field>
            <Button type="submit" disabled={processing} className="justify-self-start">
                Aplicar plan
            </Button>
        </form>
    );
}

function Extender({ base }: { base: string }) {
    const { data, setData, post, processing, errors } = useForm({ until: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(`${base}/vencimiento`, { preserveScroll: true });
            }}
            noValidate
            className="grid gap-4"
        >
            <Field label="Nueva fecha de vencimiento" error={errors.until}>
                <Input type="date" value={data.until} onChange={(e) => setData('until', e.target.value)} />
            </Field>
            <Button type="submit" disabled={processing} className="justify-self-start">
                Guardar fecha
            </Button>
        </form>
    );
}

function RegistrarPago({ tenant, plans, methods }: { tenant: Tenant; plans: Plan[]; methods: { value: string; label: string }[] }) {
    const dePago = plans.filter((p) => !p.is_free);
    const inicial = dePago.find((p) => p.id === tenant.subscription?.plan?.id) ?? dePago[0];

    const { data, setData, post, processing, errors, reset } = useForm({
        tenant_id: tenant.id,
        plan_id: String(inicial?.id ?? ''),
        cycle: (tenant.subscription?.billing_cycle ?? 'monthly') as 'monthly' | 'yearly',
        method: methods[0]?.value ?? 'cash',
        amount: String(inicial ? (tenant.subscription?.billing_cycle === 'yearly' ? inicial.price_yearly : inicial.price_monthly) : ''),
        reference: '',
    });

    if (dePago.length === 0) return null;

    const sugerir = (planId: string, cycle: 'monthly' | 'yearly') => {
        const plan = dePago.find((p) => String(p.id) === planId);
        setData((actual) => ({ ...actual, plan_id: planId, cycle, amount: plan ? String(cycle === 'yearly' ? plan.price_yearly : plan.price_monthly) : actual.amount }));
    };

    return (
        <details className="group relative">
            <summary className="border-input hover:bg-accent flex h-9 cursor-pointer list-none items-center rounded-md border px-3 text-sm font-semibold [&::-webkit-details-marker]:hidden">
                Registrar pago
            </summary>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post('/plataforma/pagos', { preserveScroll: true, onSuccess: () => reset('reference') });
                }}
                noValidate
                className="bg-card absolute right-0 z-20 mt-2 grid w-[min(22rem,calc(100vw-2rem))] gap-4 rounded-xl border p-5 shadow-xl"
            >
                <p className="text-muted-foreground text-sm">Para un pago que ya verificaste: queda aprobado y renueva la suscripción.</p>
                <Field label="Plan" error={errors.plan_id}>
                    <NativeSelect value={data.plan_id} onChange={(e) => sugerir(e.target.value, data.cycle)}>
                        {dePago.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Periodo" error={errors.cycle}>
                        <NativeSelect value={data.cycle} onChange={(e) => sugerir(data.plan_id, e.target.value as 'monthly' | 'yearly')}>
                            <option value="monthly">Mensual</option>
                            <option value="yearly">Anual</option>
                        </NativeSelect>
                    </Field>
                    <Field label="Medio" error={errors.method}>
                        <NativeSelect value={data.method} onChange={(e) => setData('method', e.target.value)}>
                            {methods.map((m) => (
                                <option key={m.value} value={m.value}>
                                    {m.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                </div>
                <Field label="Importe (Bs)" error={errors.amount}>
                    <Input type="number" min="0" step="0.01" inputMode="decimal" value={data.amount} onChange={(e) => setData('amount', e.target.value)} />
                </Field>
                <Field label="Referencia" optional error={errors.reference}>
                    <Input value={data.reference} onChange={(e) => setData('reference', e.target.value)} />
                </Field>
                <Button type="submit" disabled={processing}>
                    Registrar y aprobar
                </Button>
            </form>
        </details>
    );
}

function Suspender({ base }: { base: string }) {
    const { data, setData, post, processing, errors } = useForm({ reason: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(`${base}/suspender`, { preserveScroll: true });
            }}
            noValidate
            className="grid gap-3"
        >
            <Field label="Suspender la cuenta" error={errors.reason} hint="La empresa no podrá operar hasta que la reactives. Verá este motivo.">
                <Input value={data.reason} placeholder="Motivo" onChange={(e) => setData('reason', e.target.value)} />
            </Field>
            <Button type="submit" variant="outline" disabled={processing} className="justify-self-start">
                Suspender
            </Button>
        </form>
    );
}

function Eliminar({ tenant, base }: { tenant: Tenant; base: string }) {
    const { data, setData, delete: destroy, processing, errors } = useForm({ confirm: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                destroy(base);
            }}
            noValidate
            className="grid gap-3 border-t pt-5"
        >
            <Field
                label="Eliminar la empresa"
                error={errors.confirm}
                hint={
                    <>
                        Se borra su base de datos con todas sus ventas, facturas e inventario. No se puede deshacer. Escribe{' '}
                        <strong className="text-foreground font-semibold">{tenant.slug}</strong> para confirmar.
                    </>
                }
            >
                <Input value={data.confirm} autoComplete="off" onChange={(e) => setData('confirm', e.target.value)} />
            </Field>
            <Button type="submit" variant="destructive" disabled={processing || data.confirm !== tenant.slug} className="justify-self-start">
                Eliminar definitivamente
            </Button>
        </form>
    );
}
