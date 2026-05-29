import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Link, router, useForm } from '@inertiajs/react';
import { Award, CalendarClock, Download, FileText, GraduationCap, Pencil, Plus, Trash2, Users } from 'lucide-react';
import { useState } from 'react';

const selectClass = 'w-full rounded-md border px-3 py-2 text-sm';
const fmt = (v: any) => `Bs ${Number(v ?? 0).toFixed(2)}`;
const date = (v: string | null) => (v ? v.substring(0, 10) : '—');

const contractLabels: Record<string, string> = {
    indefinite: 'Indefinido', fixed_term: 'Plazo fijo', part_time: 'Medio tiempo', intern: 'Pasante', services: 'Servicios',
};
const statusLabels: Record<string, string> = {
    active: 'Activo', on_leave: 'En licencia', suspended: 'Suspendido', terminated: 'Baja',
};
const docTypes: Record<string, string> = {
    contract: 'Contrato', id_copy: 'Copia CI', certificate: 'Certificado', medical_exam: 'Examen médico', affiliation: 'Afiliación', other: 'Otro',
};
const incidentTypes: Record<string, string> = {
    warning: 'Llamada de atención', suspension: 'Suspensión', memo: 'Memorándum', recognition: 'Reconocimiento', complaint: 'Queja', other: 'Otro',
};

const TABS = ['Información', 'Asistencia', 'Nómina', 'Capacitación', 'Documentos', 'Relaciones laborales'] as const;
type Tab = (typeof TABS)[number];

