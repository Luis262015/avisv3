import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CalendarClock, CircleAlert } from 'lucide-react';

/**
 * Aviso del estado de la renta, encima del contenido.
 *
 * Solo aparece cuando hay algo que hacer: la prueba o el periodo está por
 * terminar, o ya terminó. Con la suscripción en orden no ocupa sitio.
 */
export function SubscriptionBanner() {
    const { tenant, auth } = usePage<SharedData>().props;
    const subscription = tenant?.subscription;

    if (!tenant || !subscription) return null;

    const vencida = !subscription.is_usable || tenant.suspended;
    if (!vencida && !subscription.is_expiring_soon) return null;

    const dias = subscription.days_left ?? 0;
    const enPrueba = subscription.status === 'trialing';
    const esAdmin = auth.roles.includes('admin');

    const cuando = dias <= 0 ? 'hoy' : dias === 1 ? 'mañana' : `en ${dias} días`;

    const mensaje = tenant.suspended
        ? 'La cuenta de la empresa está suspendida.'
        : vencida
          ? `El plan ${subscription.plan?.name ?? ''} venció. Regulariza el pago para seguir operando.`
          : enPrueba
            ? `Tu prueba del plan ${subscription.plan?.name ?? ''} termina ${cuando}.`
            : `Tu plan ${subscription.plan?.name ?? ''} vence ${cuando}.`;

    const Icono = vencida ? CircleAlert : CalendarClock;

    return (
        <div
            role={vencida ? 'alert' : 'status'}
            className={`flex flex-wrap items-center gap-x-4 gap-y-2 border-b px-4 py-2.5 text-sm md:px-6 ${
                vencida ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-900'
            }`}
        >
            <Icono className="size-4 shrink-0" aria-hidden="true" />
            <p className="min-w-0 flex-1 font-medium">{mensaje}</p>
            {esAdmin ? (
                <Link href="/suscripcion" className="font-semibold underline underline-offset-4">
                    {vencida ? 'Ver cómo pagar' : 'Renovar ahora'}
                </Link>
            ) : (
                <span>Avisa a quien administra la cuenta.</span>
            )}
        </div>
    );
}
