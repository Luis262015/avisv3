import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';
import { TrainingFormFields } from './training-form-fields';

interface Employee { id: number; first_name: string; last_name: string }

export default function TrainingCreate({ employees }: { employees: Employee[] }) {
    const { data, setData, post, processing, errors } = useForm<any>({
        title: '', description: '', provider: '', modality: 'internal', start_date: '', end_date: '',
        hours: '0', cost: '0', status: 'planned', notes: '', employee_ids: [],
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Capacitación', href: '/admin/trainings' }, { title: 'Nueva', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-3xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nueva capacitación</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/trainings'); }}>
                    <TrainingFormFields data={data} setData={setData} errors={errors} employees={employees} />
                    <div className="flex gap-2 pt-5">
                        <Button type="submit" disabled={processing}>Guardar</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
