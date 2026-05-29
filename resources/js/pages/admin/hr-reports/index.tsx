import { FlashMessage } from '@/components/flash-message';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { AlertTriangle, CalendarX, TrendingUp, UserCheck, UserMinus, Users } from 'lucide-react';

const fmt = (v: any) => `Bs ${Number(v ?? 0).toFixed(2)}`;
const contractLabels: Record<string, string> = {
    indefinite: 'Indefinido', fixed_term: 'Plazo fijo', part_time: 'Medio tiempo', intern: 'Pasante', services: 'Servicios',
};

interface Props {
    year: number;
    summary: {
        active_employees: number; total_employees: number; hired_this_year: number; terminated_this_year: number;
        turnover_rate: number; avg_base_salary: number; on_leave: number; pending_leaves: number;
    };
    headcountByDepartment: { department: string; total: number; avg_salary: number }[];
    contractDistribution: { label: string; total: number }[];
    payrollEvolution: { label: string; total_gross: number; total_deductions: number; total_net: number; status: string }[];
    trainingSummary: { id: number; title: string; modality: string; status: string; hours: number; cost: number; participants: number; start_date: string | null }[];
    expiringDocuments: { id: number; employee: string; type: string; name: string; expires_at: string }[];
    endingContracts: { id: number; employee: string; position: string; termination_date: string }[];
}

