import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Employee {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string;
    full_name: string;
    position: string;
    status: 'active' | 'on_leave' | 'suspended' | 'terminated';
    base_salary: string;
    department: { id: number; name: string } | null;
}

const statusBadge: Record<Employee['status'], { label: string; cls: string }> = {
    active: { label: 'Activo', cls: 'bg-green-100 text-green-700' },
    on_leave: { label: 'En licencia', cls: 'bg-blue-100 text-blue-700' },
    suspended: { label: 'Suspendido', cls: 'bg-yellow-100 text-yellow-700' },
    terminated: { label: 'Baja', cls: 'bg-gray-100 text-gray-500' },
};

interface Props {
    employees: PaginatedData<Employee>;
    departments: { id: number; name: string }[];
    filters: { search?: string; status?: string; department_id?: string };
}

export default function EmployeesIndex({ employees, departments, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (extra: Record<string, string>) => {
        router.get('/admin/employees', { ...filters, search, ...extra }, { preserveState: true, replace: true });
    };

    const destroy = (id: number) => {
        if (confirm('¿Eliminar empleado?')) router.delete(`/admin/employees/${id}`);
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Empleados', href: '/admin/employees' }]}>
            <FlashMessage />
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Empleados</h1>
                    <Button asChild>
                        <Link href="/admin/employees/create"><Plus className="mr-2 h-4 w-4" /> Nuevo Empleado</Link>
                    </Button>
                </div>

                <div className="mb-4 flex flex-wrap gap-2">
                    <form onSubmit={(e) => { e.preventDefault(); apply({}); }} className="flex gap-2">
                        <Input className="w-64" placeholder="Buscar nombre, código o cargo…" value={search} onChange={(e) => setSearch(e.target.value)} />
                        <Button type="submit" variant="outline">Buscar</Button>
                    </form>
                    <select className="rounded-md border px-3 py-2 text-sm" value={filters.status ?? ''} onChange={(e) => apply({ status: e.target.value })}>
                        <option value="">Todos los estados</option>
                        <option value="active">Activos</option>
                        <option value="on_leave">En licencia</option>
                        <option value="suspended">Suspendidos</option>
                        <option value="terminated">Baja</option>
                    </select>
                    <select className="rounded-md border px-3 py-2 text-sm" value={filters.department_id ?? ''} onChange={(e) => apply({ department_id: e.target.value })}>
                        <option value="">Todas las áreas</option>
                        {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                    </select>
                </div>

                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Código</th>
                                <th className="px-4 py-3">Nombre</th>
                                <th className="px-4 py-3">Cargo</th>
                                <th className="px-4 py-3">Área</th>
                                <th className="px-4 py-3 text-right">Salario</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {employees.data.map((e) => (
                                <tr key={e.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-mono text-xs text-gray-500">{e.employee_code}</td>
                                    <td className="px-4 py-3 font-medium">{e.full_name}</td>
                                    <td className="px-4 py-3 text-gray-500">{e.position}</td>
                                    <td className="px-4 py-3 text-gray-500">{e.department?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-right text-gray-500">Bs {Number(e.base_salary).toFixed(2)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusBadge[e.status].cls}`}>
                                            {statusBadge[e.status].label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" asChild><Link href={`/admin/employees/${e.id}`}><Eye className="h-4 w-4" /></Link></Button>
                                            <Button variant="ghost" size="sm" asChild><Link href={`/admin/employees/${e.id}/edit`}><Pencil className="h-4 w-4" /></Link></Button>
                                            <Button variant="ghost" size="sm" onClick={() => destroy(e.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {employees.data.length === 0 && (
                                <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">Sin empleados.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {employees.last_page > 1 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {employees.links.map((l, i) => (
                            <button
                                key={i}
                                disabled={!l.url}
                                onClick={() => l.url && router.visit(l.url, { preserveState: true })}
                                className={`rounded border px-3 py-1 text-sm ${l.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-600'} ${!l.url ? 'opacity-40' : ''}`}
                                dangerouslySetInnerHTML={{ __html: l.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
