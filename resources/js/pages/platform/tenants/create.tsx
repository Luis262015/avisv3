import { Field, NativeSelect } from '@/components/field';
import { PasswordInput } from '@/components/password-input';
import { Panel } from '@/components/platform/ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PlatformLayout from '@/layouts/platform-layout';
import { money } from '@/lib/format';
import { type Plan } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler } from 'react';

type Form = {
    name: string;
    slug: string;
    nit: string;
    owner_name: string;
    owner_email: string;
    phone: string;
    city: string;
    password: string;
    plan_id: string;
    cycle: 'monthly' | 'yearly';
};

export default function TenantCreate({ plans, baseDomain, tenancyEnabled }: { plans: Plan[]; baseDomain: string; tenancyEnabled: boolean }) {
    const { data, setData, post, processing, errors } = useForm<Form>({
        name: '',
        slug: '',
        nit: '',
        owner_name: '',
        owner_email: '',
        phone: '',
        city: '',
        password: '',
        plan_id: String(plans[0]?.id ?? ''),
        cycle: 'monthly',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/plataforma/empresas');
    };

    return (
        <PlatformLayout title="Nueva empresa" description="Crea su espacio, su primer administrador y su suscripción en un solo paso.">
            {!tenancyEnabled && (
                <p role="alert" className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                    El arrendamiento está apagado en este servidor (<code>TENANCY_ENABLED=false</code>). La empresa se creará, pero nadie podrá entrar por su
                    dirección hasta que lo actives.
                </p>
            )}

            <form onSubmit={submit} noValidate className="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                <Panel title="Datos de la empresa">
                    <div className="grid gap-5 p-5 sm:grid-cols-2">
                        <Field label="Nombre de la empresa" error={errors.name} className="sm:col-span-2">
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus />
                        </Field>
                        <Field
                            label="Dirección"
                            error={errors.slug}
                            className="sm:col-span-2"
                            hint={
                                <>
                                    Entrará por{' '}
                                    <strong className="text-foreground font-semibold">
                                        {data.slug || 'empresa'}.{baseDomain}
                                    </strong>
                                    . No se puede cambiar después.
                                </>
                            }
                        >
                            <Input
                                value={data.slug}
                                autoCapitalize="none"
                                spellCheck={false}
                                onChange={(e) => setData('slug', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))}
                            />
                        </Field>
                        <Field label="NIT" optional error={errors.nit}>
                            <Input value={data.nit} inputMode="numeric" onChange={(e) => setData('nit', e.target.value.replace(/\D/g, ''))} />
                        </Field>
                        <Field label="Ciudad" optional error={errors.city}>
                            <Input value={data.city} onChange={(e) => setData('city', e.target.value)} />
                        </Field>
                    </div>

                    <div className="grid gap-5 border-t p-5 sm:grid-cols-2">
                        <h3 className="font-sans text-sm font-semibold tracking-normal sm:col-span-2">Su administrador</h3>
                        <Field label="Nombre" error={errors.owner_name}>
                            <Input value={data.owner_name} onChange={(e) => setData('owner_name', e.target.value)} />
                        </Field>
                        <Field label="Celular" optional error={errors.phone}>
                            <Input type="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                        </Field>
                        <Field label="Correo" error={errors.owner_email}>
                            <Input type="email" value={data.owner_email} onChange={(e) => setData('owner_email', e.target.value)} />
                        </Field>
                        <Field label="Contraseña inicial" error={errors.password} hint="Compártela por un canal seguro; podrá cambiarla al entrar.">
                            <PasswordInput autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                        </Field>
                    </div>
                </Panel>

                <div className="grid content-start gap-6">
                    <Panel title="Suscripción">
                        <div className="grid gap-5 p-5">
                            <Field label="Plan" error={errors.plan_id}>
                                <NativeSelect value={data.plan_id} onChange={(e) => setData('plan_id', e.target.value)}>
                                    {plans.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} — {p.is_free ? 'gratis' : `${money(p.price_monthly, p.currency)} al mes`}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Periodo de pago" error={errors.cycle} hint="Empieza con los días de prueba del plan, si los tiene.">
                                <NativeSelect value={data.cycle} onChange={(e) => setData('cycle', e.target.value as Form['cycle'])}>
                                    <option value="monthly">Mensual</option>
                                    <option value="yearly">Anual</option>
                                </NativeSelect>
                            </Field>
                        </div>
                    </Panel>

                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" size="lg" disabled={processing}>
                            {processing && <LoaderCircle className="animate-spin" aria-hidden="true" />}
                            {processing ? 'Creando su espacio…' : 'Crear empresa'}
                        </Button>
                        <Button asChild variant="outline" size="lg">
                            <Link href="/plataforma/empresas">Cancelar</Link>
                        </Button>
                    </div>
                    <p role="status" className="text-muted-foreground text-sm">
                        {processing ? 'Se está creando su base de datos. Puede tardar hasta un minuto.' : ''}
                    </p>
                </div>
            </form>
        </PlatformLayout>
    );
}
