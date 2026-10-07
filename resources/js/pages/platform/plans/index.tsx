import { DataTable, EmptyState, Panel } from '@/components/platform/ui';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import PlatformLayout from '@/layouts/platform-layout';
import { limit, money } from '@/lib/format';
import { type ModuleCatalog, type Plan } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

export default function PlansIndex({ plans, modules }: { plans: Plan[]; modules: ModuleCatalog }) {
    const eliminar = (plan: Plan) => {
        if (confirm(`¿Eliminar el plan ${plan.name}? No se puede deshacer.`)) {
            router.delete(`/plataforma/planes/${plan.id}`, { preserveScroll: true });
        }
    };

    return (
        <PlatformLayout
            title="Planes"
            description="Lo que se ofrece en el sitio: precio, cupos y módulos de cada plan. Los cambios se ven de inmediato."
            actions={
                <Button asChild>
                    <Link href="/plataforma/planes/create">
                        <Plus aria-hidden="true" /> Nuevo plan
                    </Link>
                </Button>
            }
        >
            <Panel>
                {plans.length === 0 ? (
                    <EmptyState title="No hay planes">Sin al menos un plan activo y público nadie puede registrarse.</EmptyState>
                ) : (
                    <DataTable label="Planes">
                        <thead>
                            <tr>
                                <th scope="col">Plan</th>
                                <th scope="col">Precio</th>
                                <th scope="col">Cupos</th>
                                <th scope="col">Módulos</th>
                                <th scope="col">Empresas</th>
                                <th scope="col">
                                    <span className="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {plans.map((plan) => (
                                <tr key={plan.id} className="align-top">
                                    <td>
                                        <span className="font-semibold">{plan.name}</span>
                                        <span className="mt-1.5 flex flex-wrap gap-1.5">
                                            {!plan.is_active && <Badge variant="muted">Inactivo</Badge>}
                                            {plan.is_active && !plan.is_public && <Badge variant="muted">No se ofrece en el sitio</Badge>}
                                            {plan.is_featured && <Badge variant="info">Recomendado</Badge>}
                                        </span>
                                    </td>
                                    <td>
                                        {plan.is_free ? (
                                            'Gratis'
                                        ) : (
                                            <>
                                                {money(plan.price_monthly, plan.currency)} <span className="text-muted-foreground">al mes</span>
                                                <span className="text-muted-foreground block text-xs">
                                                    {money(plan.price_yearly, plan.currency)} al año · {plan.trial_days} días de prueba
                                                </span>
                                            </>
                                        )}
                                    </td>
                                    <td className="text-xs leading-relaxed">
                                        Tiendas: {limit(plan.limits.stores)}
                                        <br />
                                        Usuarios: {limit(plan.limits.users)}
                                        <br />
                                        Productos: {limit(plan.limits.products)}
                                        <br />
                                        Facturas al mes: {limit(plan.limits.invoices)}
                                    </td>
                                    <td className="max-w-56 text-xs leading-relaxed">
                                        {plan.modules.length === 0 ? (
                                            <span className="text-muted-foreground">Solo el núcleo</span>
                                        ) : (
                                            plan.modules.map((m) => modules[m]?.label ?? m).join(', ')
                                        )}
                                    </td>
                                    <td>{plan.subscriptions_count ?? 0}</td>
                                    <td>
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon" className="size-9">
                                                <Link href={`/plataforma/planes/${plan.id}/edit`} aria-label={`Editar el plan ${plan.name}`}>
                                                    <Pencil aria-hidden="true" />
                                                </Link>
                                            </Button>
                                            <Button variant="ghost" size="icon" className="size-9" onClick={() => eliminar(plan)} aria-label={`Eliminar el plan ${plan.name}`}>
                                                <Trash2 className="text-red-600" aria-hidden="true" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                )}
            </Panel>
        </PlatformLayout>
    );
}
