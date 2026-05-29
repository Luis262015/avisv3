import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

interface Supplier { id: number; name: string }
interface Purchase { id: number; folio: string; date: string; total: string }

function ScoreInput({ label, name, value, onChange }: { label: string; name: string; value: string; onChange: (v: string) => void }) {
    return (
        <div>
            <Label>{label} (1–5)</Label>
            <div className="flex gap-2 mt-1">
                {[1, 2, 3, 4, 5].map((n) => (
                    <button
                        key={n}
                        type="button"
                        onClick={() => onChange(n.toString())}
                        className={`h-9 w-9 rounded-full text-sm font-semibold transition-colors ${
                            value === n.toString()
                                ? 'bg-blue-600 text-white'
                                : 'border border-gray-300 text-gray-600 hover:bg-gray-100'
                        }`}
                    >
                        {n}
                    </button>
                ))}
            </div>
        </div>
    );
}

export default function EvaluationCreate({ supplier, purchases }: { supplier: Supplier; purchases: Purchase[] }) {
    const { data, setData, post, processing, errors } = useForm({
        purchase_id: '',
        overall_score: '3',
        delivery_score: '3',
        quality_score: '3',
        price_score: '3',
        comments: '',
        evaluated_at: new Date().toISOString().split('T')[0],
    });

    return (
        <AppLayout breadcrumbs={[
            { title: 'Proveedores', href: '/admin/suppliers' },
            { title: supplier.name, href: `/admin/suppliers/${supplier.id}` },
            { title: 'Nueva Evaluación', href: '' },
        ]}>
            <FlashMessage />
            <div className="mx-auto max-w-xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Evaluar — {supplier.name}</h1>

                <form onSubmit={(e) => { e.preventDefault(); post(`/admin/suppliers/${supplier.id}/evaluations`); }} className="space-y-5">

                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                        <div>
                            <Label>Fecha de evaluación *</Label>
                            <Input type="date" value={data.evaluated_at} onChange={(e) => setData('evaluated_at', e.target.value)} />
                            {errors.evaluated_at && <p className="mt-1 text-xs text-red-500">{errors.evaluated_at}</p>}
                        </div>
                        <div>
                            <Label>Compra de referencia (opcional)</Label>
                            <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.purchase_id} onChange={(e) => setData('purchase_id', e.target.value)}>
                                <option value="">— Sin compra asociada —</option>
                                {purchases.map((p) => (
                                    <option key={p.id} value={p.id}>{p.folio} — {p.date} — ${parseFloat(p.total).toFixed(2)}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-5">
                        <h2 className="font-semibold text-gray-700">Calificaciones</h2>
                        <ScoreInput label="Calificación general *" name="overall_score" value={data.overall_score} onChange={(v) => setData('overall_score', v)} />
                        {errors.overall_score && <p className="text-xs text-red-500">{errors.overall_score}</p>}
                        <ScoreInput label="Puntualidad de entrega *" name="delivery_score" value={data.delivery_score} onChange={(v) => setData('delivery_score', v)} />
                        <ScoreInput label="Calidad del producto *" name="quality_score" value={data.quality_score} onChange={(v) => setData('quality_score', v)} />
                        <ScoreInput label="Relación precio-valor *" name="price_score" value={data.price_score} onChange={(v) => setData('price_score', v)} />

                        <div>
                            <Label>Comentarios</Label>
                            <textarea
                                className="w-full rounded-md border px-3 py-2 text-sm"
                                rows={4}
                                placeholder="Descripción de la experiencia con el proveedor..."
                                value={data.comments}
                                onChange={(e) => setData('comments', e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>Guardar Evaluación</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
