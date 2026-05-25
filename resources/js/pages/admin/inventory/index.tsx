import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type PaginatedData } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';

interface Movement {
    id: number; type: string; quantity: string; stock_before: number; stock_after: number; reason: string | null; created_at: string;
    product: { name: string; sku: string | null };
    user: { name: string };
}
interface LowStockProduct { id: number; name: string; stock: number; min_stock: number; primary_image: { url: string } | null }

const typeColors: Record<string, string> = { in: 'bg-green-100 text-green-700', out: 'bg-red-100 text-red-700', adjustment: 'bg-blue-100 text-blue-700', return: 'bg-purple-100 text-purple-700' };
const typeLabels: Record<string, string> = { in: 'Entrada', out: 'Salida', adjustment: 'Ajuste', return: 'Devolución' };

export default function InventoryIndex({ movements, lowStock }: { movements: PaginatedData<Movement>; lowStock: LowStockProduct[] }) {
    const [adjustingProduct, setAdjustingProduct] = useState<LowStockProduct | null>(null);
    const { data, setData, post, processing, errors, reset } = useForm({ product_id: '', new_stock: '', reason: '' });

    const openAdjust = (p: LowStockProduct) => { setAdjustingProduct(p); setData({ product_id: p.id.toString(), new_stock: p.stock.toString(), reason: '' }); };
    const submitAdjust = (e: React.FormEvent) => { e.preventDefault(); post('/admin/inventory/adjust', { onSuccess: () => { setAdjustingProduct(null); reset(); } }); };

    return (
        <AppLayout breadcrumbs={[{ title: 'Inventario', href: '/admin/inventory' }]}>
            <FlashMessage />
            <div className="p-6 space-y-6">
                <h1 className="text-2xl font-bold">Inventario</h1>

                {lowStock.length > 0 && (
                    <div className="rounded-lg border border-amber-300 bg-amber-50 p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-amber-600" />
                            <h2 className="font-semibold text-amber-800">Productos con stock bajo ({lowStock.length})</h2>
                        </div>
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                            {lowStock.map((p) => (
                                <div key={p.id} className="rounded-lg border bg-white p-3 shadow-sm">
                                    <div className="flex items-center gap-2 mb-2">
                                        {p.primary_image && <img src={p.primary_image.url} className="h-8 w-8 rounded object-cover" />}
                                        <p className="text-sm font-medium">{p.name}</p>
                                    </div>
                                    <p className="text-xs text-red-600">Stock: {p.stock} / Mín: {p.min_stock}</p>
                                    <Button size="sm" variant="outline" className="mt-2 w-full text-xs" onClick={() => openAdjust(p)}>Ajustar</Button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="rounded-lg border bg-white shadow-sm">
                    <div className="border-b px-4 py-3 font-semibold text-gray-700">Movimientos de inventario</div>
                    <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3">Tipo</th>
                                <th className="px-4 py-3 text-right">Cantidad</th>
                                <th className="px-4 py-3 text-right">Stock antes</th>
                                <th className="px-4 py-3 text-right">Stock después</th>
                                <th className="px-4 py-3">Razón</th>
                                <th className="px-4 py-3">Usuario</th>
                                <th className="px-4 py-3">Fecha</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {movements.data.map((m) => (
                                <tr key={m.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <p className="font-medium">{m.product.name}</p>
                                        {m.product.sku && <p className="font-mono text-xs text-gray-400">{m.product.sku}</p>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${typeColors[m.type]}`}>{typeLabels[m.type]}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium">{m.quantity}</td>
                                    <td className="px-4 py-3 text-right text-gray-500">{m.stock_before}</td>
                                    <td className="px-4 py-3 text-right font-medium">{m.stock_after}</td>
                                    <td className="px-4 py-3 text-gray-500">{m.reason ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-500">{m.user.name}</td>
                                    <td className="px-4 py-3 text-gray-400 text-xs">{new Date(m.created_at).toLocaleString('es-MX')}</td>
                                </tr>
                            ))}
                            {movements.data.length === 0 && <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Sin movimientos registrados.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>

            {adjustingProduct && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                        <h2 className="mb-1 text-lg font-bold">Ajustar inventario</h2>
                        <p className="mb-4 text-sm text-gray-500">{adjustingProduct.name} — Stock actual: {adjustingProduct.stock}</p>
                        <form onSubmit={submitAdjust} className="space-y-4">
                            <div>
                                <Label>Nuevo stock *</Label>
                                <Input type="number" min="0" value={data.new_stock} onChange={(e) => setData('new_stock', e.target.value)} />
                                {errors.new_stock && <p className="mt-1 text-xs text-red-500">{errors.new_stock}</p>}
                            </div>
                            <div>
                                <Label>Razón del ajuste *</Label>
                                <Input value={data.reason} onChange={(e) => setData('reason', e.target.value)} placeholder="Ej: Conteo físico, merma, etc." />
                                {errors.reason && <p className="mt-1 text-xs text-red-500">{errors.reason}</p>}
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>Guardar ajuste</Button>
                                <Button type="button" variant="outline" onClick={() => setAdjustingProduct(null)}>Cancelar</Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
