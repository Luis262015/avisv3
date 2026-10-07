import { Field } from '@/components/field';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler } from 'react';

/**
 * Cada empresa entra por su propia dirección. Desde el sitio público primero
 * hay que saber cuál es; de ahí se pasa a su pantalla de ingreso.
 */
export default function Access({ baseDomain }: { baseDomain: string }) {
    const { data, setData, post, processing, errors } = useForm({ slug: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/ingresar');
    };

    return (
        <AuthLayout title="¿A qué empresa quieres entrar?" description="Escribe la dirección que eligieron al crear la cuenta. Te llevamos a su pantalla de ingreso.">
            <Head title="Ingresar" />

            <form onSubmit={submit} noValidate className="grid gap-5">
                <Field
                    label="Dirección de la empresa"
                    error={errors.slug}
                    hint={
                        <>
                            Es la primera parte de{' '}
                            <strong className="text-foreground font-semibold">
                                {data.slug || 'tu-empresa'}.{baseDomain}
                            </strong>
                        </>
                    }
                >
                    <Input
                        value={data.slug}
                        autoFocus
                        autoCapitalize="none"
                        autoCorrect="off"
                        spellCheck={false}
                        className="h-11"
                        placeholder="tu-empresa"
                        onChange={(e) => setData('slug', e.target.value)}
                    />
                </Field>

                <Button type="submit" size="lg" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" aria-hidden="true" />}
                    Continuar
                </Button>
            </form>

            <p className="text-muted-foreground mt-8 text-sm leading-relaxed">
                ¿Tu empresa aún no usa AVIS? <TextLink href="/registro">Crea su cuenta</TextLink>
            </p>
        </AuthLayout>
    );
}
