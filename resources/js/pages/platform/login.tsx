import { Field } from '@/components/field';
import { PasswordInput } from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler } from 'react';

type Form = { email: string; password: string; remember: boolean };

export default function PlatformLogin() {
    const { data, setData, post, processing, errors, reset } = useForm<Form>({ email: '', password: '', remember: false });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/plataforma/ingresar', { onFinish: () => reset('password') });
    };

    return (
        <AuthLayout
            title="Panel de la plataforma"
            description="Acceso para quienes administran las empresas, los planes y los cobros de AVIS."
            aside={
                <>
                    <p className="font-display text-[2rem] leading-[1.15] font-semibold tracking-[-0.02em] text-balance">Todas las empresas, sus planes y sus pagos.</p>
                    <p className="mt-6 text-[0.9375rem] leading-relaxed text-white/80">
                        Este acceso no es el de ninguna empresa. Si buscas entrar a tu sistema, vuelve al inicio y elige «Ingresar».
                    </p>
                </>
            }
        >
            <Head title="Plataforma" />

            <form onSubmit={submit} noValidate className="grid gap-5">
                <Field label="Correo" error={errors.email}>
                    <Input
                        type="email"
                        inputMode="email"
                        autoComplete="username"
                        autoFocus
                        className="h-11"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </Field>

                <Field label="Contraseña" error={errors.password}>
                    <PasswordInput autoComplete="current-password" className="h-11" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                </Field>

                <div className="flex items-center gap-3">
                    <Checkbox id="remember" checked={data.remember} onCheckedChange={(v) => setData('remember', v === true)} />
                    <Label htmlFor="remember" className="font-normal">
                        Mantener la sesión en este equipo
                    </Label>
                </div>

                <Button type="submit" size="lg" className="mt-2 w-full" disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" aria-hidden="true" />}
                    Ingresar al panel
                </Button>
            </form>
        </AuthLayout>
    );
}
