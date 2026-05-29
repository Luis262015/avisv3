import BarcodeScanner from '@/components/barcode-scanner';
import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Camera, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Shift { id: number; cash_register: { name: string; store: { name: string } } }
interface Product { id: number; name: string; sku: string | null; barcode: string | null; price: string; stock: number; track_inventory: boolean; category_id: number | null; primary_image: { url: string } | null }
interface Item { product_id: number; product_name: string; quantity: string; price: string; discount: string; combo_name?: string }
interface Customer { id: number; name: string }
interface Promotion {
    id: number; name: string; code: string | null; type: 'percentage' | 'fixed' | 'buy_x_get_y';
    value: string; scope: 'all' | 'product' | 'category'; min_purchase: string;
    buy_qty: number | null; get_qty: number | null; product_ids: number[]; category_ids: number[];
}
interface ComboLine { product_id: number; name: string; sku: string | null; price: string; quantity: string }
interface Combo { id: number; name: string; combo_price: string; items: ComboLine[] }

const round2 = (n: number) => Math.round(n * 100) / 100;

interface CartLine { product_id: number; category_id: number | null; quantity: number; price: number; subtotal: number }

function applicableLines(promo: Promotion, cart: CartLine[]): CartLine[] {
    if (promo.scope === 'all') return cart;
    if (promo.scope === 'product') return cart.filter((l) => promo.product_ids.includes(l.product_id));
    return cart.filter((l) => l.category_id !== null && promo.category_ids.includes(l.category_id));
}

function calcPromoDiscount(promo: Promotion, cart: CartLine[]): number {
    const lines = applicableLines(promo, cart);
    const base = lines.reduce((s, l) => s + l.subtotal, 0);
    if (base <= 0) return 0;
    const value = parseFloat(promo.value) || 0;
    if (promo.type === 'percentage') return base * (value / 100);
    if (promo.type === 'fixed') return Math.min(value, base);
    // buy_x_get_y
    const buy = promo.buy_qty ?? 0;
    const get = promo.get_qty ?? 0;
    if (buy <= 0 || get <= 0) return 0;
    const totalQty = lines.reduce((s, l) => s + l.quantity, 0);
    if (totalQty <= 0) return 0;
    const freeUnits = Math.floor(totalQty / (buy + get)) * get;
    return freeUnits * (base / totalQty);
}

