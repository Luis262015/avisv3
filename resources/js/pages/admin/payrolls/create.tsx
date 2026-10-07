import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

export default function PayrollCreate() {
    const now = new Date();
    const { data, setData, post, processing, errors } = useForm<any>({
        period_year: now.getFullYear(),
        period_month: now.getMonth() + 1,
        pay_date: '',
        notes: '',
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Nómina', href: '/admin/payrolls' }, { title: 'Generar', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-xl p-6">
                <h1 className="mb-2 text-2xl font-bold">Generar planilla</h1>
                <p className="mb-6 text-sm text-gray-500">
                    Se creará una boleta por cada empleado activo con el cálculo sugerido (haber, bono de antigüedad,
                    aporte AFP 12.71% y RC-IVA). Podrás ajustar cada boleta antes de aprobar.
                </p>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/payrolls'); }} className="rounded-lg border bg-card p-4 shadow-sm space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="campo-mes">Mes *</Label>
                            <select id="campo-mes" className="w-full rounded-md border px-3 py-2 text-sm" value={data.period_month} onChange={(e) => setData('period_month', Number(e.target.value))}>
                                {months.map((m, i) => <option key={i} value={i + 1}>{m}</option>)}
                            </select>
                            {errors.period_month && <p className="mt-1 text-xs text-red-500">{errors.period_month}</p>}
                        </div>
                        <div>
                            <Label htmlFor="campo-ano">Año *</Label>
                            <Input id="campo-ano" type="number" value={data.period_year} onChange={(e) => setData('period_year', Number(e.target.value))} />
                            {errors.period_year && <p className="mt-1 text-xs text-red-500">{errors.period_year}</p>}
                        </div>
                    </div>
                    <div>
                        <Label htmlFor="campo-fecha-de-pago">Fecha de pago</Label>
                        <Input id="campo-fecha-de-pago" type="date" value={data.pay_date} onChange={(e) => setData('pay_date', e.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="campo-notas">Notas</Label>
                        <textarea id="campo-notas" className="w-full rounded-md border px-3 py-2 text-sm" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>
                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>Generar</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
