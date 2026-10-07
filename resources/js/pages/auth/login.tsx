import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { PasswordInput } from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

// `type` y no `interface`: useForm exige una firma de índice que las interfaces no traen.
type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
};

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Ingresa a tu cuenta" description="Usa el correo y la contraseña que te dio el administrador de tu empresa.">
            <Head title="Ingresar" />

            {status && (
                <div role="status" className="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
                    {status}
                </div>
            )}

            {/* `noValidate`: los errores los pinta el formulario, junto a cada
                campo y en español; los globos del navegador no se pueden
                estilizar ni los lee bien un lector de pantalla. */}
            <form className="flex flex-col gap-5" onSubmit={submit} noValidate>
                <div className="grid gap-2">
                    <Label htmlFor="email">Correo</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autoFocus
                        autoComplete="username"
                        inputMode="email"
                        className="h-11"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="tu@correo.com"
                        aria-invalid={errors.email ? true : undefined}
                        aria-describedby={errors.email ? 'email-error' : undefined}
                    />
                    <InputError id="email-error" message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <div className="flex items-baseline justify-between gap-4">
                        <Label htmlFor="password">Contraseña</Label>
                        {canResetPassword && (
                            <TextLink href={route('password.request')} className="text-sm">
                                ¿La olvidaste?
                            </TextLink>
                        )}
                    </div>
                    <PasswordInput
                        id="password"
                        required
                        autoComplete="current-password"
                        className="h-11"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        aria-invalid={errors.password ? true : undefined}
                        aria-describedby={errors.password ? 'password-error' : undefined}
                    />
                    <InputError id="password-error" message={errors.password} />
                </div>

                <div className="flex items-center gap-3">
                    <Checkbox id="remember" checked={data.remember} onCheckedChange={(valor) => setData('remember', valor === true)} />
                    <Label htmlFor="remember" className="font-normal">
                        Mantener la sesión en este equipo
                    </Label>
                </div>

                <Button type="submit" size="lg" className="mt-2 w-full" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" aria-hidden="true" />}
                    {processing ? 'Ingresando…' : 'Ingresar'}
                </Button>
            </form>

            <p className="text-muted-foreground mt-8 text-sm leading-relaxed">
                ¿No tienes usuario? Las cuentas las crea el administrador de tu empresa desde «Usuarios».
            </p>
        </AuthLayout>
    );
}
