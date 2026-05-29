import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { router, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface Employee { id: number; first_name: string; last_name: string }
interface Department {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    manager_id: number | null;
    is_active: boolean;
    employees_count: number;
    manager: { id: number; first_name: string; last_name: string } | null;
}

const selectClass = 'w-full rounded-md border px-2 py-1.5 text-sm';

export default function DepartmentsIndex({ departments, employees }: { departments: Department[]; employees: Employee[] }) {
    const [editingId, setEditingId] = useState<number | null>(null);

    const create = useForm<any>({ name: '', code: '', description: '', manager_id: null, is_active: true });
    const edit = useForm<any>({ name: '', code: '', description: '', manager_id: null, is_active: true });

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        create.post('/admin/departments', { onSuccess: () => create.reset() });
    };

    const startEdit = (d: Department) => {
        setEditingId(d.id);
        edit.setData({ name: d.name, code: d.code ?? '', description: d.description ?? '', manager_id: d.manager_id, is_active: d.is_active });
    };

    const submitEdit = (id: number) => {
        edit.put(`/admin/departments/${id}`, { onSuccess: () => setEditingId(null) });
    };

    const remove = (id: number) => {
        if (confirm('¿Eliminar área? Los empleados quedarán sin asignar.')) router.delete(`/admin/departments/${id}`);
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Áreas', href: '/admin/departments' }]}>
            <FlashMessage />
            <div className="p-6 space-y-6">
                <h1 className="text-2xl font-bold">Áreas / Departamentos</h1>

                <form onSubmit={submitCreate} className="rounded-lg border bg-white p-4 shadow-sm">
                    <h2 className="mb-3 font-semibold text-gray-700">Nueva área</h2>
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div>
                            <Label>Nombre *</Label>
                            <Input value={create.data.name} onChange={(e) => create.setData('name', e.target.value)} />
                            {create.errors.name && <p className="mt-1 text-xs text-red-500">{create.errors.name}</p>}
                        </div>
                        <div>
                            <Label>Código</Label>
                            <Input value={create.data.code} onChange={(e) => create.setData('code', e.target.value)} />
                        </div>
                        <div>
                            <Label>Responsable</Label>
                            <select className={selectClass} value={create.data.manager_id ?? ''} onChange={(e) => create.setData('manager_id', e.target.value || null)}>
                                <option value="">—</option>
                                {employees.map((m) => <option key={m.id} value={m.id}>{m.first_name} {m.last_name}</option>)}
                            </select>
                        </div>
                        <div className="flex items-end">
                            <Button type="submit" disabled={create.processing} className="w-full"><Plus className="mr-2 h-4 w-4" /> Agregar</Button>
                        </div>
                    </div>
                </form>

                <div className="rounded-lg border bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Nombre</th>
                                <th className="px-4 py-3">Código</th>
                                <th className="px-4 py-3">Responsable</th>
                                <th className="px-4 py-3 text-center">Empleados</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {departments.map((d) => editingId === d.id ? (
                                <tr key={d.id} className="bg-blue-50/40">
                                    <td className="px-4 py-2"><Input value={edit.data.name} onChange={(e) => edit.setData('name', e.target.value)} /></td>
                                    <td className="px-4 py-2"><Input value={edit.data.code} onChange={(e) => edit.setData('code', e.target.value)} /></td>
                                    <td className="px-4 py-2">
                                        <select className={selectClass} value={edit.data.manager_id ?? ''} onChange={(e) => edit.setData('manager_id', e.target.value || null)}>
                                            <option value="">—</option>
                                            {employees.map((m) => <option key={m.id} value={m.id}>{m.first_name} {m.last_name}</option>)}
                                        </select>
                                    </td>
                                    <td className="px-4 py-2 text-center">{d.employees_count}</td>
                                    <td className="px-4 py-2">
                                        <label className="flex items-center gap-1 text-xs">
                                            <input type="checkbox" checked={edit.data.is_active} onChange={(e) => edit.setData('is_active', e.target.checked)} /> Activo
                                        </label>
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" onClick={() => submitEdit(d.id)}><Check className="h-4 w-4 text-green-600" /></Button>
                                            <Button variant="ghost" size="sm" onClick={() => setEditingId(null)}><X className="h-4 w-4" /></Button>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                <tr key={d.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium">{d.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{d.code ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{d.manager ? `${d.manager.first_name} ${d.manager.last_name}` : '—'}</td>
                                    <td className="px-4 py-3 text-center text-gray-500">{d.employees_count}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${d.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {d.is_active ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" onClick={() => startEdit(d)}><Pencil className="h-4 w-4" /></Button>
                                            <Button variant="ghost" size="sm" onClick={() => remove(d.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {departments.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400">Sin áreas registradas.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
