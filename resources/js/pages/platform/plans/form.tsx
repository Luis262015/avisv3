import { Field } from '@/components/field';
import { Panel } from '@/components/platform/ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PlatformLayout from '@/layouts/platform-layout';
import { type ModuleCatalog, type Plan } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

type Form = {
    name: string;
    slug: string;
    tagline: string;
    price_monthly: string;
    price_yearly: string;
    trial_days: string;
    max_stores: string;
    max_users: string;
    max_products: string;
    max_invoices_month: string;
    modules: string[];
    is_public: boolean;
    is_featured: boolean;
    is_active: boolean;
    sort_order: string;
};

const texto = (valor: number | null | undefined) => (valor === null || valor === undefined ? '' : String(valor));

export default function PlanForm({ plan, modules }: { plan: Plan | null; modules: ModuleCatalog }) {
    const { data, setData, post, put, processing, errors, transform } = useForm<Form>({
        name: plan?.name ?? '',
        slug: plan?.slug ?? '',
        tagline: plan?.tagline ?? '',
        price_monthly: texto(plan?.price_monthly ?? 0),
        price_yearly: texto(plan?.price_yearly ?? 0),
        trial_days: texto(plan?.trial_days ?? 0),
        max_stores: texto(plan?.limits.stores),
        max_users: texto(plan?.limits.users),
        max_products: texto(plan?.limits.products),
        max_invoices_month: texto(plan?.limits.invoices),
        modules: plan?.modules ?? [],
        is_public: plan?.is_public ?? true,
        is_featured: plan?.is_featured ?? false,
        is_active: plan?.is_active ?? true,
        sort_order: texto(plan?.sort_order ?? 0),
    });

    // Un cupo vacío es «sin límite»: viaja como null, no como cadena vacía.
    transform((valores) => ({
        ...valores,
        max_stores: valores.max_stores === '' ? null : valores.max_stores,
        max_users: valores.max_users === '' ? null : valores.max_users,
        max_products: valores.max_products === '' ? null : valores.max_products,
        max_invoices_month: valores.max_invoices_month === '' ? null : valores.max_invoices_month,
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (plan) {
            put(`/plataforma/planes/${plan.id}`);
        } else {
            post('/plataforma/planes');
        }
    };

    const alternar = (clave: string) =>
        setData('modules', data.modules.includes(clave) ? data.modules.filter((m) => m !== clave) : [...data.modules, clave]);

    const numero = (campo: keyof Form, opciones: { decimal?: boolean } = {}) => ({
        type: 'number' as const,
        min: 0,
        step: opciones.decimal ? '0.01' : '1',
        inputMode: opciones.decimal ? ('decimal' as const) : ('numeric' as const),
        value: data[campo] as string,
        onChange: (e: React.ChangeEvent<HTMLInputElement>) => setData(campo, e.target.value as never),
    });

    return (
        <PlatformLayout title={plan ? `Editar ${plan.name}` : 'Nuevo plan'} description="El precio y los cupos se muestran tal cual en el sitio público.">
            <form onSubmit={submit} noValidate className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                <div className="grid content-start gap-6">
                    <Panel title="Qué es">
                        <div className="grid gap-5 p-5 sm:grid-cols-2">
                            <Field label="Nombre" error={errors.name}>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus={!plan} />
                            </Field>
                            <Field label="Identificador" optional error={errors.slug} hint="Aparece en el enlace de registro. Vacío: se toma del nombre.">
                                <Input value={data.slug} autoCapitalize="none" spellCheck={false} onChange={(e) => setData('slug', e.target.value)} />
                            </Field>
                            <Field label="Descripción corta" optional error={errors.tagline} className="sm:col-span-2" hint="Una línea: para quién es este plan.">
                                <Input value={data.tagline} onChange={(e) => setData('tagline', e.target.value)} />
                            </Field>
                        </div>
                    </Panel>

                    <Panel title="Precio" description="En bolivianos. Con ambos en 0, el plan es gratuito y no vence.">
                        <div className="grid gap-5 p-5 sm:grid-cols-3">
                            <Field label="Al mes" error={errors.price_monthly}>
                                <Input {...numero('price_monthly', { decimal: true })} />
                            </Field>
                            <Field label="Al año" error={errors.price_yearly}>
                                <Input {...numero('price_yearly', { decimal: true })} />
                            </Field>
                            <Field label="Días de prueba" error={errors.trial_days} hint="0: sin prueba; se activa con el primer pago.">
                                <Input {...numero('trial_days')} />
                            </Field>
                        </div>
                    </Panel>

                    <Panel title="Cupos" description="Deja un campo vacío para no poner tope.">
                        <div className="grid gap-5 p-5 sm:grid-cols-2">
                            <Field label="Tiendas" optional error={errors.max_stores}>
                                <Input {...numero('max_stores')} placeholder="Sin límite" />
                            </Field>
                            <Field label="Usuarios" optional error={errors.max_users}>
                                <Input {...numero('max_users')} placeholder="Sin límite" />
                            </Field>
                            <Field label="Productos" optional error={errors.max_products}>
                                <Input {...numero('max_products')} placeholder="Sin límite" />
                            </Field>
                            <Field label="Facturas al mes" optional error={errors.max_invoices_month}>
                                <Input {...numero('max_invoices_month')} placeholder="Sin límite" />
                            </Field>
                        </div>
                    </Panel>
                </div>

                <div className="grid content-start gap-6">
                    <Panel title="Módulos incluidos" description="Punto de venta, inventario y compras van siempre.">
                        <fieldset className="grid gap-1 p-3">
                            <legend className="sr-only">Módulos incluidos</legend>
                            {Object.entries(modules).map(([clave, modulo]) => (
                                <label key={clave} className="hover:bg-accent flex cursor-pointer gap-3 rounded-lg p-2.5">
                                    <input type="checkbox" className="mt-0.5 size-4 shrink-0" checked={data.modules.includes(clave)} onChange={() => alternar(clave)} />
                                    <span>
                                        <span className="block text-sm font-medium">{modulo.label}</span>
                                        <span className="text-muted-foreground block text-xs leading-relaxed">{modulo.description}</span>
                                    </span>
                                </label>
                            ))}
                        </fieldset>
                        {errors.modules && (
                            <p role="alert" className="px-5 pb-4 text-sm font-medium text-red-700">
                                {errors.modules}
                            </p>
                        )}
                    </Panel>

                    <Panel title="Dónde aparece">
                        <div className="grid gap-1 p-3">
                            <Interruptor
                                titulo="Activo"
                                ayuda="Inactivo: no se puede asignar a nadie nuevo. Quien ya lo tiene lo conserva."
                                checked={data.is_active}
                                onChange={(v) => setData('is_active', v)}
                            />
                            <Interruptor
                                titulo="Se ofrece en el sitio"
                                ayuda="Apagado: solo tú puedes asignarlo desde el panel."
                                checked={data.is_public}
                                onChange={(v) => setData('is_public', v)}
                            />
                            <Interruptor
                                titulo="Recomendado"
                                ayuda="Se destaca entre los demás en la página de planes."
                                checked={data.is_featured}
                                onChange={(v) => setData('is_featured', v)}
                            />
                        </div>
                        <div className="border-t p-5">
                            <Field label="Orden" error={errors.sort_order} hint="De menor a mayor, de izquierda a derecha.">
                                <Input {...numero('sort_order')} className="w-28" />
                            </Field>
                        </div>
                    </Panel>

                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" size="lg" disabled={processing}>
                            {plan ? 'Guardar plan' : 'Crear plan'}
                        </Button>
                        <Button asChild variant="outline" size="lg">
                            <Link href="/plataforma/planes">Cancelar</Link>
                        </Button>
                    </div>
                </div>
            </form>
        </PlatformLayout>
    );
}

function Interruptor({ titulo, ayuda, checked, onChange }: { titulo: string; ayuda: string; checked: boolean; onChange: (valor: boolean) => void }) {
    return (
        <label className="hover:bg-accent flex cursor-pointer gap-3 rounded-lg p-2.5">
            <input type="checkbox" className="mt-0.5 size-4 shrink-0" checked={checked} onChange={(e) => onChange(e.target.checked)} />
            <span>
                <span className="block text-sm font-medium">{titulo}</span>
                <span className="text-muted-foreground block text-xs leading-relaxed">{ayuda}</span>
            </span>
        </label>
    );
}
