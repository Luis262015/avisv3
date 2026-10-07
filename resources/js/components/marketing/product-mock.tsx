import { ArrowDown, BadgeCheck, ScanBarcode } from 'lucide-react';

const LINEAS = [
    { cantidad: 2, nombre: 'Aceite vegetal 1 L', importe: '36,00' },
    { cantidad: 1, nombre: 'Arroz grano largo 5 kg', importe: '58,50' },
    { cantidad: 3, nombre: 'Detergente 900 g', importe: '67,50' },
];

/**
 * La venta, el descuento de existencias y la factura, en una sola imagen.
 *
 * Es una representación con datos de ejemplo —está rotulada como tal— y no
 * aporta nada a un lector de pantalla: lo mismo se dice con palabras en el
 * titular y en la sección siguiente. Por eso va entera como `aria-hidden`.
 *
 * Las dos tarjetas que se desprenden de la venta entran una tras otra al
 * cargar: es el único movimiento de la página y cuenta el orden en que pasan
 * las cosas.
 */
export function ProductMock() {
    return (
        <div aria-hidden="true" className="relative mx-auto w-full max-w-[30rem] select-none lg:max-w-none">
            {/* Ventana de la venta */}
            <div className="text-foreground overflow-hidden rounded-2xl bg-white shadow-[0_30px_60px_-20px_rgba(2,12,32,0.55)] dark:bg-[#0c1c36]">
                <div className="flex items-center gap-3 border-b px-5 py-3.5">
                    <ScanBarcode className="text-brand-teal-ink size-5" />
                    <span className="font-display text-[0.9375rem] font-semibold">Nueva venta</span>
                    <span className="text-muted-foreground ml-auto text-xs">Caja 1 · Casa matriz</span>
                </div>

                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-muted-foreground text-left text-xs">
                            <th className="py-2.5 pl-5 font-medium">Cant.</th>
                            <th className="py-2.5 font-medium">Producto</th>
                            <th className="py-2.5 pr-5 text-right font-medium">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        {LINEAS.map((l) => (
                            <tr key={l.nombre} className="border-t">
                                <td className="py-3 pl-5 font-medium">{l.cantidad}</td>
                                <td className="py-3">{l.nombre}</td>
                                <td className="py-3 pr-5 text-right">{l.importe}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="bg-muted flex items-end justify-between px-5 py-4">
                    <div>
                        <p className="text-muted-foreground text-xs">Total a cobrar</p>
                        <p className="font-display text-2xl leading-tight font-semibold">Bs 162,00</p>
                    </div>
                    <span className="bg-brand-navy rounded-lg px-5 py-2.5 text-sm font-semibold text-white dark:bg-[#2bc7bd] dark:text-[#04182f]">Cobrar</span>
                </div>
            </div>

            {/* Lo que la venta desencadena */}
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <div className="mock-paso text-foreground rounded-xl bg-white p-4 shadow-[0_18px_40px_-18px_rgba(2,12,32,0.5)] [animation-delay:500ms] dark:bg-[#0c1c36]">
                    <p className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                        <ArrowDown className="size-3.5" /> Existencias en Casa matriz
                    </p>
                    <p className="mt-2 text-sm font-medium">Aceite vegetal 1 L</p>
                    <p className="font-display mt-0.5 text-xl font-semibold">
                        <span className="text-muted-foreground font-normal line-through decoration-1">48</span> 46{' '}
                        <span className="text-muted-foreground font-sans text-xs font-normal">unidades</span>
                    </p>
                </div>

                <div className="mock-paso rounded-xl bg-[#00ada4] p-4 text-[#04202b] shadow-[0_18px_40px_-18px_rgba(2,12,32,0.5)] [animation-delay:950ms]">
                    <p className="flex items-center gap-1.5 text-xs font-semibold">
                        <BadgeCheck className="size-3.5" /> Factura electrónica
                    </p>
                    <p className="mt-2 text-sm font-semibold">N.º 412 · Validada</p>
                    <p className="mt-0.5 truncate font-mono text-xs opacity-80">CUF 4A1F9C…E27B</p>
                </div>
            </div>

            <p className="mt-3 text-right text-xs text-white/60">Pantalla ilustrativa con datos de ejemplo.</p>
        </div>
    );
}
