import { FlashMessage } from '@/components/flash-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string | null;
    role_label: string;
}

const TONO: Record<string, 'info' | 'success' | 'muted'> = { admin: 'info', operador: 'success', vendedor: 'muted' };

export default function UsersIndex({ users }: { users: PaginatedData<UserRow> }) {
    const { auth, tenant } = usePage<SharedData>().props;
    const cupo = tenant?.subscription?.plan?.limits.users ?? null;

    const eliminar = (user: UserRow) => {
        if (confirm(`¿Eliminar a ${user.name}? Perderá el acceso al sistema.`)) {
            router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Usuarios', href: '/admin/users' }]}>
            <Head title="Usuarios" />
            <FlashMessage />

            <div className="p-4 md:p-6">
                <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Usuarios</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Quiénes entran al sistema y qué pueden hacer.
                            {cupo !== null && ` Tu plan permite ${cupo}; llevas ${users.total}.`}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/users/create">
                            <Plus aria-hidden="true" /> Nuevo usuario
                        </Link>
                    </Button>
                </div>

                <div className="bg-card relative overflow-x-auto rounded-xl border" role="region" aria-label="Usuarios" tabIndex={0}>
                    <table className="w-full min-w-[34rem] text-sm">
                        <thead className="text-left text-xs text-gray-500">
                            <tr className="border-b">
                                <th scope="col" className="px-5 py-3 font-semibold">
                                    Nombre
                                </th>
                                <th scope="col" className="px-5 py-3 font-semibold">
                                    Correo
                                </th>
                                <th scope="col" className="px-5 py-3 font-semibold">
                                    Rol
                                </th>
                                <th scope="col" className="px-5 py-3">
                                    <span className="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {users.data.map((user) => (
                                <tr key={user.id} className="hover:bg-gray-50">
                                    <td className="px-5 py-3.5 font-medium">
                                        {user.name}
                                        {user.id === auth.user.id && <span className="text-muted-foreground font-normal"> (tú)</span>}
                                    </td>
                                    <td className="px-5 py-3.5">{user.email}</td>
                                    <td className="px-5 py-3.5">
                                        <Badge variant={TONO[user.role ?? ''] ?? 'muted'}>{user.role_label}</Badge>
                                    </td>
                                    <td className="px-5 py-2 text-right whitespace-nowrap">
                                        <Button asChild variant="ghost" size="icon" className="size-9">
                                            <Link href={`/admin/users/${user.id}/edit`} aria-label={`Editar a ${user.name}`}>
                                                <Pencil aria-hidden="true" />
                                            </Link>
                                        </Button>
                                        {user.id !== auth.user.id && (
                                            <Button variant="ghost" size="icon" className="size-9" onClick={() => eliminar(user)} aria-label={`Eliminar a ${user.name}`}>
                                                <Trash2 className="text-red-600" aria-hidden="true" />
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
