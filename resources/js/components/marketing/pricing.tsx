import { Button } from '@/components/ui/button';
import { limit, money } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type ModuleCatalog, type Plan } from '@/types';
import { Link } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';
import { useState } from 'react';

type Ciclo = 'monthly' | 'yearly';

const CUPOS: { key: keyof Plan['limits']; uno: string; varios: string }[] = [
    { key: 'stores', uno: 'tienda', varios: 'tiendas' },
    { key: 'users', uno: 'usuario', varios: 'usuarios' },
    { key: 'products', uno: 'producto', varios: 'productos' },
];

function cupo(valor: number | null, uno: string, varios: string): string {
    if (valor === null) return `${varios.charAt(0).toUpperCase()}${varios.slice(1)} sin límite`;

    return `${valor.toLocaleString('es-BO')} ${valor === 1 ? uno : varios}`;
}

/**
 * Planes con su precio, sus cupos y lo que incluyen, más la tabla que los
 * compara módulo a módulo.
 */
export function Pricing({ plans, modules }: { plans: Plan[]; modules: ModuleCatalog }) {
    const [ciclo, setCiclo] = useState<Ciclo>('monthly');

    const hayAnual = plans.some((p) => !p.is_free && p.price_yearly > 0 && p.price_yearly < p.price_monthly * 12);

    return (
        <div>
            {hayAnual && (
                <div className="mb-10 flex justify-center">
                    <div role="radiogroup" aria-label="Periodo de pago" className="bg-muted inline-flex rounded-full p-1">
                        {(['monthly', 'yearly'] as const).map((valor) => (
                            <button
                                key={valor}
                                type="button"
                                role="radio"
                                aria-checked={ciclo === valor}
                                onClick={() => setCiclo(valor)}
                                className={cn(
                                    'min-h-10 rounded-full px-5 text-sm font-semibold transition-colors',
                                    ciclo === valor ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {valor === 'monthly' ? 'Pago mensual' : 'Pago anual'}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            <div className={cn('grid items-start gap-5', plans.length >= 3 ? 'lg:grid-cols-3' : 'md:grid-cols-2')}>
                {plans.map((plan) => {
                    const anual = ciclo === 'yearly' && !plan.is_free;
                    const precio = anual ? plan.price_yearly : plan.price_monthly;
                    const ahorro = anual ? plan.price_monthly * 12 - plan.price_yearly : 0;
                    const destacado = plan.is_featured;

                    return (
                        <article
                            key={plan.id}
                            className={cn(
                                'relative flex h-full flex-col rounded-2xl p-7',
                                destacado
                                    ? 'bg-brand-navy text-white shadow-[0_28px_60px_-28px_rgba(10,37,80,0.7)] lg:-my-3 lg:py-10 dark:bg-[#12233f]'
                                    : 'bg-card border',
                            )}
                        >
                            {destacado && (
                                <p className="absolute top-0 right-6 -translate-y-1/2 rounded-full bg-[#00ada4] px-3 py-1 text-xs font-bold text-[#04202b]">
                                    Recomendado
                                </p>
                            )}

                            <h3 className="text-xl font-semibold">{plan.name}</h3>
                            {plan.tagline && (
                                <p className={cn('mt-1.5 text-sm leading-relaxed', destacado ? 'text-white/80' : 'text-muted-foreground')}>{plan.tagline}</p>
                            )}

                            <p className="mt-6 flex items-baseline gap-2">
                                <span className="font-display text-4xl font-semibold tracking-[-0.02em]">{plan.is_free ? 'Gratis' : money(precio, plan.currency)}</span>
                                {!plan.is_free && <span className={cn('text-sm', destacado ? 'text-white/75' : 'text-muted-foreground')}>{anual ? 'al año' : 'al mes'}</span>}
                            </p>
                            <p className={cn('mt-1.5 min-h-5 text-sm', destacado ? 'text-[#7de8e0]' : 'text-brand-teal-ink')}>
                                {plan.is_free
                                    ? 'Para siempre, sin vencimiento'
                                    : ahorro > 0
                                      ? `Ahorras ${money(ahorro, plan.currency)} frente al pago mensual`
                                      : plan.trial_days > 0
                                        ? `${plan.trial_days} días de prueba sin pagar`
                                        : ''}
                            </p>

                            <Button asChild size="lg" variant={destacado ? 'brand' : plan.is_free ? 'outline' : 'default'} className="mt-6 w-full">
                                <Link href={`/registro?plan=${plan.slug}&ciclo=${plan.is_free ? 'monthly' : ciclo}`}>
                                    {plan.is_free ? 'Crear cuenta gratis' : plan.trial_days > 0 ? `Probar ${plan.name}` : `Elegir ${plan.name}`}
                                </Link>
                            </Button>

                            <ul className={cn('mt-7 space-y-3 border-t pt-6 text-sm', destacado ? 'border-white/15' : '')}>
                                {CUPOS.map(({ key, uno, varios }) => (
                                    <Incluido key={key} destacado={destacado}>
                                        {cupo(plan.limits[key], uno, varios)}
                                    </Incluido>
                                ))}
                                <Incluido destacado={destacado}>Punto de venta, inventario y compras</Incluido>
                                {Object.entries(modules)
                                    .filter(([clave]) => plan.modules.includes(clave))
                                    .map(([clave, modulo]) => (
                                        <Incluido key={clave} destacado={destacado}>
                                            {modulo.label}
                                            {clave === 'facturacion' && plan.limits.invoices !== null && ` · ${limit(plan.limits.invoices)} facturas al mes`}
                                        </Incluido>
                                    ))}
                            </ul>
                        </article>
                    );
                })}
            </div>

            {/* Comparación completa. En pantallas estrechas se desplaza en horizontal
                dentro de su propia región, sin arrastrar la página. */}
            <div className="mt-16 relative overflow-x-auto rounded-2xl border" role="region" aria-label="Comparación de planes" tabIndex={0}>
                <table className="bg-card w-full min-w-[36rem] text-sm">
                    <caption className="sr-only">Qué incluye cada plan</caption>
                    <thead>
                        <tr className="border-b text-left">
                            <th scope="col" className="w-2/5 px-5 py-4 font-semibold">
                                Qué incluye
                            </th>
                            {plans.map((p) => (
                                <th key={p.id} scope="col" className="px-4 py-4 text-center font-semibold">
                                    {p.name}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        <FilaTexto titulo="Tiendas" valores={plans.map((p) => limit(p.limits.stores))} />
                        <FilaTexto titulo="Usuarios" valores={plans.map((p) => limit(p.limits.users))} />
                        <FilaTexto titulo="Productos" valores={plans.map((p) => limit(p.limits.products))} />
                        <tr className="border-b">
                            <th scope="row" className="px-5 py-3.5 text-left font-medium">
                                Punto de venta, inventario y compras
                                <span className="text-muted-foreground block text-xs font-normal">Turnos de caja, productos, proveedores, clientes y devoluciones.</span>
                            </th>
                            {plans.map((p) => (
                                <Celda key={p.id} incluido />
                            ))}
                        </tr>
                        {Object.entries(modules).map(([clave, modulo]) => (
                            <tr key={clave} className="border-b last:border-b-0">
                                <th scope="row" className="px-5 py-3.5 text-left font-medium">
                                    {modulo.label}
                                    <span className="text-muted-foreground block text-xs font-normal">{modulo.description}</span>
                                </th>
                                {plans.map((p) => (
                                    <Celda key={p.id} incluido={p.modules.includes(clave)} />
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function Incluido({ children, destacado }: { children: React.ReactNode; destacado: boolean }) {
    return (
        <li className="flex gap-3">
            <Check className={cn('mt-0.5 size-4 shrink-0', destacado ? 'text-[#4fd6cd]' : 'text-brand-teal-ink')} aria-hidden="true" />
            <span>{children}</span>
        </li>
    );
}

function FilaTexto({ titulo, valores }: { titulo: string; valores: string[] }) {
    return (
        <tr className="border-b">
            <th scope="row" className="px-5 py-3.5 text-left font-medium">
                {titulo}
            </th>
            {valores.map((v, i) => (
                <td key={i} className="px-4 py-3.5 text-center">
                    {v}
                </td>
            ))}
        </tr>
    );
}

/** Sí o no dicho con palabras además del icono: el color y la forma no bastan. */
function Celda({ incluido = false }: { incluido?: boolean }) {
    return (
        <td className="px-4 py-3.5 text-center">
            {incluido ? (
                <Check className="text-brand-teal-ink mx-auto size-5" aria-hidden="true" />
            ) : (
                <Minus className="text-muted-foreground mx-auto size-4" aria-hidden="true" />
            )}
            <span className="sr-only">{incluido ? 'Incluido' : 'No incluido'}</span>
        </td>
    );
}