export default function HrReportsIndex(props: Props) {
    const { year, summary } = props;

    const maxNet = Math.max(...props.payrollEvolution.map((p) => p.total_net), 1);

    const cards = [
        { label: 'Empleados activos', value: summary.active_employees, icon: Users, color: 'text-blue-600' },
        { label: 'Contrataciones (año)', value: summary.hired_this_year, icon: UserCheck, color: 'text-green-600' },
        { label: 'Bajas (año)', value: summary.terminated_this_year, icon: UserMinus, color: 'text-red-600' },
        { label: 'Rotación', value: `${summary.turnover_rate}%`, icon: TrendingUp, color: 'text-amber-600' },
        { label: 'Salario base prom.', value: fmt(summary.avg_base_salary), icon: Users, color: 'text-indigo-600' },
        { label: 'En licencia', value: summary.on_leave, icon: CalendarX, color: 'text-cyan-600' },
        { label: 'Ausencias pendientes', value: summary.pending_leaves, icon: AlertTriangle, color: 'text-orange-600' },
        { label: 'Total empleados', value: summary.total_employees, icon: Users, color: 'text-gray-600' },
    ];

    return (
        <AppLayout breadcrumbs={[{ title: 'Reportes RR.HH.', href: '/admin/hr-reports' }]}>
            <FlashMessage />
            <div className="p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Análisis de Recursos Humanos</h1>
                    <select className="rounded-md border px-3 py-2 text-sm" value={year}
                        onChange={(e) => router.get('/admin/hr-reports', { year: e.target.value }, { preserveState: true, replace: true })}>
                        {[year + 1, year, year - 1, year - 2].map((y) => <option key={y} value={y}>{y}</option>)}
                    </select>
                </div>

                {/* KPIs */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    {cards.map((c) => (
                        <div key={c.label} className="flex items-center gap-3 rounded-lg border bg-white p-4 shadow-sm">
                            <c.icon className={`h-7 w-7 ${c.color}`} />
                            <div>
                                <p className="text-xs uppercase text-gray-500">{c.label}</p>
                                <p className={`text-lg font-bold ${c.color}`}>{c.value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {/* Headcount por área */}
                    <div className="rounded-lg border bg-white shadow-sm">
                        <h2 className="border-b px-4 py-3 font-semibold text-gray-700">Plantilla por área</h2>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th className="px-4 py-2">Área</th><th className="px-4 py-2 text-center">Empleados</th><th className="px-4 py-2 text-right">Salario prom.</th></tr>
                            </thead>
                            <tbody className="divide-y">
                                {props.headcountByDepartment.map((d, i) => (
                                    <tr key={i}><td className="px-4 py-2">{d.department}</td><td className="px-4 py-2 text-center">{d.total}</td><td className="px-4 py-2 text-right text-gray-500">{fmt(d.avg_salary)}</td></tr>
                                ))}
                                {props.headcountByDepartment.length === 0 && <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">Sin datos.</td></tr>}
                            </tbody>
                        </table>
                    </div>

                    {/* Distribución por contrato */}
                    <div className="rounded-lg border bg-white shadow-sm">
                        <h2 className="border-b px-4 py-3 font-semibold text-gray-700">Tipos de contrato</h2>
                        <div className="space-y-3 p-4">
                            {props.contractDistribution.map((c) => {
                                const total = props.contractDistribution.reduce((a, b) => a + b.total, 0) || 1;
                                const pct = Math.round((c.total / total) * 100);
                                return (
                                    <div key={c.label}>
                                        <div className="mb-1 flex justify-between text-xs text-gray-600">
                                            <span>{contractLabels[c.label] ?? c.label}</span><span>{c.total} ({pct}%)</span>
                                        </div>
                                        <div className="h-2 rounded-full bg-gray-100"><div className="h-2 rounded-full bg-blue-500" style={{ width: `${pct}%` }} /></div>
                                    </div>
                                );
                            })}
                            {props.contractDistribution.length === 0 && <p className="text-center text-sm text-gray-400">Sin datos.</p>}
                        </div>
                    </div>
                </div>

                {/* Evolución de nómina */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <h2 className="border-b px-4 py-3 font-semibold text-gray-700">Costo de nómina {year}</h2>
                    {props.payrollEvolution.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-gray-400">Sin planillas registradas este año.</p>
                    ) : (
                        <div className="flex items-end gap-3 overflow-x-auto p-4" style={{ minHeight: 180 }}>
                            {props.payrollEvolution.map((p, i) => (
                                <div key={i} className="flex flex-1 flex-col items-center gap-1" style={{ minWidth: 60 }}>
                                    <span className="text-xs text-gray-500">{fmt(p.total_net)}</span>
                                    <div className="w-full rounded-t bg-green-500" style={{ height: `${(p.total_net / maxNet) * 120}px` }} />
                                    <span className="text-xs text-gray-400">{p.label.split(' ')[0].substring(0, 3)}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {/* Documentos por vencer */}
                    <div className="rounded-lg border bg-white shadow-sm">
                        <h2 className="border-b px-4 py-3 font-semibold text-gray-700">Documentos por vencer (60 días)</h2>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th className="px-4 py-2">Empleado</th><th className="px-4 py-2">Documento</th><th className="px-4 py-2 text-right">Vence</th></tr>
                            </thead>
                            <tbody className="divide-y">
                                {props.expiringDocuments.map((d) => (
                                    <tr key={d.id}><td className="px-4 py-2">{d.employee}</td><td className="px-4 py-2 text-gray-500">{d.name}</td><td className="px-4 py-2 text-right font-medium text-red-600">{d.expires_at}</td></tr>
                                ))}
                                {props.expiringDocuments.length === 0 && <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">Nada por vencer.</td></tr>}
                            </tbody>
                        </table>
                    </div>

                    {/* Contratos a finalizar */}
                    <div className="rounded-lg border bg-white shadow-sm">
                        <h2 className="border-b px-4 py-3 font-semibold text-gray-700">Contratos por finalizar (60 días)</h2>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th className="px-4 py-2">Empleado</th><th className="px-4 py-2">Cargo</th><th className="px-4 py-2 text-right">Finaliza</th></tr>
                            </thead>
                            <tbody className="divide-y">
                                {props.endingContracts.map((c) => (
                                    <tr key={c.id}><td className="px-4 py-2">{c.employee}</td><td className="px-4 py-2 text-gray-500">{c.position}</td><td className="px-4 py-2 text-right font-medium text-amber-600">{c.termination_date}</td></tr>
                                ))}
                                {props.endingContracts.length === 0 && <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">Sin contratos próximos a finalizar.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Capacitaciones del año */}
                <div className="rounded-lg border bg-white shadow-sm">
                    <h2 className="border-b px-4 py-3 font-semibold text-gray-700">Capacitaciones {year}</h2>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-2">Curso</th><th className="px-4 py-2">Estado</th>
                                <th className="px-4 py-2 text-center">Participantes</th><th className="px-4 py-2 text-right">Horas</th><th className="px-4 py-2 text-right">Costo</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {props.trainingSummary.map((t) => (
                                <tr key={t.id}>
                                    <td className="px-4 py-2">{t.title}</td><td className="px-4 py-2 text-gray-500">{t.status}</td>
                                    <td className="px-4 py-2 text-center">{t.participants}</td><td className="px-4 py-2 text-right">{t.hours.toFixed(1)}</td><td className="px-4 py-2 text-right text-gray-500">{fmt(t.cost)}</td>
                                </tr>
                            ))}
                            {props.trainingSummary.length === 0 && <tr><td colSpan={5} className="px-4 py-6 text-center text-gray-400">Sin capacitaciones este año.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
