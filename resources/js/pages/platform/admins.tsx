import { Field } from '@/components/field';
import { PasswordInput } from '@/components/password-input';
import { DataTable, Panel } from '@/components/platform/ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PlatformLayout from '@/layouts/platform-layout';
import { shortDate } from '@/lib/format';
import { router, useForm, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface Admin {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

export default function PlatformAdmins({ admins }: { admins: Admin[] }) {
    const yo = usePage<{ platformAdmin: { id: number } | null }>().props.platformAdmin;
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', email: '', password: '', password_confirmation: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/plataforma/administradores', { preserveScroll: true, onSuccess: () => reset() });
    };

    const quitar = (admin: Admin) => {
        if (confirm(`¿Quitar el acceso de ${admin.name}?`)) {
            router.delete(`/plataforma/administradores/${admin.id}`, { preserveScroll: true });
        }
    };

    return (
        <PlatformLayout title="Administradores" description="Quiénes pueden entrar a este panel. No son usuarios de ninguna empresa.">
            <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                <Panel>
                    <DataTable label="Administradores de la plataforma">
                        <thead>
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Correo</th>
                                <th scope="col">Desde</th>
                                <th scope="col">
                                    <span className="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {admins.map((admin) => (
                                <tr key={admin.id}>
                                    <td className="font-medium">
                                        {admin.name}
                                        {admin.id === yo?.id && <span className="text-muted-foreground font-normal"> (tú)</span>}
                                    </td>
                                    <td>{admin.email}</td>
                                    <td>{shortDate(admin.created_at)}</td>
                                    <td className="text-right">
                                        {admin.id !== yo?.id && (
                                            <Button variant="ghost" size="icon" className="size-9" onClick={() => quitar(admin)} aria-label={`Quitar a ${admin.name}`}>
                                                <Trash2 className="text-red-600" aria-hidden="true" />
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </DataTable>
                </Panel>

                <Panel title="Agregar administrador">
                    <form onSubmit={submit} noValidate className="grid gap-5 p-5">
                        <Field label="Nombre" error={errors.name}>
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        </Field>
                        <Field label="Correo" error={errors.email}>
                            <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        </Field>
                        <Field label="Contraseña" error={errors.password} hint="Mínimo 8 caracteres.">
                            <PasswordInput autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                        </Field>
                        <Field label="Repite la contraseña" error={errors.password_confirmation}>
                            <PasswordInput autoComplete="new-password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                        </Field>
                        <Button type="submit" disabled={processing} className="justify-self-start">
                            Agregar
                        </Button>
                    </form>
                </Panel>
            </div>
        </PlatformLayout>
    );
}
