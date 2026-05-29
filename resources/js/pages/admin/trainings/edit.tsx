import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';
import { TrainingFormFields } from './training-form-fields';

interface Employee { id: number; first_name: string; last_name: string }

export default function TrainingEdit({ training, employees }: { training: any; employees: Employee[] }) {
    const toDate = (v: string | null) => (v ? v.substring(0, 10) : '');

    const { data, setData, put, processing, errors } = useForm<any>({
        title: training.title ?? '', description: training.description ?? '', provider: training.provider ?? '',
        modality: training.modality ?? 'internal', start_date: toDate(training.start_date), end_date: toDate(training.end_date),
        hours: training.hours ?? '0', cost: training.cost ?? '0', status: training.status ?? 'planned',
        notes: training.notes ?? '', employee_ids: (training.employees ?? []).map((e: any) => e.id),
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Capacitación', href: '/admin/trainings' }, { title: 'Editar', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-3xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Editar capacitación</h1>
                <form onSubmit={(e) => { e.preventDefault(); put(`/admin/trainings/${training.id}`); }}>
                    <TrainingFormFields data={data} setData={setData} errors={errors} employees={employees} />
                    <div className="flex gap-2 pt-5">
                        <Button type="submit" disabled={processing}>Actualizar</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
