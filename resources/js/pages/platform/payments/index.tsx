import { Field } from '@/components/field';
import { EmptyState, Pagination, Panel, PaymentStatusBadge } from '@/components/platform/ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PlatformLayout from '@/layouts/platform-layout';
import { money, shortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type PaginatedData, type Payment } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState } from 'react';

interface Props {
    payments: { data: Payment[]; meta: PaginatedData<Payment> };
    status: string | null;
    statuses: { value: string; label: string }[];
    pendingCount: number;
}

export default function PaymentsIndex({ payments, status, statuses, pendingCount }: Props) {
    const filtros = [{ value: '', label: 'Todos' }, ...statuses];

    return (
        <PlatformLayout
            title="Pagos"
            description="Los comprobantes que envían las empresas y los pagos que registras tú. Aprobar uno pone al día la suscripción."
        >
            <nav aria-label="Filtrar por estado" className="mb-4 flex flex-wrap gap-2">
                {filtros.map((f) => {
                    const activo = (status ?? '') === f.value;

                    return (
                        <Link
                            key={f.value}
                            href={f.value ? `/plataforma/pagos?status=${f.value}` : '/plataforma/pagos'}
                            aria-current={activo ? 'page' : undefined}
                            className={cn(
                                'flex min-h-10 items-center rounded-full border px-4 text-sm font-semibold',
                                activo ? 'bg-primary text-primary-foreground border-transparent' : 'bg-card hover:bg-accent',
                            )}
                        >
                            {f.label}
                            {f.value === 'pending' && pendingCount > 0 && (
                                <span className={cn('ml-2 rounded-full px-1.5 text-xs', activo ? 'bg-white/20' : 'bg-amber-100 text-amber-800')}>{pendingCount}</span>
                            )}
                        </Link>
                    );
                })}
            </nav>

            <Panel>
                {payments.data.length === 0 ? (
                    <EmptyState title={status === 'pending' ? 'No hay pagos por revisar' : 'No hay pagos'}>
                        Las empresas envían su comprobante desde «Plan y pagos». También puedes registrar un pago desde la ficha de cada empresa.
                    </EmptyState>
                ) : (
                    <ul className="divide-y">
                        {payments.data.map((p) => (
                            <Pago key={p.id} payment={p} />
                        ))}
                    </ul>
                )}
                <Pagination meta={payments.meta} />
            </Panel>
        </PlatformLayout>
    );
}

function Pago({ payment: p }: { payment: Payment }) {
    const [rechazando, setRechazando] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ reason: '' });

    const aprobar = () => {
        if (confirm(`¿Aprobar el pago de ${money(p.amount, p.currency)} de ${p.tenant?.name}? Su suscripción quedará al día.`)) {
            router.post(`/plataforma/pagos/${p.id}/aprobar`, {}, { preserveScroll: true });
        }
    };

    return (
        <li className="grid gap-4 p-5 md:grid-cols-[1fr_auto] md:items-start">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <Link href={`/plataforma/empresas/${p.tenant?.id}`} className="font-semibold hover:underline">
                        {p.tenant?.name}
                    </Link>
                    <PaymentStatusBadge status={p.status} label={p.status_label} />
                </div>

                <dl className="text-muted-foreground mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <Par titulo="Plan" valor={`${p.plan?.name} · ${p.billing_cycle_label}`} />
                    <Par titulo="Medio" valor={p.method_label} />
                    <Par titulo="Referencia" valor={p.reference ?? '—'} />
                    <Par titulo="Enviado" valor={shortDate(p.created_at)} />
                </dl>

                {p.notes && <p className="mt-2 text-sm">«{p.notes}»</p>}
                {p.status === 'approved' && p.period_end && (
                    <p className="text-muted-foreground mt-2 text-sm">
                        Cubre hasta el {shortDate(p.period_end)}
                        {p.reviewer && ` · aprobó ${p.reviewer}`}
                    </p>
                )}
                {p.status === 'rejected' && <p className="mt-2 text-sm text-red-700">Motivo: {p.rejection_reason}</p>}

                {rechazando && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(`/plataforma/pagos/${p.id}/rechazar`, { preserveScroll: true, onSuccess: () => setRechazando(false) });
                        }}
                        noValidate
                        className="mt-4 grid max-w-md gap-3"
                    >
                        <Field label="Motivo del rechazo" error={errors.reason} hint="La empresa lo verá en su historial de pagos.">
                            <Input value={data.reason} autoFocus onChange={(e) => setData('reason', e.target.value)} />
                        </Field>
                        <div className="flex gap-2">
                            <Button type="submit" variant="destructive" disabled={processing}>
                                Rechazar pago
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => setRechazando(false)}>
                                Cancelar
                            </Button>
                        </div>
                    </form>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2 md:flex-col md:items-end">
                <p className="font-display text-xl font-semibold md:mb-1">{money(p.amount, p.currency)}</p>

                {p.has_proof && (
                    <Button asChild variant="outline" size="sm">
                        <a href={`/plataforma/pagos/${p.id}/comprobante`} target="_blank" rel="noopener noreferrer" aria-label={`Ver comprobante de ${p.tenant?.name} (se abre en una pestaña nueva)`}>
                            <FileText aria-hidden="true" /> Ver comprobante
                        </a>
                    </Button>
                )}

                {p.status === 'pending' && !rechazando && (
                    <div className="flex gap-2">
                        <Button size="sm" onClick={aprobar}>
                            Aprobar
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => setRechazando(true)}>
                            Rechazar
                        </Button>
                    </div>
                )}
            </div>
        </li>
    );
}

function Par({ titulo, valor }: { titulo: string; valor: string }) {
    return (
        <div className="flex gap-1.5 sm:block">
            <dt className="text-xs">{titulo}</dt>
            <dd className="text-foreground truncate font-medium">{valor}</dd>
        </div>
    );
}
