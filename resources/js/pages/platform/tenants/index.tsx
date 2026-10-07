import { NativeSelect } from '@/components/field';
import { DataTable, EmptyState, Pagination, Panel, SubscriptionStatusBadge, TenantStatusBadge, vencimiento } from '@/components/platform/ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PlatformLayout from '@/layouts/platform-layout';
import { shortDate } from '@/lib/format';
import { type PaginatedData, type Subscription } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState, type FormEventHandler } from 'react';

interface TenantRow {
    id: number;
    name: string;
    slug: string;
    host: string;
    owner_email: string;
    status: string;
    status_label: string;
    created_at: string;
    subscription: Subscription | null;
}

interface Props {
    tenants: { data: TenantRow[]; meta: PaginatedData<TenantRow> };
    filters: { q?: string; status?: string; plan?: string };
    plans: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
}

export default function TenantsIndex({ tenants, filters, plans, statuses }: Props) {
    const [q, setQ] = useState(filters.q ?? '');

    const filtrar = (cambios: Record<string, string>) => {
        const siguiente = { ...filters, q, ...cambios };
        // Sin claves vacías en la dirección: se comparte y se lee mejor.
        const limpio = Object.fromEntries(Object.entries(siguiente).filter(([, v]) => v));
        router.get('/plataforma/empresas', limpio, { preserveState: true, replace: true });
    };

    const buscar: FormEventHandler = (e) => {
        e.preventDefault();
        filtrar({});
    };

    const hayFiltros = Boolean(filters.q || filters.status || filters.plan);

    return (
        <PlatformLayout
            title="Empresas"
            description="Cada empresa que renta el sistema, con su plan y el estado de su renta."
            actions={
                <Button asChild>
                    <Link href="/plataforma/empresas/create">
                        <Plus aria-hidden="true" /> Nueva empresa
                    </Link>
                </Button>
            }
        >
            <Panel>
                <form onSubmit={buscar} role="search" className="grid gap-3 border-b p-4 sm:grid-cols-[1fr_auto_auto_auto]">
                    <div className="relative">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" aria-hidden="true" />
                        <Input
                            type="search"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Nombre, dirección, correo o NIT"
                            aria-label="Buscar empresas"
                            className="pl-9"
                        />
                    </div>
                    <NativeSelect aria-label="Filtrar por estado" value={filters.status ?? ''} onChange={(e) => filtrar({ status: e.target.value })} className="sm:w-44">
                        <option value="">Todos los estados</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect aria-label="Filtrar por plan" value={filters.plan ?? ''} onChange={(e) => filtrar({ plan: e.target.value })} className="sm:w-44">
                        <option value="">Todos los planes</option>
                        {plans.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <Button type="submit" variant="outline">
                        Buscar
                    </Button>
                </form>

                {tenants.data.length === 0 ? (
                    hayFiltros ? (
                        <EmptyState title="Ninguna empresa coincide con la búsqueda">
                            <Link href="/plataforma/empresas" className="text-brand-teal-ink font-semibold underline underline-offset-4">
                                Quitar filtros
                            </Link>
                        </EmptyState>
                    ) : (
                        <EmptyState title="Aún no hay empresas">Crea la primera con «Nueva empresa» o espera a que se registren desde el sitio.</EmptyState>
                    )
                ) : (
                    <DataTable label="Empresas">
                        <thead>
                            <tr>
                                <th scope="col">Empresa</th>
                                <th scope="col">Plan</th>
                                <th scope="col">Renta</th>
                                <th scope="col">Cuenta</th>
                                <th scope="col">Alta</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tenants.data.map((t) => (
                                <tr key={t.id} className="hover:bg-gray-50">
                                    <td>
                                        <Link href={`/plataforma/empresas/${t.id}`} className="font-semibold hover:underline">
                                            {t.name}
                                        </Link>
                                        <span className="text-muted-foreground block text-xs">
                                            {t.host} · {t.owner_email}
                                        </span>
                                    </td>
                                    <td>{t.subscription?.plan?.name ?? '—'}</td>
                                    <td>
                                        <SubscriptionStatusBadge subscription={t.subscription} />
                                        <span className="text-muted-foreground mt-1 block text-xs">{vencimiento(t.subscription)}</span>
                                    </td>
                                    <td>
                                        <TenantStatusBadge status={t.status} label={t.status_label} />
                                    </td>
                                    <td>{shortDate(t.created_at)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                )}

                <Pagination meta={tenants.meta} />
            </Panel>
        </PlatformLayout>
    );
}
