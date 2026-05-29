import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';

interface Product { id: number; name: string; sku: string | null }
interface Customer { id: number; name: string }
interface SaleRow { id: number; folio: string }

export default function WarrantyCreate({ products, customers, sales }: { products: Product[]; customers: Customer[]; sales: SaleRow[] }) {
    const { data, setData, post, processing, errors } = useForm<any>({
        product_id: '', customer_id: '', sale_id: '', serial_number: '',
        start_date: new Date().toISOString().split('T')[0], end_date: '', terms: '',
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Garantías', href: '/admin/warranties' }, { title: 'Nueva', href: '' }]}>
            <FlashMessage />
            <div className="mx-auto max-w-2xl p-6">
                <h1 className="mb-6 text-2xl font-bold">Nueva Garantía</h1>
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/warranties'); }} className="space-y-5">
                    <div className="rounded-lg border bg-white p-4 shadow-sm space-y-4">
                        <div>
                            <Label>Producto *</Label>
                            <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.product_id} onChange={(e) => setData('product_id', e.target.value)}>
                                <option value="">— Seleccionar —</option>
                                {products.map((p) => <option key={p.id} value={p.id}>{p.name}{p.sku ? ` (${p.sku})` : ''}</option>)}
                            </select>
                            {errors.product_id && <p className="mt-1 text-xs text-red-500">{errors.product_id}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Cliente</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.customer_id} onChange={(e) => setData('customer_id', e.target.value)}>
                                    <option value="">— Sin cliente —</option>
                                    {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <Label>Venta asociada</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.sale_id} onChange={(e) => setData('sale_id', e.target.value)}>
                                    <option value="">— Sin venta —</option>
                                    {sales.map((s) => <option key={s.id} value={s.id}>{s.folio}</option>)}
                                </select>
                            </div>
                        </div>
                        <div>
                            <Label>Número de serie</Label>
                            <Input value={data.serial_number} onChange={(e) => setData('serial_number', e.target.value)} />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Inicio *</Label>
                                <Input type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} />
                                {errors.start_date && <p className="mt-1 text-xs text-red-500">{errors.start_date}</p>}
                            </div>
                            <div>
                                <Label>Fin *</Label>
                                <Input type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} />
                                {errors.end_date && <p className="mt-1 text-xs text-red-500">{errors.end_date}</p>}
                            </div>
                        </div>
                        <div>
                            <Label>Términos / cobertura</Label>
                            <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={3} value={data.terms} onChange={(e) => setData('terms', e.target.value)} />
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>Guardar</Button>
                        <Button variant="outline" type="button" onClick={() => history.back()}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
