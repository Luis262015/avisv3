import { Field } from '@/components/field';
import { FlashMessage } from '@/components/flash-message';
import { PasswordInput } from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

interface Props {
    user: { id: number; name: string; email: string; role: string | null } | null;
    roles: { value: string; label: string }[];
}

const QUE_PUEDE: Record<string, string> = {
    admin: 'Todo: configuración, usuarios, facturación y reportes.',
    operador: 'Productos, compras, inventario, ventas y caja. No toca la configuración.',
    vendedor: 'Solo vender y manejar su turno de caja.',
};

export default function UserForm({ user, roles }: Props) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? 'vendedor',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (user) {
            put(`/admin/users/${user.id}`);
        } else {
            post('/admin/users');
        }
    };

    const titulo = user ? `Editar a ${user.name}` : 'Nuevo usuario';

    return (
        <AppLayout breadcrumbs={[{ title: 'Usuarios', href: '/admin/users' }, { title: user ? 'Editar' : 'Nuevo', href: '' }]}>
            <Head title={titulo} />
            <FlashMessage />

            <form onSubmit={submit} noValidate className="mx-auto grid w-full max-w-2xl gap-6 p-4 md:p-6">
                <h1 className="text-2xl font-semibold">{titulo}</h1>

                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label="Nombre" error={errors.name}>
                        <Input value={data.name} autoComplete="off" autoFocus={!user} onChange={(e) => setData('name', e.target.value)} />
                    </Field>
                    <Field label="Correo" error={errors.email} hint="Con este correo ingresa al sistema.">
                        <Input type="email" autoComplete="off" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    </Field>
                </div>

                <fieldset>
                    <legend className="mb-3 text-sm font-medium">Rol</legend>
                    <div className="grid gap-2.5">
                        {roles.map((rol) => (
                            <label
                                key={rol.value}
                                className={cn('flex cursor-pointer gap-3 rounded-xl border p-4 transition-colors', data.role === rol.value ? 'border-ring bg-accent' : 'bg-card hover:border-gray-400')}
                            >
                                <input type="radio" name="role" className="mt-1 size-4" checked={data.role === rol.value} onChange={() => setData('role', rol.value)} />
                                <span>
                                    <span className="block font-semibold">{rol.label}</span>
                                    <span className="text-muted-foreground block text-sm">{QUE_PUEDE[rol.value]}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {errors.role && (
                        <p role="alert" className="mt-2 text-sm font-medium text-red-700">
                            {errors.role}
                        </p>
                    )}
                </fieldset>

                <div className="grid gap-5 sm:grid-cols-2">
                    <Field
                        label={user ? 'Contraseña nueva' : 'Contraseña'}
                        optional={Boolean(user)}
                        error={errors.password}
                        hint={user ? 'Déjala vacía para conservar la actual.' : 'Mínimo 8 caracteres.'}
                    >
                        <PasswordInput autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                    </Field>
                    <Field label="Repite la contraseña" optional={Boolean(user)} error={errors.password_confirmation}>
                        <PasswordInput autoComplete="new-password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                    </Field>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button type="submit" disabled={processing}>
                        {user ? 'Guardar cambios' : 'Crear usuario'}
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/admin/users">Cancelar</Link>
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
