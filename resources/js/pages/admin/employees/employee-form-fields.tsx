import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Option {
    id: number;
    name: string;
    email?: string;
}

interface Props {
    data: Record<string, any>;
    setData: (key: string, value: any) => void;
    errors: Partial<Record<string, string>>;
    departments: Option[];
    users: Option[];
}

const selectClass = 'w-full rounded-md border px-3 py-2 text-sm';

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <Label>{label}</Label>
            {children}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

export function EmployeeFormFields({ data, setData, errors, departments, users }: Props) {
    return (
        <div className="space-y-5">
            {/* Identificación */}
            <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                <h2 className="font-semibold text-gray-700">Identificación</h2>
                <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
                    <Field label="Código de empleado *" error={errors.employee_code}>
                        <Input value={data.employee_code} onChange={(e) => setData('employee_code', e.target.value)} />
                    </Field>
                    <Field label="Nombres *" error={errors.first_name}>
                        <Input value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} />
                    </Field>
                    <Field label="Apellidos *" error={errors.last_name}>
                        <Input value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                    </Field>
                    <Field label="Tipo de documento" error={errors.document_type}>
                        <select className={selectClass} value={data.document_type} onChange={(e) => setData('document_type', e.target.value)}>
                            <option value="ci">CI</option>
                            <option value="passport">Pasaporte</option>
                            <option value="other">Otro</option>
                        </select>
                    </Field>
                    <Field label="N° de documento" error={errors.document_number}>
                        <Input value={data.document_number} onChange={(e) => setData('document_number', e.target.value)} />
                    </Field>
                    <Field label="Fecha de nacimiento" error={errors.birth_date}>
                        <Input type="date" value={data.birth_date ?? ''} onChange={(e) => setData('birth_date', e.target.value)} />
                    </Field>
                    <Field label="Género" error={errors.gender}>
                        <select className={selectClass} value={data.gender ?? ''} onChange={(e) => setData('gender', e.target.value)}>
                            <option value="">—</option>
                            <option value="male">Masculino</option>
                            <option value="female">Femenino</option>
                            <option value="other">Otro</option>
                        </select>
                    </Field>
                    <Field label="Estado civil" error={errors.marital_status}>
                        <select className={selectClass} value={data.marital_status ?? ''} onChange={(e) => setData('marital_status', e.target.value)}>
                            <option value="">—</option>
                            <option value="single">Soltero(a)</option>
                            <option value="married">Casado(a)</option>
                            <option value="divorced">Divorciado(a)</option>
                            <option value="widowed">Viudo(a)</option>
                            <option value="free_union">Unión libre</option>
                        </select>
                    </Field>
                    <Field label="Nacionalidad" error={errors.nationality}>
                        <Input value={data.nationality ?? ''} onChange={(e) => setData('nationality', e.target.value)} />
                    </Field>
                </div>
            </div>

            {/* Contacto */}
            <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                <h2 className="font-semibold text-gray-700">Contacto</h2>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Teléfono" error={errors.phone}>
                        <Input value={data.phone ?? ''} onChange={(e) => setData('phone', e.target.value)} />
                    </Field>
                    <Field label="Email" error={errors.email}>
                        <Input type="email" value={data.email ?? ''} onChange={(e) => setData('email', e.target.value)} />
                    </Field>
                </div>
                <Field label="Dirección" error={errors.address}>
                    <Input value={data.address ?? ''} onChange={(e) => setData('address', e.target.value)} />
                </Field>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Contacto de emergencia" error={errors.emergency_contact_name}>
                        <Input value={data.emergency_contact_name ?? ''} onChange={(e) => setData('emergency_contact_name', e.target.value)} />
                    </Field>
                    <Field label="Teléfono de emergencia" error={errors.emergency_contact_phone}>
                        <Input value={data.emergency_contact_phone ?? ''} onChange={(e) => setData('emergency_contact_phone', e.target.value)} />
                    </Field>
                </div>
            </div>

            {/* Datos laborales */}
            <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                <h2 className="font-semibold text-gray-700">Datos laborales</h2>
                <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
                    <Field label="Cargo *" error={errors.position}>
                        <Input value={data.position} onChange={(e) => setData('position', e.target.value)} />
                    </Field>
                    <Field label="Área / Departamento" error={errors.department_id}>
                        <select className={selectClass} value={data.department_id ?? ''} onChange={(e) => setData('department_id', e.target.value || null)}>
                            <option value="">— Sin asignar —</option>
                            {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Cuenta de acceso" error={errors.user_id}>
                        <select className={selectClass} value={data.user_id ?? ''} onChange={(e) => setData('user_id', e.target.value || null)}>
                            <option value="">— Ninguna —</option>
                            {users.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}
                        </select>
                    </Field>
                    <Field label="Fecha de ingreso *" error={errors.hire_date}>
                        <Input type="date" value={data.hire_date} onChange={(e) => setData('hire_date', e.target.value)} />
                    </Field>
                    <Field label="Fecha de baja" error={errors.termination_date}>
                        <Input type="date" value={data.termination_date ?? ''} onChange={(e) => setData('termination_date', e.target.value)} />
                    </Field>
                    <Field label="Tipo de contrato" error={errors.contract_type}>
                        <select className={selectClass} value={data.contract_type} onChange={(e) => setData('contract_type', e.target.value)}>
                            <option value="indefinite">Indefinido</option>
                            <option value="fixed_term">Plazo fijo</option>
                            <option value="part_time">Medio tiempo</option>
                            <option value="intern">Pasante</option>
                            <option value="services">Servicios</option>
                        </select>
                    </Field>
                    <Field label="Estado" error={errors.status}>
                        <select className={selectClass} value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            <option value="active">Activo</option>
                            <option value="on_leave">En licencia</option>
                            <option value="suspended">Suspendido</option>
                            <option value="terminated">Dado de baja</option>
                        </select>
                    </Field>
                    <Field label="Salario base (Bs) *" error={errors.base_salary}>
                        <Input type="number" step="0.01" min="0" value={data.base_salary} onChange={(e) => setData('base_salary', e.target.value)} />
                    </Field>
                </div>
            </div>

            {/* Nómina */}
            <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                <h2 className="font-semibold text-gray-700">Datos para nómina</h2>
                <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
                    <Field label="Banco" error={errors.bank_name}>
                        <Input value={data.bank_name ?? ''} onChange={(e) => setData('bank_name', e.target.value)} />
                    </Field>
                    <Field label="N° de cuenta" error={errors.bank_account}>
                        <Input value={data.bank_account ?? ''} onChange={(e) => setData('bank_account', e.target.value)} />
                    </Field>
                    <Field label="AFP" error={errors.afp_name}>
                        <Input value={data.afp_name ?? ''} onChange={(e) => setData('afp_name', e.target.value)} />
                    </Field>
                    <Field label="N° AFP (NUA)" error={errors.afp_number}>
                        <Input value={data.afp_number ?? ''} onChange={(e) => setData('afp_number', e.target.value)} />
                    </Field>
                    <Field label="Matrícula CNS (CUNS)" error={errors.cuns}>
                        <Input value={data.cuns ?? ''} onChange={(e) => setData('cuns', e.target.value)} />
                    </Field>
                </div>
            </div>

            {/* Notas */}
            <div className="rounded-lg border bg-white p-4 shadow-sm">
                <Field label="Notas" error={errors.notes}>
                    <textarea className={selectClass} rows={3} value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} />
                </Field>
            </div>
        </div>
    );
}
