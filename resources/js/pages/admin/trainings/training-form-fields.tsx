import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Employee { id: number; first_name: string; last_name: string }

interface Props {
    data: Record<string, any>;
    setData: (key: string, value: any) => void;
    errors: Partial<Record<string, string>>;
    employees: Employee[];
}

const selectClass = 'w-full rounded-md border px-3 py-2 text-sm';

export function TrainingFormFields({ data, setData, errors, employees }: Props) {
    const toggle = (id: number) => {
        const ids: number[] = data.employee_ids ?? [];
        setData('employee_ids', ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]);
    };

    return (
        <div className="space-y-5">
            <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                <div>
                    <Label>Título *</Label>
                    <Input value={data.title} onChange={(e) => setData('title', e.target.value)} />
                    {errors.title && <p className="mt-1 text-xs text-red-500">{errors.title}</p>}
                </div>
                <div>
                    <Label>Descripción</Label>
                    <textarea className={selectClass} rows={2} value={data.description ?? ''} onChange={(e) => setData('description', e.target.value)} />
                </div>
                <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
                    <div>
                        <Label>Proveedor</Label>
                        <Input value={data.provider ?? ''} onChange={(e) => setData('provider', e.target.value)} />
                    </div>
                    <div>
                        <Label>Modalidad</Label>
                        <select className={selectClass} value={data.modality} onChange={(e) => setData('modality', e.target.value)}>
                            <option value="internal">Interna</option>
                            <option value="external">Externa</option>
                            <option value="online">En línea</option>
                        </select>
                    </div>
                    <div>
                        <Label>Estado</Label>
                        <select className={selectClass} value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            <option value="planned">Planificada</option>
                            <option value="in_progress">En curso</option>
                            <option value="completed">Completada</option>
                            <option value="cancelled">Cancelada</option>
                        </select>
                    </div>
                    <div><Label>Inicio</Label><Input type="date" value={data.start_date ?? ''} onChange={(e) => setData('start_date', e.target.value)} /></div>
                    <div><Label>Fin</Label><Input type="date" value={data.end_date ?? ''} onChange={(e) => setData('end_date', e.target.value)} /></div>
                    <div><Label>Horas</Label><Input type="number" step="0.5" value={data.hours} onChange={(e) => setData('hours', e.target.value)} /></div>
                    <div><Label>Costo (Bs)</Label><Input type="number" step="0.01" value={data.cost} onChange={(e) => setData('cost', e.target.value)} /></div>
                </div>
            </div>

            <div className="rounded-lg border bg-white p-4 shadow-sm">
                <Label>Participantes</Label>
                <div className="mt-2 grid max-h-64 grid-cols-2 gap-2 overflow-y-auto md:grid-cols-3">
                    {employees.map((e) => {
                        const checked = (data.employee_ids ?? []).includes(e.id);
                        return (
                            <label key={e.id} className={`flex items-center gap-2 rounded border px-3 py-2 text-sm ${checked ? 'border-blue-300 bg-blue-50' : ''}`}>
                                <input type="checkbox" checked={checked} onChange={() => toggle(e.id)} />
                                {e.first_name} {e.last_name}
                            </label>
                        );
                    })}
                    {employees.length === 0 && <p className="text-sm text-gray-400">Sin empleados activos.</p>}
                </div>
            </div>
        </div>
    );
}