export default function SaleCreate({ activeShift, openShifts, products, customers, promotions, combos }: {
    activeShift: Shift | null; openShifts: Shift[]; products: Product[]; customers: Customer[]; promotions: Promotion[]; combos: Combo[];
}) {
    const [search, setSearch] = useState('');
    const [scannerOpen, setScannerOpen] = useState(false);
    const [lastAdded, setLastAdded] = useState<string | null>(null);
    const lastAddedTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const searchRef = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, errors } = useForm<any>({
        cash_shift_id: activeShift?.id.toString() ?? (openShifts[0]?.id.toString() ?? ''),
        customer_id: '', promotion_id: '',
        discount: '0', tax: '0', amount_paid: '', payment_method: 'cash', notes: '',
        items: [] as Item[],
    });

    const filtered = products.filter((p) =>
        p.name.toLowerCase().includes(search.toLowerCase()) ||
        (p.sku && p.sku.toLowerCase().includes(search.toLowerCase())) ||
        (p.barcode && p.barcode.toLowerCase().includes(search.toLowerCase()))
    ).slice(0, 10);

    const addProduct = (p: Product) => {
        const exists = data.items.find((i: Item) => i.product_id === p.id);
        if (exists) {
            setData('items', data.items.map((i: Item) => i.product_id === p.id ? { ...i, quantity: String(parseFloat(i.quantity) + 1) } : i));
        } else {
            setData('items', [...data.items, { product_id: p.id, product_name: p.name, quantity: '1', price: p.price, discount: '0' }]);
        }
        setSearch('');
    };

    // Agrega un combo expandiéndolo en sus productos como líneas reales del carrito.
    // El ahorro (precio regular − precio del combo) se reparte como descuento por línea,
    // de modo que la suma de las líneas equivale al precio del combo.
    const addCombo = (combo: Combo) => {
        if (combo.items.length === 0) return;
        const regularTotal = combo.items.reduce((s, it) => s + (parseFloat(it.price) || 0) * (parseFloat(it.quantity) || 0), 0);
        const comboPrice = parseFloat(combo.combo_price) || 0;
        const totalDiscount = Math.max(0, Math.min(regularTotal, regularTotal - comboPrice));

        let allocated = 0;
        const newLines: Item[] = combo.items.map((it, idx) => {
            const lineRegular = (parseFloat(it.price) || 0) * (parseFloat(it.quantity) || 0);
            let lineDiscount: number;
            if (idx === combo.items.length - 1) {
                lineDiscount = round2(totalDiscount - allocated);
            } else {
                lineDiscount = regularTotal > 0 ? round2(totalDiscount * (lineRegular / regularTotal)) : 0;
                allocated += lineDiscount;
            }
            return {
                product_id: it.product_id,
                product_name: it.name,
                quantity: String(parseFloat(it.quantity) || 0),
                price: it.price,
                discount: String(Math.max(0, lineDiscount)),
                combo_name: combo.name,
            };
        });
        setData('items', [...data.items, ...newLines]);
    };

    const handleBarcodeScan = (barcode: string): boolean => {
        const product = products.find(
            (p) => p.barcode === barcode || p.sku === barcode
        );
        if (product) {
            addProduct(product);
            // Show brief "added" badge
            if (lastAddedTimer.current) clearTimeout(lastAddedTimer.current);
            setLastAdded(product.name);
            lastAddedTimer.current = setTimeout(() => setLastAdded(null), 3000);
            return true;
        }
        return false;
    };

    // Keyboard shortcut F8 to toggle scanner
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'F8') {
                e.preventDefault();
                setScannerOpen((v) => !v);
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

    // Auto-focus search when scanner closes
    useEffect(() => {
        if (!scannerOpen) searchRef.current?.focus();
    }, [scannerOpen]);

    const removeItem = (i: number) => setData('items', data.items.filter((_: Item, j: number) => j !== i));
    const setItem = (i: number, field: keyof Item, value: string) => {
        const items = [...data.items];
        items[i] = { ...items[i], [field]: value };
        setData('items', items);
    };

    const subtotal = data.items.reduce((s: number, i: Item) => s + (parseFloat(i.quantity) || 0) * (parseFloat(i.price) || 0) - (parseFloat(i.discount) || 0), 0);
    const tax = parseFloat(data.tax) || 0;

    const selectedPromo = promotions.find((p) => p.id.toString() === data.promotion_id);
    const cart: CartLine[] = data.items.map((i: Item) => {
        const prod = products.find((p) => p.id === i.product_id);
        const qty = parseFloat(i.quantity) || 0;
        const price = parseFloat(i.price) || 0;
        return { product_id: i.product_id, category_id: prod?.category_id ?? null, quantity: qty, price, subtotal: qty * price - (parseFloat(i.discount) || 0) };
    });
    const promoDiscount = selectedPromo ? Math.min(calcPromoDiscount(selectedPromo, cart), subtotal) : 0;
    const promoBelowMin = selectedPromo ? subtotal < (parseFloat(selectedPromo.min_purchase) || 0) : false;
    const discount = selectedPromo ? promoDiscount : (parseFloat(data.discount) || 0);
    const total = subtotal - discount + tax;
    const change = (parseFloat(data.amount_paid) || 0) - total;

    return (
        <AppLayout breadcrumbs={[{ title: 'Ventas', href: '/admin/sales' }, { title: 'Nueva Venta', href: '' }]}>
            <FlashMessage />

            {scannerOpen && (
                <BarcodeScanner
                    onScan={handleBarcodeScan}
                    onClose={() => setScannerOpen(false)}
                />
            )}

            {!activeShift && openShifts.length === 0 && (
                <div className="mx-4 mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <div className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 text-amber-600" />
                        <p className="font-medium text-amber-800">No hay turnos de caja abiertos.</p>
                    </div>
                    <p className="mt-1 text-sm text-amber-700">
                        Debes <Link href="/admin/cash-shifts/create" className="underline">iniciar un turno</Link> antes de registrar ventas.
                    </p>
                </div>
            )}

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Nueva Venta</h1>
                    {lastAdded && (
                        <div className="animate-pulse rounded-full bg-green-100 px-4 py-1.5 text-sm font-medium text-green-700">
                            ✓ {lastAdded} agregado
                        </div>
                    )}
                </div>

                <form onSubmit={(e) => { e.preventDefault(); post('/admin/sales'); }} className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Columna izquierda: buscador + carrito */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <Label>Buscar producto</Label>
                            <div className="mt-1 flex gap-2">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                    <Input
                                        ref={searchRef}
                                        className="pl-9"
                                        placeholder="Nombre, SKU o código de barras…"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    title="Escanear con cámara (F8)"
                                    onClick={() => setScannerOpen(true)}
                                    className="shrink-0 border-blue-200 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                >
                                    <Camera className="h-4 w-4" />
                                </Button>
                            </div>

                            {/* Hint */}
                            <p className="mt-1.5 text-xs text-gray-400">
                                Presiona <kbd className="rounded border bg-gray-100 px-1 py-0.5 font-mono text-xs">F8</kbd> para abrir el escáner
                            </p>

                            {search && (
                                <div className="mt-2 rounded-md border divide-y bg-white shadow-md">
                                    {filtered.map((p) => (
                                        <button key={p.id} type="button" onClick={() => addProduct(p)}
                                            className="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-gray-50">
                                            {p.primary_image && <img src={p.primary_image.url} className="h-8 w-8 rounded object-cover" />}
                                            <div className="flex-1">
                                                <p className="font-medium">{p.name}</p>
                                                <div className="flex gap-2">
                                                    {p.sku && <span className="text-xs text-gray-400">SKU: {p.sku}</span>}
                                                    {p.barcode && <span className="text-xs text-gray-400">CB: {p.barcode}</span>}
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className="font-medium">${parseFloat(p.price).toFixed(2)}</p>
                                                <p className={`text-xs ${p.stock <= 0 ? 'text-red-500' : 'text-gray-400'}`}>Stock: {p.stock}</p>
                                            </div>
                                        </button>
                                    ))}
                                    {filtered.length === 0 && <p className="px-3 py-2 text-sm text-gray-400">Sin resultados.</p>}
                                </div>
                            )}
                        </div>

                        {combos.length > 0 && (
                            <div className="rounded-lg border bg-white p-4 shadow-sm">
                                <Label>Combos disponibles</Label>
                                <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    {combos.map((c) => {
                                        const regular = c.items.reduce((s, it) => s + (parseFloat(it.price) || 0) * (parseFloat(it.quantity) || 0), 0);
                                        const price = parseFloat(c.combo_price) || 0;
                                        const saving = regular - price;
                                        return (
                                            <button key={c.id} type="button" onClick={() => addCombo(c)}
                                                className="flex flex-col gap-1 rounded-md border border-indigo-200 bg-indigo-50/50 p-3 text-left transition hover:border-indigo-400 hover:bg-indigo-50">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="font-medium text-indigo-900">{c.name}</span>
                                                    <span className="font-bold text-indigo-700">${price.toFixed(2)}</span>
                                                </div>
                                                <span className="text-xs text-gray-500">
                                                    {c.items.map((it) => `${parseFloat(it.quantity)}× ${it.name}`).join(' + ')}
                                                </span>
                                                {saving > 0 && (
                                                    <span className="text-xs font-medium text-green-600">Ahorras ${saving.toFixed(2)}</span>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        <div className="rounded-lg border bg-white shadow-sm">
                            <div className="border-b px-4 py-3 font-semibold text-gray-700">Artículos ({data.items.length})</div>
                            {data.items.length === 0 ? (
                                <div className="px-4 py-12 text-center text-gray-400">
                                    <Plus className="mx-auto mb-2 h-8 w-8" />
                                    <p>Busca y agrega productos</p>
                                    <p className="mt-1 text-xs">o usa el escáner de código de barras</p>
                                </div>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-gray-50 text-xs uppercase text-gray-400">
                                        <tr>
                                            <th className="px-4 py-2 text-left">Producto</th>
                                            <th className="px-4 py-2 text-right">Precio</th>
                                            <th className="px-4 py-2 text-right">Descuento</th>
                                            <th className="px-4 py-2 text-right">Cant.</th>
                                            <th className="px-4 py-2 text-right">Subtotal</th>
                                            <th className="px-4 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {data.items.map((item: Item, i: number) => (
                                            <tr key={i}>
                                                <td className="px-4 py-2 font-medium">
                                                    {item.product_name}
                                                    {item.combo_name && (
                                                        <span className="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">{item.combo_name}</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2"><Input className="w-24 text-right" type="number" step="0.01" min="0" value={item.price} onChange={(e) => setItem(i, 'price', e.target.value)} /></td>
                                                <td className="px-4 py-2"><Input className="w-20 text-right" type="number" step="0.01" min="0" value={item.discount} onChange={(e) => setItem(i, 'discount', e.target.value)} /></td>
                                                <td className="px-4 py-2"><Input className="w-20 text-right" type="number" step="0.01" min="0.01" value={item.quantity} onChange={(e) => setItem(i, 'quantity', e.target.value)} /></td>
                                                <td className="px-4 py-2 text-right font-medium">
                                                    ${((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0) - (parseFloat(item.discount) || 0)).toFixed(2)}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => removeItem(i)}><Trash2 className="h-4 w-4 text-red-400" /></Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                            {errors.items && <p className="px-4 py-2 text-xs text-red-500">{errors.items}</p>}
                        </div>
                    </div>

                    {/* Columna derecha: totales y pago */}
                    <div className="space-y-4">
                        <div className="rounded-lg border bg-white p-4 shadow-sm">
                            <h2 className="mb-3 font-semibold text-gray-700">Turno de caja</h2>
                            <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.cash_shift_id} onChange={(e) => setData('cash_shift_id', e.target.value)}>
                                <option value="">— Seleccionar —</option>
                                {openShifts.map((s) => <option key={s.id} value={s.id}>{s.cash_register.name} — {s.cash_register.store.name}</option>)}
                            </select>
                            {errors.cash_shift_id && <p className="mt-1 text-xs text-red-500">{errors.cash_shift_id}</p>}
                        </div>

                        <div className="rounded-lg border bg-white p-4 shadow-sm space-y-3">
                            <h2 className="font-semibold text-gray-700">Cliente y promoción</h2>
                            <div>
                                <Label>Cliente</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.customer_id} onChange={(e) => setData('customer_id', e.target.value)}>
                                    <option value="">— Consumidor final —</option>
                                    {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <Label>Promoción / cupón</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.promotion_id} onChange={(e) => setData('promotion_id', e.target.value)}>
                                    <option value="">— Sin promoción —</option>
                                    {promotions.map((p) => <option key={p.id} value={p.id}>{p.name}{p.code ? ` (${p.code})` : ''}</option>)}
                                </select>
                                {selectedPromo && promoBelowMin && (
                                    <p className="mt-1 text-xs text-amber-600">Compra mínima de ${parseFloat(selectedPromo.min_purchase).toFixed(2)} para aplicar esta promoción.</p>
                                )}
                                {selectedPromo && !promoBelowMin && promoDiscount <= 0 && (
                                    <p className="mt-1 text-xs text-amber-600">La promoción no aplica a los productos del carrito.</p>
                                )}
                                {errors.promotion_id && <p className="mt-1 text-xs text-red-500">{errors.promotion_id}</p>}
                            </div>
                        </div>

                        <div className="rounded-lg border bg-white p-4 shadow-sm space-y-3">
                            <h2 className="font-semibold text-gray-700">Resumen</h2>
                            <div className="flex justify-between text-sm"><span className="text-gray-500">Subtotal</span><span>${subtotal.toFixed(2)}</span></div>
                            {selectedPromo ? (
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">Descuento ({selectedPromo.name})</span>
                                    <span className="font-medium text-green-600">-${promoDiscount.toFixed(2)}</span>
                                </div>
                            ) : (
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">Descuento global</span>
                                    <Input className="w-24 text-right" type="number" step="0.01" min="0" value={data.discount} onChange={(e) => setData('discount', e.target.value)} />
                                </div>
                            )}
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-gray-500">IVA</span>
                                <Input className="w-24 text-right" type="number" step="0.01" min="0" value={data.tax} onChange={(e) => setData('tax', e.target.value)} />
                            </div>
                            <div className="flex justify-between border-t pt-2 text-lg font-bold"><span>Total</span><span>${total.toFixed(2)}</span></div>
                        </div>

                        <div className="rounded-lg border bg-white p-4 shadow-sm space-y-3">
                            <h2 className="font-semibold text-gray-700">Pago</h2>
                            <div>
                                <Label>Método de pago</Label>
                                <select className="w-full rounded-md border px-3 py-2 text-sm" value={data.payment_method} onChange={(e) => setData('payment_method', e.target.value)}>
                                    <option value="cash">Efectivo</option>
                                    <option value="card">Tarjeta</option>
                                    <option value="transfer">Transferencia</option>
                                    <option value="mixed">Mixto</option>
                                </select>
                            </div>
                            <div>
                                <Label>Monto recibido *</Label>
                                <Input type="number" step="0.01" min="0" value={data.amount_paid} onChange={(e) => setData('amount_paid', e.target.value)} className="text-xl font-bold" />
                                {errors.amount_paid && <p className="mt-1 text-xs text-red-500">{errors.amount_paid}</p>}
                            </div>
                            {data.amount_paid && (
                                <div className={`rounded-lg p-3 text-center ${change >= 0 ? 'bg-green-50' : 'bg-red-50'}`}>
                                    <p className="text-xs text-gray-500">Cambio</p>
                                    <p className={`text-2xl font-bold ${change >= 0 ? 'text-green-700' : 'text-red-700'}`}>${Math.abs(change).toFixed(2)}</p>
                                </div>
                            )}
                            <div>
                                <Label>Notas</Label>
                                <textarea className="w-full rounded-md border px-3 py-2 text-sm" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </div>
                        </div>

                        <Button type="submit" className="w-full" size="lg" disabled={processing || data.items.length === 0}>
                            Registrar Venta
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
