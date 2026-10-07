import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { type PaginatedData, type Subscription } from '@/types';
import { Link } from '@inertiajs/react';
import { type ComponentProps, type ReactNode } from 'react';

type Tono = ComponentProps<typeof Badge>['variant'];

const TONO_EMPRESA: Record<string, Tono> = { active: 'success', suspended: 'danger', provisioning: 'warning', cancelled: 'muted' };
const TONO_SUSCRIPCION: Record<string, Tono> = { active: 'success', trialing: 'info', past_due: 'danger', cancelled: 'muted' };
const TONO_PAGO: Record<string, Tono> = { pending: 'warning', approved: 'success', rejected: 'danger' };

/** El estado siempre va escrito: el color solo acompaña. */
export function TenantStatusBadge({ status, label }: { status: string; label: string }) {
    return <Badge variant={TONO_EMPRESA[status] ?? 'muted'}>{label}</Badge>;
}

export function SubscriptionStatusBadge({ subscription }: { subscription: Subscription | null | undefined }) {
    if (!subscription) return <Badge variant="muted">Sin plan</Badge>;

    return <Badge variant={TONO_SUSCRIPCION[subscription.status] ?? 'muted'}>{subscription.status_label}</Badge>;
}

export function PaymentStatusBadge({ status, label }: { status: string; label: string }) {
    return <Badge variant={TONO_PAGO[status] ?? 'muted'}>{label}</Badge>;
}

/** «vence en 5 días», «venció hace 2 días», «no vence». */
export function vencimiento(subscription: Subscription | null | undefined): string {
    const dias = subscription?.days_left;

    if (dias === null || dias === undefined) return 'No vence';
    if (dias < 0) return `Venció hace ${Math.abs(dias)} ${Math.abs(dias) === 1 ? 'día' : 'días'}`;
    if (dias === 0) return 'Vence hoy';

    return `Vence en ${dias} ${dias === 1 ? 'día' : 'días'}`;
}

/** Bloque con título propio dentro de una pantalla. */
export function Panel({ title, description, actions, className, children }: { title?: string; description?: string; actions?: ReactNode; className?: string; children: ReactNode }) {
    return (
        <section className={cn('bg-card rounded-xl border', className)}>
            {(title || actions) && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                    <div>
                        {title && <h2 className="text-base font-semibold">{title}</h2>}
                        {description && <p className="text-muted-foreground mt-0.5 text-sm">{description}</p>}
                    </div>
                    {actions}
                </div>
            )}
            {children}
        </section>
    );
}

/** Lo que se muestra cuando una lista no tiene nada: qué falta y qué hacer. */
export function EmptyState({ title, children }: { title: string; children?: ReactNode }) {
    return (
        <div className="px-5 py-12 text-center">
            <p className="font-medium">{title}</p>
            {children && <div className="text-muted-foreground mx-auto mt-1.5 max-w-sm text-sm leading-relaxed">{children}</div>}
        </div>
    );
}

/** Tabla que en pantallas estrechas se desplaza dentro de su propia región. */
export function DataTable({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="relative overflow-x-auto" role="region" aria-label={label} tabIndex={0}>
            <table className="w-full min-w-[40rem] text-sm [&_tbody_tr]:border-t [&_td]:px-5 [&_td]:py-3.5 [&_th]:px-5 [&_th]:py-3 [&_th]:text-left [&_thead_th]:text-xs [&_thead_th]:font-semibold [&_thead_th]:text-gray-500">
                {children}
            </table>
        </div>
    );
}

export function Pagination({ meta }: { meta: PaginatedData<unknown> | { links: PaginatedData<unknown>['links']; from: number; to: number; total: number } }) {
    if (!meta.links || meta.links.length <= 3) return null;

    return (
        <nav aria-label="Páginas" className="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-3 text-sm">
            <p className="text-muted-foreground">
                {meta.from ?? 0}–{meta.to ?? 0} de {meta.total}
            </p>
            <ul className="flex flex-wrap gap-1">
                {meta.links.map((link, i) => {
                    const etiqueta = link.label.replace('&laquo; Previous', 'Anterior').replace('Next &raquo;', 'Siguiente').replace('pagination.previous', 'Anterior').replace('pagination.next', 'Siguiente');

                    return (
                        <li key={i}>
                            {link.url ? (
                                <Link
                                    href={link.url}
                                    preserveScroll
                                    aria-current={link.active ? 'page' : undefined}
                                    className={cn(
                                        'flex h-9 min-w-9 items-center justify-center rounded-md px-3 font-medium',
                                        link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-accent',
                                    )}
                                >
                                    {etiqueta}
                                </Link>
                            ) : (
                                <span className="text-muted-foreground flex h-9 min-w-9 items-center justify-center px-3 opacity-60">{etiqueta}</span>
                            )}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
