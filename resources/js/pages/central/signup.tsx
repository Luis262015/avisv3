import { Field } from '@/components/field';
import { PasswordInput } from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MarketingLayout from '@/layouts/marketing-layout';
import { limit, money } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type Plan } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Check, LoaderCircle } from 'lucide-react';
import { useRef, type FormEventHandler } from 'react';

interface SignupProps {
    plans: Plan[];
    selectedPlan: string;
    selectedCycle: 'monthly' | 'yearly';
    baseDomain: string;
}

type SignupForm = {
    name: string;
    slug: string;
    nit: string;
    owner_name: string;
    owner_email: string;
    phone: string;
    city: string;
    password: string;
    password_confirmation: string;
    plan: string;
    cycle: 'monthly' | 'yearly';
    terms: boolean;
};

/** «Ferretería El Tornillo S.R.L.» → «ferreteria-el-tornillo». */
function aDireccion(texto: string): string {
    return texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/\b(s\.?r\.?l\.?|s\.?a\.?|ltda\.?)\b/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 30)
        .replace(/-+$/, '');
}

export default function Signup({ plans, selectedPlan, selectedCycle, baseDomain }: SignupProps) {
    const { data, setData, post, processing, errors } = useForm<SignupForm>({
        name: '',
        slug: '',
        nit: '',
        owner_name: '',
        owner_email: '',
        phone: '',
        city: '',
        password: '',
        password_confirmation: '',
        plan: selectedPlan,
        cycle: selectedCycle,
        terms: false,
    });

    // La dirección se propone a partir del nombre hasta que alguien la toca:
    // después manda lo que haya escrito.
    const direccionTocada = useRef(false);

    const plan = plans.find((p) => p.slug === data.plan) ?? plans[0];
    const anual = data.cycle === 'yearly' && !plan.is_free;
    const hayAnual = !plan.is_free && plan.price_yearly > 0;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/registro');
    };

    return (
        <MarketingLayout sections={[{ href: '/#funciones', label: 'Funciones' }, { href: '/#planes', label: 'Planes' }]}>
            <Head title="Crear cuenta" />

            <div className="mx-auto grid max-w-6xl gap-10 px-4 pt-28 pb-20 sm:px-6 lg:grid-cols-[1.25fr_0.9fr] lg:gap-14">
                <form onSubmit={submit} noValidate className="order-2 lg:order-1">
                    <h1 className="text-3xl font-semibold sm:text-4xl">Crea la cuenta de tu empresa</h1>
                    <p className="text-muted-foreground mt-3 max-w-xl leading-relaxed">
                        En un par de minutos tendrás tu propio espacio, listo para cargar productos y abrir caja.
                    </p>

                    <fieldset className="mt-10 grid gap-5 sm:grid-cols-2">
                        <legend className="font-display mb-5 text-lg font-semibold sm:col-span-2">Tu empresa</legend>

                        <Field label="Nombre de la empresa" error={errors.name} className="sm:col-span-2">
                            <Input
                                value={data.name}
                                autoComplete="organization"
                                autoFocus
                                onChange={(e) => {
                                    const nombre = e.target.value;
                                    setData((actual) => ({
                                        ...actual,
                                        name: nombre,
                                        slug: direccionTocada.current ? actual.slug : aDireccion(nombre),
                                    }));
                                }}
                            />
                        </Field>

                        <Field
                            label="Dirección de tu sistema"
                            error={errors.slug}
                            className="sm:col-span-2"
                            hint={
                                <>
                                    Por aquí entrará tu equipo:{' '}
                                    <strong className="text-foreground font-semibold break-all">
                                        {data.slug || 'tu-empresa'}.{baseDomain}
                                    </strong>
                                    . Solo minúsculas, números y guiones.
                                </>
                            }
                        >
                            <Input
                                value={data.slug}
                                autoCapitalize="none"
                                autoCorrect="off"
                                spellCheck={false}
                                onChange={(e) => {
                                    direccionTocada.current = true;
                                    setData('slug', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''));
                                }}
                            />
                        </Field>

                        <Field label="NIT" optional error={errors.nit} hint="Lo necesitarás para facturar. Puedes agregarlo después.">
                            <Input value={data.nit} inputMode="numeric" onChange={(e) => setData('nit', e.target.value.replace(/\D/g, ''))} />
                        </Field>

                        <Field label="Ciudad" optional error={errors.city}>
                            <Input value={data.city} autoComplete="address-level2" onChange={(e) => setData('city', e.target.value)} />
                        </Field>
                    </fieldset>

                    <fieldset className="mt-10 grid gap-5 sm:grid-cols-2">
                        <legend className="font-display mb-5 text-lg font-semibold sm:col-span-2">Quién administra la cuenta</legend>

                        <Field label="Tu nombre" error={errors.owner_name}>
                            <Input value={data.owner_name} autoComplete="name" onChange={(e) => setData('owner_name', e.target.value)} />
                        </Field>

                        <Field label="Celular" optional error={errors.phone}>
                            <Input value={data.phone} type="tel" autoComplete="tel" onChange={(e) => setData('phone', e.target.value)} />
                        </Field>

                        <Field label="Correo" error={errors.owner_email} className="sm:col-span-2" hint="Con este correo vas a ingresar.">
                            <Input
                                value={data.owner_email}
                                type="email"
                                inputMode="email"
                                autoComplete="email"
                                onChange={(e) => setData('owner_email', e.target.value)}
                            />
                        </Field>

                        <Field label="Contraseña" error={errors.password} hint="Mínimo 8 caracteres.">
                            <PasswordInput value={data.password} autoComplete="new-password" onChange={(e) => setData('password', e.target.value)} />
                        </Field>

                        <Field label="Repite la contraseña" error={errors.password_confirmation}>
                            <PasswordInput
                                value={data.password_confirmation}
                                autoComplete="new-password"
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                            />
                        </Field>
                    </fieldset>

                    <div className="mt-8">
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="terms"
                                className="mt-0.5"
                                checked={data.terms}
                                onCheckedChange={(v) => setData('terms', v === true)}
                                aria-invalid={errors.terms ? true : undefined}
                                aria-describedby={errors.terms ? 'terms-error' : undefined}
                            />
                            <Label htmlFor="terms" className="leading-relaxed font-normal">
                                Confirmo que los datos son correctos y que tengo autorización para registrar esta empresa.
                            </Label>
                        </div>
                        {errors.terms && (
                            <p id="terms-error" role="alert" className="mt-2 text-sm font-medium text-red-700">
                                {errors.terms}
                            </p>
                        )}
                    </div>

                    <Button type="submit" size="lg" className="mt-8 w-full sm:w-auto sm:px-10" disabled={processing}>
                        {processing && <LoaderCircle className="animate-spin" aria-hidden="true" />}
                        {processing ? 'Preparando tu espacio…' : 'Crear mi cuenta'}
                    </Button>

                    {/* Crear la base de la empresa tarda: sin este aviso parece colgado. */}
                    <p role="status" className="text-muted-foreground mt-3 min-h-5 text-sm">
                        {processing ? 'Estamos creando tu base de datos. Puede tardar hasta un minuto; no cierres esta página.' : ''}
                    </p>

                    <p className="text-muted-foreground mt-6 text-sm">
                        ¿Tu empresa ya tiene cuenta?{' '}
                        <Link href="/ingresar" className="text-brand-teal-ink font-semibold underline underline-offset-4">
                            Ingresar
                        </Link>
                    </p>
                </form>

                {/* ── Plan elegido ─────────────────────────────────────── */}
                <aside className="order-1 lg:order-2 lg:sticky lg:top-24 lg:self-start" aria-label="Plan elegido">
                    <div className="bg-card rounded-2xl border p-6 sm:p-7">
                        <fieldset>
                            <legend className="font-display text-lg font-semibold">Elige tu plan</legend>
                            <div className="mt-4 grid gap-2.5">
                                {plans.map((p) => {
                                    const elegido = p.slug === data.plan;

                                    return (
                                        <label
                                            key={p.id}
                                            className={cn(
                                                'flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition-colors',
                                                elegido ? 'border-ring bg-accent' : 'hover:border-gray-400',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="plan"
                                                value={p.slug}
                                                checked={elegido}
                                                onChange={() => setData('plan', p.slug)}
                                                className="size-4"
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block font-semibold">{p.name}</span>
                                                <span className="text-muted-foreground block text-sm">
                                                    {p.is_free ? 'Gratis, sin vencimiento' : `${money(p.price_monthly, p.currency)} al mes`}
                                                </span>
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        </fieldset>

                        {hayAnual && (
                            <fieldset className="mt-6">
                                <legend className="text-sm font-semibold">Cómo prefieres pagar</legend>
                                <div className="mt-3 grid grid-cols-2 gap-2.5">
                                    {(['monthly', 'yearly'] as const).map((ciclo) => (
                                        <label
                                            key={ciclo}
                                            className={cn(
                                                'flex cursor-pointer flex-col rounded-xl border p-3 text-sm transition-colors',
                                                data.cycle === ciclo ? 'border-ring bg-accent' : 'hover:border-gray-400',
                                            )}
                                        >
                                            <span className="flex items-center gap-2 font-semibold">
                                                <input
                                                    type="radio"
                                                    name="cycle"
                                                    value={ciclo}
                                                    checked={data.cycle === ciclo}
                                                    onChange={() => setData('cycle', ciclo)}
                                                    className="size-4"
                                                />
                                                {ciclo === 'monthly' ? 'Cada mes' : 'Cada año'}
                                            </span>
                                            <span className="text-muted-foreground mt-1 pl-6">
                                                {money(ciclo === 'monthly' ? plan.price_monthly : plan.price_yearly, plan.currency)}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                        )}

                        <div className="mt-6 border-t pt-6" aria-live="polite">
                            <p className="font-display text-2xl font-semibold">
                                {plan.is_free ? 'Gratis' : money(anual ? plan.price_yearly : plan.price_monthly, plan.currency)}
                                {!plan.is_free && <span className="text-muted-foreground font-sans text-sm font-normal"> {anual ? 'al año' : 'al mes'}</span>}
                            </p>
                            <p className="text-brand-teal-ink mt-1 text-sm font-medium">
                                {plan.is_free
                                    ? 'No pagas nada y no vence.'
                                    : plan.trial_days > 0
                                      ? `Hoy no pagas nada: tienes ${plan.trial_days} días de prueba.`
                                      : 'Se activa cuando confirmemos tu primer pago.'}
                            </p>

                            <ul className="mt-5 space-y-2.5 text-sm">
                                {[
                                    `Tiendas: ${limit(plan.limits.stores)}`,
                                    `Usuarios: ${limit(plan.limits.users)}`,
                                    `Productos: ${limit(plan.limits.products)}`,
                                    plan.modules.includes('facturacion') ? 'Facturación electrónica SIAT' : null,
                                ]
                                    .filter((x): x is string => x !== null)
                                    .map((linea) => (
                                        <li key={linea} className="flex gap-2.5">
                                            <Check className="text-brand-teal-ink mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                            {linea}
                                        </li>
                                    ))}
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
        </MarketingLayout>
    );
}
