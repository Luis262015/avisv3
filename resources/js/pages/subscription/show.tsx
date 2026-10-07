import { Field, NativeSelect, TextArea } from '@/components/field';
import { FlashMessage } from '@/components/flash-message';
import { EmptyState, Panel, PaymentStatusBadge, SubscriptionStatusBadge } from '@/components/platform/ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { limit, longDate, money, shortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type ModuleCatalog, type Payment, type Plan, type Subscription } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, LoaderCircle } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface Props {
    company: { name: string; suspended: boolean; suspension_reason: string | null };
    subscription: Subscription | null;
    usage: { key: string; label: string; used: number; limit: number | null }[];
    plans: Plan[];
    modules: ModuleCatalog;
    payments: Payment[];
    paymentInfo: {
        bank_name: string | null;
        bank_account: string | null;
        bank_holder: string | null;
        bank_document: string | null;
        payment_instructions: string | null;
        support_whatsapp: string | null;
        support_email: string | null;
        has_qr: boolean;
    };
    onlineGateway: string | null;
    canManage: boolean;
}

type PagoForm = {
    plan: string;
    cycle: 'monthly' | 'yearly';
    method: 'qr' | 'transfer';
    reference: string;
    notes: string;
    proof: File | null;
};

export default function SubscriptionShow({ company, subscription, usage, plans, modules, payments, paymentInfo, onlineGateway, canManage }: Props) {
    const plan = subscription?.plan;
    const dePago = plans.filter((p) => !p.is_free);
    const gratis = plans.find((p) => p.is_free);
    const pendiente = payments.find((p) => p.status === 'pending');
    const bloqueada = company.suspended || !subscription?.is_usable;

    const { data, setData, post, processing, errors, reset } = useForm<PagoForm>({
        plan: (plan && !plan.is_free ? plan.slug : dePago[0]?.slug) ?? '',
        cycle: subscription?.billing_cycle ?? 'monthly',
        method: paymentInfo.has_qr ? 'qr' : 'transfer',
        reference: '',
        notes: '',
        proof: null,
    });

    const elegido = dePago.find((p) => p.slug === data.plan);
    const importe = elegido ? (data.cycle === 'yearly' ? elegido.price_yearly : elegido.price_monthly) : 0;

    const enviar: FormEventHandler = (e) => {
        e.preventDefault();
        post('/suscripcion/pagos', { preserveScroll: true, forceFormData: true, onSuccess: () => reset('reference', 'notes', 'proof') });
    };

    const pasarAGratis = () => {
        if (confirm('¿Pasar al plan Gratis? Tus datos se conservan, pero dejarás de tener los módulos y cupos de tu plan actual.')) {
            router.post('/suscripcion/plan-gratuito', {}, { preserveScroll: true });
        }
    };

    const hayDatosBancarios = paymentInfo.bank_account || paymentInfo.has_qr;

    return (
        <AppLayout breadcrumbs={[{ title: 'Plan y pagos', href: '/suscripcion' }]}>
            <Head title="Plan y pagos" />
            <FlashMessage />

            <div className="mx-auto w-full max-w-6xl p-4 md:p-8">
                <h1 className="text-2xl font-semibold sm:text-[1.75rem]">Plan y pagos</h1>
                <p className="text-muted-foreground mt-1.5 text-[0.9375rem]">La renta de AVIS para {company.name}.</p>

                {company.suspended ? (
                    <p role="alert" className="mt-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-900">
                        <strong className="font-semibold">La cuenta está suspendida.</strong> {company.suspension_reason} Escríbenos para resolverlo.
                    </p>
                ) : (
                    bloqueada && (
                        <p role="alert" className="mt-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-900">
                            <strong className="font-semibold">Tu plan venció y el sistema está en pausa.</strong> Tus datos están intactos. Envía el pago para
                            seguir operando{gratis ? ' o pasa al plan Gratis' : ''}.
                        </p>
                    )
                )}

                <div className="mt-6 grid gap-6 lg:grid-cols-[1.1fr_1fr]">
                    {/* ── Plan actual ─────────────────────────────────── */}
                    <Panel title="Tu plan">
                        <div className="p-5">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <p className="font-display text-3xl font-semibold tracking-[-0.02em]">{plan?.name ?? 'Sin plan'}</p>
                                <SubscriptionStatusBadge subscription={subscription} />
                            </div>

                            {subscription && plan && (
                                <p className="text-muted-foreground mt-2 leading-relaxed">
                                    {plan.is_free
                                        ? 'Gratis y sin vencimiento.'
                                        : subscription.ends_at === null
                                          ? 'Sin fecha de vencimiento.'
                                          : subscription.status === 'trialing'
                                          ? `Estás en el periodo de prueba, que termina el ${longDate(subscription.ends_at)}.`
                                          : subscription.is_usable
                                            ? `Pago ${subscription.billing_cycle_label.toLowerCase()}. Cubierto hasta el ${longDate(subscription.ends_at)}.`
                                            : `Venció el ${longDate(subscription.ends_at)}.`}
                                </p>
                            )}

                            {plan && (
                                <ul className="mt-5 grid gap-2.5 text-sm sm:grid-cols-2">
                                    <Linea>Punto de venta, inventario y compras</Linea>
                                    {Object.entries(modules)
                                        .filter(([clave]) => plan.modules.includes(clave))
                                        .map(([clave, m]) => (
                                            <Linea key={clave}>{m.label}</Linea>
                                        ))}
                                </ul>
                            )}

                            {canManage && gratis && plan && !plan.is_free && (
                                <Button variant="outline" size="sm" className="mt-6" onClick={pasarAGratis}>
                                    Pasar al plan Gratis
                                </Button>
                            )}
                        </div>
                    </Panel>

                    {/* ── Uso ─────────────────────────────────────────── */}
                    <Panel title="Lo que llevas usado">
                        <ul className="space-y-5 p-5">
                            {usage.map((u) => {
                                const lleno = u.limit !== null && u.used >= u.limit;

                                return (
                                    <li key={u.key}>
                                        <div className="mb-1.5 flex justify-between gap-3 text-sm">
                                            <span className="font-medium">{u.label}</span>
                                            <span className={lleno ? 'font-semibold text-red-700' : 'text-muted-foreground'}>
                                                {u.used.toLocaleString('es-BO')}
                                                {u.limit === null ? ' · sin límite' : ` de ${limit(u.limit)}`}
                                                {lleno && ' · cupo lleno'}
                                            </span>
                                        </div>
                                        {u.limit !== null && (
                                            <div className="bg-muted h-2 overflow-hidden rounded-full" aria-hidden="true">
                                                <div className={cn('h-full rounded-full', lleno ? 'bg-red-600' : 'bg-chart-2')} style={{ width: `${Math.min((u.used / u.limit) * 100, 100)}%` }} />
                                            </div>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </Panel>
                </div>

                {/* ── Pagar o cambiar de plan ─────────────────────────── */}
                {canManage && dePago.length > 0 && !company.suspended && (
                    <Panel title={plan?.is_free ? 'Sube de plan' : 'Paga o cambia de plan'} description="Elige el plan, paga y envía el comprobante. Lo confirmamos y queda al día." className="mt-6">
                        {pendiente ? (
                            <div className="p-5">
                                <p className="font-medium">Tu pago de {money(pendiente.amount, pendiente.currency)} está en revisión.</p>
                                <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                    Lo enviaste el {longDate(pendiente.created_at)} por el plan {pendiente.plan?.name}. Te avisaremos aquí cuando esté confirmado; mientras
                                    tanto no hace falta enviar otro.
                                </p>
                            </div>
                        ) : (
                            <form onSubmit={enviar} noValidate className="grid lg:grid-cols-[1.15fr_1fr]">
                                <div className="grid content-start gap-6 p-5">
                                    <fieldset>
                                        <legend className="mb-3 text-sm font-semibold">1. Elige el plan</legend>
                                        <div className="grid gap-2.5 sm:grid-cols-2">
                                            {dePago.map((p) => {
                                                const activo = p.slug === data.plan;

                                                return (
                                                    <label key={p.id} className={cn('flex cursor-pointer gap-3 rounded-xl border p-4 transition-colors', activo ? 'border-ring bg-accent' : 'hover:border-gray-400')}>
                                                        <input type="radio" name="plan" className="mt-1 size-4" checked={activo} onChange={() => setData('plan', p.slug)} />
                                                        <span className="min-w-0">
                                                            <span className="block font-semibold">
                                                                {p.name}
                                                                {p.id === plan?.id && <span className="text-muted-foreground font-normal"> (el tuyo)</span>}
                                                            </span>
                                                            <span className="text-muted-foreground block text-sm">{money(p.price_monthly, p.currency)} al mes</span>
                                                            <span className="text-muted-foreground mt-1 block text-xs leading-relaxed">
                                                                {limit(p.limits.stores)} tiendas · {limit(p.limits.users)} usuarios
                                                            </span>
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                        {errors.plan && (
                                            <p role="alert" className="mt-2 text-sm font-medium text-red-700">
                                                {errors.plan}
                                            </p>
                                        )}
                                    </fieldset>

                                    <fieldset>
                                        <legend className="mb-3 text-sm font-semibold">2. Elige cada cuánto pagas</legend>
                                        <div className="grid grid-cols-2 gap-2.5">
                                            {(['monthly', 'yearly'] as const).map((ciclo) => {
                                                const precio = elegido ? (ciclo === 'yearly' ? elegido.price_yearly : elegido.price_monthly) : 0;
                                                const ahorro = elegido && ciclo === 'yearly' ? elegido.price_monthly * 12 - elegido.price_yearly : 0;

                                                return (
                                                    <label
                                                        key={ciclo}
                                                        className={cn('flex cursor-pointer gap-3 rounded-xl border p-4 transition-colors', data.cycle === ciclo ? 'border-ring bg-accent' : 'hover:border-gray-400')}
                                                    >
                                                        <input type="radio" name="cycle" className="mt-1 size-4" checked={data.cycle === ciclo} onChange={() => setData('cycle', ciclo)} />
                                                        <span>
                                                            <span className="block font-semibold">{ciclo === 'monthly' ? 'Cada mes' : 'Cada año'}</span>
                                                            <span className="text-muted-foreground block text-sm">{money(precio, elegido?.currency)}</span>
                                                            {ahorro > 0 && <span className="text-brand-teal-ink mt-0.5 block text-xs font-semibold">Ahorras {money(ahorro, elegido?.currency)}</span>}
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </fieldset>

                                    <div className="grid gap-5">
                                        <p className="text-sm font-semibold">3. Envía el comprobante</p>
                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <Field label="Cómo pagaste" error={errors.method}>
                                                <NativeSelect value={data.method} onChange={(e) => setData('method', e.target.value as PagoForm['method'])}>
                                                    <option value="qr">QR</option>
                                                    <option value="transfer">Transferencia</option>
                                                </NativeSelect>
                                            </Field>
                                            <Field label="Número de operación" optional error={errors.reference}>
                                                <Input value={data.reference} onChange={(e) => setData('reference', e.target.value)} />
                                            </Field>
                                        </div>
                                        <Field label="Comprobante" error={errors.proof} hint="Captura o PDF del pago, hasta 4 MB.">
                                            <Input type="file" accept="image/*,application/pdf" onChange={(e) => setData('proof', e.target.files?.[0] ?? null)} />
                                        </Field>
                                        <Field label="Algo que debamos saber" optional error={errors.notes}>
                                            <TextArea className="min-h-20" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                                        </Field>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3">
                                        <Button type="submit" size="lg" disabled={processing}>
                                            {processing && <LoaderCircle className="animate-spin" aria-hidden="true" />}
                                            Enviar comprobante de {money(importe, elegido?.currency)}
                                        </Button>
                                        {onlineGateway && (
                                            <Button
                                                type="button"
                                                size="lg"
                                                variant="outline"
                                                onClick={() => router.post('/suscripcion/pago-en-linea', { plan: data.plan, cycle: data.cycle })}
                                            >
                                                Pagar con {onlineGateway}
                                            </Button>
                                        )}
                                    </div>
                                </div>

                                {/* Datos para pagar */}
                                <div className="bg-muted/60 border-t p-5 lg:border-t-0 lg:border-l">
                                    <h3 className="font-sans text-sm font-semibold tracking-normal">Dónde pagar</h3>

                                    {hayDatosBancarios ? (
                                        <>
                                            <p className="font-display mt-3 text-3xl font-semibold tracking-[-0.02em]">{money(importe, elegido?.currency)}</p>
                                            <p className="text-muted-foreground text-sm">
                                                Plan {elegido?.name}, {data.cycle === 'yearly' ? 'un año' : 'un mes'}
                                            </p>

                                            {paymentInfo.has_qr && (
                                                <img src="/suscripcion/qr" alt="Código QR para pagar" className="mt-5 w-full max-w-56 rounded-xl border bg-white object-contain p-3" />
                                            )}

                                            {paymentInfo.bank_account && (
                                                <dl className="mt-5 grid gap-2.5 text-sm">
                                                    <Dato titulo="Banco" valor={paymentInfo.bank_name} />
                                                    <Dato titulo="Cuenta" valor={paymentInfo.bank_account} />
                                                    <Dato titulo="Titular" valor={paymentInfo.bank_holder} />
                                                    <Dato titulo="NIT o CI" valor={paymentInfo.bank_document} />
                                                </dl>
                                            )}

                                            {paymentInfo.payment_instructions && <p className="mt-5 text-sm leading-relaxed whitespace-pre-line">{paymentInfo.payment_instructions}</p>}
                                        </>
                                    ) : (
                                        <p className="text-muted-foreground mt-3 text-sm leading-relaxed">
                                            Aún no publicamos los datos de pago. Escríbenos
                                            {paymentInfo.support_whatsapp ? ` al WhatsApp ${paymentInfo.support_whatsapp}` : paymentInfo.support_email ? ` a ${paymentInfo.support_email}` : ''} y te los
                                            enviamos.
                                        </p>
                                    )}
                                </div>
                            </form>
                        )}
                    </Panel>
                )}

                {!canManage && (
                    <p className="text-muted-foreground mt-6 text-sm">Solo quien administra la cuenta puede pagar o cambiar de plan.</p>
                )}

                {/* ── Historial ───────────────────────────────────────── */}
                <Panel title="Historial de pagos" className="mt-6">
                    {payments.length === 0 ? (
                        <EmptyState title="Aún no hay pagos">Cuando envíes un comprobante lo verás aquí, con su estado.</EmptyState>
                    ) : (
                        <ul className="divide-y">
                            {payments.map((p) => (
                                <li key={p.id} className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 px-5 py-4">
                                    <div className="min-w-0">
                                        <p className="font-medium">
                                            {p.plan?.name} · {p.billing_cycle_label}
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {shortDate(p.created_at)} · {p.method_label}
                                            {p.period_end && ` · cubre hasta el ${shortDate(p.period_end)}`}
                                        </p>
                                        {p.status === 'rejected' && <p className="mt-1 text-sm text-red-700">No se pudo confirmar: {p.rejection_reason}</p>}
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <PaymentStatusBadge status={p.status} label={p.status === 'pending' ? 'En revisión' : p.status_label} />
                                        <span className="w-24 text-right font-semibold">{money(p.amount, p.currency)}</span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </AppLayout>
    );
}

function Linea({ children }: { children: React.ReactNode }) {
    return (
        <li className="flex gap-2.5">
            <Check className="text-brand-teal-ink mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {children}
        </li>
    );
}

function Dato({ titulo, valor }: { titulo: string; valor: string | null }) {
    if (!valor) return null;

    return (
        <div className="flex justify-between gap-4">
            <dt className="text-muted-foreground">{titulo}</dt>
            <dd className="text-right font-semibold select-all">{valor}</dd>
        </div>
    );
}