export default function EmployeeShow({ employee, stats }: { employee: any; stats: any }) {
    const [tab, setTab] = useState<Tab>('Información');

    return (
        <AppLayout breadcrumbs={[{ title: 'Empleados', href: '/admin/employees' }, { title: employee.full_name, href: '' }]}>
            <FlashMessage />
            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold">{employee.full_name}</h1>
                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">{statusLabels[employee.status]}</span>
                        </div>
                        <p className="mt-1 text-gray-500">{employee.position}{employee.department ? ` · ${employee.department.name}` : ''} · <span className="font-mono text-xs">{employee.employee_code}</span></p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={`/admin/employees/${employee.id}/edit`}><Pencil className="mr-2 h-4 w-4" /> Editar</Link>
                    </Button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    {[
                        { label: 'Antigüedad', value: `${stats.years_of_service} años`, icon: CalendarClock, color: 'text-blue-600' },
                        { label: 'Documentos', value: stats.documents, icon: FileText, color: 'text-indigo-600' },
                        { label: 'Capacitaciones', value: stats.trainings, icon: GraduationCap, color: 'text-green-600' },
                        { label: 'Registros laborales', value: stats.incidents, icon: Award, color: 'text-amber-600' },
                    ].map((s) => (
                        <div key={s.label} className="flex items-center gap-3 rounded-lg border bg-white p-4 shadow-sm">
                            <s.icon className={`h-7 w-7 ${s.color}`} />
                            <div>
                                <p className="text-xs uppercase text-gray-500">{s.label}</p>
                                <p className={`text-xl font-bold ${s.color}`}>{s.value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Tabs */}
                <div className="flex flex-wrap gap-1 border-b">
                    {TABS.map((t) => (
                        <button key={t} onClick={() => setTab(t)}
                            className={`border-b-2 px-4 py-2 text-sm font-medium ${tab === t ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                            {t}
                        </button>
                    ))}
                </div>

                {tab === 'Información' && <InfoTab employee={employee} />}
                {tab === 'Asistencia' && <AttendanceTab employee={employee} />}
                {tab === 'Nómina' && <PayrollTab employee={employee} />}
                {tab === 'Capacitación' && <TrainingTab employee={employee} />}
                {tab === 'Documentos' && <DocumentsTab employee={employee} />}
                {tab === 'Relaciones laborales' && <IncidentsTab employee={employee} />}
            </div>
        </AppLayout>
    );
}

function Row({ label, value }: { label: string; value: any }) {
    return (
        <p className="text-sm text-gray-600"><span className="font-medium text-gray-700">{label}:</span> {value ?? '—'}</p>
    );
}

function InfoTab({ employee }: { employee: any }) {
    return (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div className="rounded-lg border bg-white p-4 shadow-sm space-y-2">
                <h2 className="mb-1 font-semibold text-gray-700">Personal</h2>
                <Row label="Documento" value={`${(employee.document_type ?? '').toUpperCase()} ${employee.document_number ?? ''}`} />
                <Row label="Nacimiento" value={date(employee.birth_date)} />
                <Row label="Nacionalidad" value={employee.nationality} />
                <Row label="Teléfono" value={employee.phone} />
                <Row label="Email" value={employee.email} />
                <Row label="Dirección" value={employee.address} />
                <Row label="Emergencia" value={employee.emergency_contact_name ? `${employee.emergency_contact_name} (${employee.emergency_contact_phone ?? '—'})` : null} />
            </div>
            <div className="rounded-lg border bg-white p-4 shadow-sm space-y-2">
                <h2 className="mb-1 font-semibold text-gray-700">Laboral</h2>
                <Row label="Cargo" value={employee.position} />
                <Row label="Área" value={employee.department?.name} />
                <Row label="Ingreso" value={date(employee.hire_date)} />
                <Row label="Baja" value={date(employee.termination_date)} />
                <Row label="Contrato" value={contractLabels[employee.contract_type]} />
                <Row label="Salario base" value={fmt(employee.base_salary)} />
                <Row label="Acceso al sistema" value={employee.user?.name} />
            </div>
            <div className="rounded-lg border bg-white p-4 shadow-sm space-y-2">
                <h2 className="mb-1 font-semibold text-gray-700">Nómina</h2>
                <Row label="Banco" value={employee.bank_name} />
                <Row label="Cuenta" value={employee.bank_account} />
                <Row label="AFP" value={employee.afp_name} />
                <Row label="N° AFP" value={employee.afp_number} />
                <Row label="CNS (CUNS)" value={employee.cuns} />
            </div>
            {employee.notes && (
                <div className="md:col-span-3 rounded-lg border bg-white p-4 shadow-sm">
                    <h2 className="mb-1 font-semibold text-gray-700">Notas</h2>
                    <p className="text-sm text-gray-600">{employee.notes}</p>
                </div>
            )}
        </div>
    );
}

function AttendanceTab({ employee }: { employee: any }) {
    const rows = employee.attendances ?? [];
    return (
        <Card title="Asistencia reciente" icon={CalendarClock}>
            {rows.length === 0 ? <Empty text="Sin registros de asistencia." /> : (
                <SimpleTable head={['Fecha', 'Entrada', 'Salida', 'Horas', 'H. extra', 'Estado']}>
                    {rows.map((a: any) => (
                        <tr key={a.id} className="hover:bg-gray-50">
                            <td className="px-4 py-2">{date(a.date)}</td>
                            <td className="px-4 py-2">{a.check_in ?? '—'}</td>
                            <td className="px-4 py-2">{a.check_out ?? '—'}</td>
                            <td className="px-4 py-2">{Number(a.worked_hours).toFixed(1)}</td>
                            <td className="px-4 py-2">{Number(a.overtime_hours).toFixed(1)}</td>
                            <td className="px-4 py-2">{a.status}</td>
                        </tr>
                    ))}
                </SimpleTable>
            )}
        </Card>
    );
}

function PayrollTab({ employee }: { employee: any }) {
    const rows = employee.payroll_items ?? [];
    return (
        <Card title="Boletas de pago" icon={Users}>
            {rows.length === 0 ? <Empty text="Sin boletas registradas." /> : (
                <SimpleTable head={['Periodo', 'Ganado', 'AFP', 'RC-IVA', 'Deducciones', 'Líquido', 'Estado']}>
                    {rows.map((i: any) => (
                        <tr key={i.id} className="hover:bg-gray-50">
                            <td className="px-4 py-2">{i.payroll?.label}</td>
                            <td className="px-4 py-2">{fmt(i.gross_salary)}</td>
                            <td className="px-4 py-2">{fmt(i.afp_deduction)}</td>
                            <td className="px-4 py-2">{fmt(i.rc_iva_deduction)}</td>
                            <td className="px-4 py-2">{fmt(i.total_deductions)}</td>
                            <td className="px-4 py-2 font-semibold">{fmt(i.net_salary)}</td>
                            <td className="px-4 py-2">{i.payroll?.status}</td>
                        </tr>
                    ))}
                </SimpleTable>
            )}
        </Card>
    );
}

function TrainingTab({ employee }: { employee: any }) {
    const rows = employee.trainings ?? [];
    return (
        <Card title="Capacitaciones" icon={GraduationCap}>
            {rows.length === 0 ? <Empty text="Sin capacitaciones." /> : (
                <SimpleTable head={['Curso', 'Modalidad', 'Estado', 'Nota', 'Completado']}>
                    {rows.map((t: any) => (
                        <tr key={t.id} className="hover:bg-gray-50">
                            <td className="px-4 py-2"><Link href={`/admin/trainings/${t.id}`} className="text-blue-600 hover:underline">{t.title}</Link></td>
                            <td className="px-4 py-2">{t.modality}</td>
                            <td className="px-4 py-2">{t.pivot?.status}</td>
                            <td className="px-4 py-2">{t.pivot?.score ?? '—'}</td>
                            <td className="px-4 py-2">{date(t.pivot?.completed_at)}</td>
                        </tr>
                    ))}
                </SimpleTable>
            )}
        </Card>
    );
}

function DocumentsTab({ employee }: { employee: any }) {
    const { data, setData, post, processing, errors, reset } = useForm<any>({
        type: 'contract', name: '', file: null, issued_at: '', expires_at: '', notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/employees/${employee.id}/documents`, { forceFormData: true, onSuccess: () => reset() });
    };

    const remove = (id: number) => {
        if (confirm('¿Eliminar documento?')) router.delete(`/admin/employees/${employee.id}/documents/${id}`);
    };

    return (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div className="lg:col-span-2">
                <Card title="Documentos del empleado" icon={FileText}>
                    {(employee.documents ?? []).length === 0 ? <Empty text="Sin documentos." /> : (
                        <SimpleTable head={['Tipo', 'Nombre', 'Emisión', 'Vencimiento', '']}>
                            {employee.documents.map((d: any) => {
                                const expired = d.expires_at && new Date(d.expires_at) < new Date();
                                return (
                                    <tr key={d.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-2">{docTypes[d.type]}</td>
                                        <td className="px-4 py-2 font-medium">{d.name}</td>
                                        <td className="px-4 py-2">{date(d.issued_at)}</td>
                                        <td className={`px-4 py-2 ${expired ? 'font-semibold text-red-600' : ''}`}>{date(d.expires_at)}</td>
                                        <td className="px-4 py-2">
                                            <div className="flex justify-end gap-1">
                                                {d.file_path && (
                                                    <a href={`/admin/employees/${employee.id}/documents/${d.id}/download`}>
                                                        <Button variant="ghost" size="sm"><Download className="h-4 w-4" /></Button>
                                                    </a>
                                                )}
                                                <Button variant="ghost" size="sm" onClick={() => remove(d.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </SimpleTable>
                    )}
                </Card>
            </div>
            <form onSubmit={submit} className="rounded-lg border bg-white p-4 shadow-sm space-y-3">
                <h2 className="font-semibold text-gray-700">Agregar documento</h2>
                <div>
                    <Label>Tipo</Label>
                    <select className={selectClass} value={data.type} onChange={(e) => setData('type', e.target.value)}>
                        {Object.entries(docTypes).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                </div>
                <div>
                    <Label>Nombre *</Label>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                </div>
                <div>
                    <Label>Archivo (PDF/imagen)</Label>
                    <input type="file" className="w-full text-sm" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} />
                    {errors.file && <p className="mt-1 text-xs text-red-500">{errors.file}</p>}
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <div><Label>Emisión</Label><Input type="date" value={data.issued_at} onChange={(e) => setData('issued_at', e.target.value)} /></div>
                    <div><Label>Vence</Label><Input type="date" value={data.expires_at} onChange={(e) => setData('expires_at', e.target.value)} /></div>
                </div>
                <Button type="submit" disabled={processing} className="w-full"><Plus className="mr-2 h-4 w-4" /> Agregar</Button>
            </form>
        </div>
    );
}

function IncidentsTab({ employee }: { employee: any }) {
    const { data, setData, post, processing, errors, reset } = useForm<any>({
        type: 'warning', severity: 'low', title: '', description: '', date: '', action_taken: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/employees/${employee.id}/incidents`, { onSuccess: () => reset() });
    };

    const remove = (id: number) => {
        if (confirm('¿Eliminar registro?')) router.delete(`/admin/employees/${employee.id}/incidents/${id}`);
    };

    return (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div className="lg:col-span-2">
                <Card title="Relaciones laborales" icon={Award}>
                    {(employee.incidents ?? []).length === 0 ? <Empty text="Sin registros." /> : (
                        <SimpleTable head={['Fecha', 'Tipo', 'Gravedad', 'Título', '']}>
                            {employee.incidents.map((i: any) => (
                                <tr key={i.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-2">{date(i.date)}</td>
                                    <td className="px-4 py-2">{incidentTypes[i.type]}</td>
                                    <td className="px-4 py-2">{i.severity}</td>
                                    <td className="px-4 py-2 font-medium">{i.title}</td>
                                    <td className="px-4 py-2 text-right">
                                        <Button variant="ghost" size="sm" onClick={() => remove(i.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                                    </td>
                                </tr>
                            ))}
                        </SimpleTable>
                    )}
                </Card>
            </div>
            <form onSubmit={submit} className="rounded-lg border bg-white p-4 shadow-sm space-y-3">
                <h2 className="font-semibold text-gray-700">Nuevo registro</h2>
                <div>
                    <Label>Tipo</Label>
                    <select className={selectClass} value={data.type} onChange={(e) => setData('type', e.target.value)}>
                        {Object.entries(incidentTypes).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                </div>
                <div>
                    <Label>Gravedad</Label>
                    <select className={selectClass} value={data.severity} onChange={(e) => setData('severity', e.target.value)}>
                        <option value="low">Baja</option>
                        <option value="medium">Media</option>
                        <option value="high">Alta</option>
                    </select>
                </div>
                <div>
                    <Label>Título *</Label>
                    <Input value={data.title} onChange={(e) => setData('title', e.target.value)} />
                    {errors.title && <p className="mt-1 text-xs text-red-500">{errors.title}</p>}
                </div>
                <div>
                    <Label>Fecha *</Label>
                    <Input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                    {errors.date && <p className="mt-1 text-xs text-red-500">{errors.date}</p>}
                </div>
                <div>
                    <Label>Descripción</Label>
                    <textarea className={selectClass} rows={2} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                </div>
                <div>
                    <Label>Acción tomada</Label>
                    <Input value={data.action_taken} onChange={(e) => setData('action_taken', e.target.value)} />
                </div>
                <Button type="submit" disabled={processing} className="w-full"><Plus className="mr-2 h-4 w-4" /> Registrar</Button>
            </form>
        </div>
    );
}

function Card({ title, icon: Icon, children }: { title: string; icon: any; children: React.ReactNode }) {
    return (
        <div className="rounded-lg border bg-white shadow-sm">
            <div className="flex items-center gap-2 border-b px-4 py-3">
                <Icon className="h-4 w-4 text-gray-500" />
                <h2 className="font-semibold text-gray-700">{title}</h2>
            </div>
            {children}
        </div>
    );
}

function SimpleTable({ head, children }: { head: string[]; children: React.ReactNode }) {
    return (
        <table className="w-full text-sm">
            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>{head.map((h, i) => <th key={i} className="px-4 py-2">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y">{children}</tbody>
        </table>
    );
}

function Empty({ text }: { text: string }) {
    return <p className="px-4 py-8 text-center text-sm text-gray-400">{text}</p>;
}
