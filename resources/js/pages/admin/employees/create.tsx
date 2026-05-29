import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';
import { EmployeeFormFields } from './employee-form-fields';

interface Option { id: number; name: string; email?: string }

export default function EmployeeCreate({ departments, users }: { departments: Option[]; users: Option[] }) {
    const { data, setData, post, processing, errors } = useForm<any>({
        employee_code: '', first_name: '', last_name: '', document_type: 'ci', document_number: '',
        birth_date: '', gender: '', marital_status: '', nationality: 'Boliviana',
        phone: '', email: '', address: '', emergency_contact_name: '', emergency_contact_phone: '',
        position: '', department_id: null, user_id: null, hire_date: '', termination_date: '',
        contract_type: 'indefinite', status: 'active', base_salary: '',
        bank_name: '', bank_account: '', afp_name: '', afp_number: '', cuns: '', notes: '',
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Empleados', href: '/admin/employees' }, { title: 'Nuevo', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-4xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nuevo Empleado</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/employees'); }}>
                    <EmployeeFormFields data={data} setData={setData} errors={errors} departments={departments} users={users} />
                    <div className="flex gap-2 pt-5">
                        <Button type="submit" disabled={processing}>Guardar</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
