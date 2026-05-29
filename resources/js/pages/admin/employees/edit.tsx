import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';
import { EmployeeFormFields } from './employee-form-fields';

interface Option { id: number; name: string; email?: string }

export default function EmployeeEdit({ employee, departments, users }: { employee: any; departments: Option[]; users: Option[] }) {
    const toDate = (v: string | null) => (v ? v.substring(0, 10) : '');

    const { data, setData, put, processing, errors } = useForm<any>({
        employee_code: employee.employee_code ?? '', first_name: employee.first_name ?? '', last_name: employee.last_name ?? '',
        document_type: employee.document_type ?? 'ci', document_number: employee.document_number ?? '',
        birth_date: toDate(employee.birth_date), gender: employee.gender ?? '', marital_status: employee.marital_status ?? '',
        nationality: employee.nationality ?? '', phone: employee.phone ?? '', email: employee.email ?? '',
        address: employee.address ?? '', emergency_contact_name: employee.emergency_contact_name ?? '',
        emergency_contact_phone: employee.emergency_contact_phone ?? '', position: employee.position ?? '',
        department_id: employee.department_id, user_id: employee.user_id, hire_date: toDate(employee.hire_date),
        termination_date: toDate(employee.termination_date), contract_type: employee.contract_type ?? 'indefinite',
        status: employee.status ?? 'active', base_salary: employee.base_salary ?? '',
        bank_name: employee.bank_name ?? '', bank_account: employee.bank_account ?? '', afp_name: employee.afp_name ?? '',
        afp_number: employee.afp_number ?? '', cuns: employee.cuns ?? '', notes: employee.notes ?? '',
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Empleados', href: '/admin/employees' }, { title: 'Editar', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-4xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Editar Empleado</h1>
                <form onSubmit={(e) => { e.preventDefault(); put(`/admin/employees/${employee.id}`); }}>
                    <EmployeeFormFields data={data} setData={setData} errors={errors} departments={departments} users={users} />
                    <div className="flex gap-2 pt-5">
                        <Button type="submit" disabled={processing}>Actualizar</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
