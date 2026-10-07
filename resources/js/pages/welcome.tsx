import { Pricing } from '@/components/marketing/pricing';
import { ProductMock } from '@/components/marketing/product-mock';
import { Button } from '@/components/ui/button';
import MarketingLayout from '@/layouts/marketing-layout';
import { type ModuleCatalog, type Plan } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Banknote,
    Boxes,
    ChartColumn,
    Check,
    ChevronDown,
    ClipboardList,
    FileCheck2,
    ScanBarcode,
    Truck,
    UserCog,
    Users,
    type LucideIcon,
} from 'lucide-react';

interface WelcomeProps {
    plans: Plan[];
    modules: ModuleCatalog;
    signupEnabled: boolean;
    contact: { support_email?: string | null; support_whatsapp?: string | null };
}

const PASOS = [
    {
        titulo: 'Cobras en el mostrador',
        texto: 'Escaneas los productos, eliges cómo paga el cliente y cierras la venta. La caja lleva su turno con apertura, cierre y arqueo.',
    },
    {
        titulo: 'El inventario se descuenta solo',
        texto: 'Cada unidad vendida sale de las existencias de esa tienda. Sabes qué queda, dónde, y qué está por agotarse.',
    },
    {
        titulo: 'La factura sale en el mismo cobro',
        texto: 'La factura electrónica se emite ante el SIN con su CUF y su QR, sin volver a escribir nada. Si se corta la conexión, se emite en contingencia y se envía después.',
    },
];

const AREAS: { icono: LucideIcon; titulo: string; texto: string; puntos: string[] }[] = [
    {
        icono: ScanBarcode,
        titulo: 'Punto de venta',
        texto: 'Pensado para atender con cola: rápido con teclado, con lector de códigos o con el dedo.',
        puntos: ['Turnos de caja con apertura y cierre', 'Varias formas de pago', 'Recibo imprimible', 'Devoluciones'],
    },
    {
        icono: Boxes,
        titulo: 'Inventario por tienda',
        texto: 'Las existencias de cada sucursal por separado, con su mínimo y su historial de movimientos.',
        puntos: ['Categorías, marcas y etiquetas', 'Alertas de stock bajo', 'Transferencias entre tiendas', 'Kárdex de cada producto'],
    },
    {
        icono: Truck,
        titulo: 'Compras y proveedores',
        texto: 'Del pedido a la recepción: lo que llega entra al stock de la tienda que lo recibe.',
        puntos: ['Órdenes de compra', 'Recepción parcial o total', 'Evaluación de proveedores', 'Cuentas por pagar'],
    },
    {
        icono: Users,
        titulo: 'Clientes y gestión comercial',
        texto: 'Todo lo que pasa antes y después de la venta, ligado al mismo cliente.',
        puntos: ['Cotizaciones', 'Pedidos y envíos', 'Promociones y combos', 'Garantías'],
    },
    {
        icono: Banknote,
        titulo: 'Finanzas',
        texto: 'El dinero que entra y sale del negocio, más allá de la caja.',
        puntos: ['Gastos e ingresos', 'Retiros', 'Cuentas por cobrar', 'Reporte financiero'],
    },
    {
        icono: UserCog,
        titulo: 'Recursos humanos',
        texto: 'El equipo que atiende, con su asistencia y su planilla.',
        puntos: ['Empleados y áreas', 'Asistencia y ausencias', 'Nómina', 'Capacitación'],
    },
    {
        icono: ChartColumn,
        titulo: 'Reportes',
        texto: 'Respuestas a las preguntas de fin de mes sin armar hojas de cálculo.',
        puntos: ['Ventas por periodo y producto', 'Compras por proveedor', 'Resultado financiero', 'Indicadores de personal'],
    },
];

const SIAT = [
    'Emisión en línea con CUF y código QR',
    'Contingencia por paquetes cuando no hay conexión',
    'Notas de crédito y débito',
    'Anulación de facturas',
    'Registro de compras',
    'Varios puntos de venta por sucursal',
    'CUIS y CUFD gestionados desde el sistema',
    'Catálogos del SIN sincronizados',
];

const PREGUNTAS = [
    {
        p: '¿Tengo que instalar algo?',
        r: 'No. AVIS funciona en el navegador de tu computadora, tablet o teléfono. Cada empresa entra por su propia dirección.',
    },
    {
        p: '¿Mis datos se mezclan con los de otras empresas?',
        r: 'No. Cada empresa tiene su propia base de datos, separada de las demás. Tus ventas, tus clientes y tus facturas solo las ve tu equipo.',
    },
    {
        p: '¿Cómo pago un plan?',
        r: 'Por QR o transferencia bancaria. Subes el comprobante desde «Plan y pagos» y, cuando lo confirmamos, tu plan queda al día.',
    },
    {
        p: '¿Qué pasa cuando termina la prueba?',
        r: 'Eliges: pagas el plan para seguir igual, o pasas al plan Gratis. En los dos casos tus datos se conservan.',
    },
    {
        p: '¿Puedo cambiar de plan más adelante?',
        r: 'Sí. Desde «Plan y pagos» ves cuánto usas de tu plan y puedes subir o bajar cuando lo necesites.',
    },
    {
        p: '¿Sirve si tengo varias sucursales?',
        r: 'Sí. Cada tienda lleva sus cajas y sus existencias, y puedes transferir mercadería entre ellas. La cantidad de tiendas depende del plan.',
    },
];

export default function Welcome({ plans, modules, signupEnabled, contact }: WelcomeProps) {
    const hayPlanes = signupEnabled && plans.length > 0;
    const gratis = plans.find((p) => p.is_free);

    const secciones = [
        { href: '#funciones', label: 'Funciones' },
        { href: '#facturacion', label: 'Facturación' },
        ...(hayPlanes ? [{ href: '#planes', label: 'Planes' }] : []),
        { href: '#preguntas', label: 'Preguntas' },
    ];

    const accion = signupEnabled ? { href: '/registro', texto: gratis ? 'Crear cuenta gratis' : 'Crear cuenta' } : { href: '/ingresar', texto: 'Ingresar' };

    return (
        <MarketingLayout sections={secciones} signupEnabled={signupEnabled} contact={contact} overHero>
            <Head title="Inventarios y facturación electrónica">
                <meta
                    name="description"
                    content="AVIS reúne punto de venta, inventario por tienda y facturación electrónica SIAT para comercios de Bolivia. Crea tu cuenta y empieza a vender hoy."
                />
            </Head>

            {/* ── Portada ─────────────────────────────────────────────── */}
            <section className="bg-brand-navy relative overflow-hidden text-white dark:bg-[#050d1b]">
                <div className="mx-auto grid max-w-6xl items-center gap-12 px-4 pt-32 pb-20 sm:px-6 lg:grid-cols-[1.15fr_1fr] lg:gap-14 lg:pt-40 lg:pb-28">
                    <div>
                        <h1 className="text-[clamp(2.125rem,4.4vw,3.375rem)] leading-[1.08] font-semibold tracking-[-0.03em] text-pretty">
                            Vende, controla tu inventario y factura al SIN desde un solo lugar.
                        </h1>
                        <p className="mt-6 max-w-[34rem] text-lg leading-relaxed text-white/80">
                            AVIS une el punto de venta, las existencias de cada tienda y la facturación electrónica. Lo que cobras en el mostrador queda
                            descontado del stock y facturado, sin escribirlo dos veces.
                        </p>

                        <div className="mt-9 flex flex-wrap items-center gap-3">
                            <Button asChild size="lg" variant="brand" className="px-7">
                                <Link href={accion.href}>{accion.texto}</Link>
                            </Button>
                            {hayPlanes && (
                                <Button asChild size="lg" variant="ghost" className="text-white hover:bg-white/10 hover:text-white">
                                    <a href="#planes">Ver planes</a>
                                </Button>
                            )}
                        </div>

                        {signupEnabled && gratis && (
                            <p className="mt-5 text-sm text-white/65">No pide tarjeta. El plan Gratis no vence.</p>
                        )}
                    </div>

                    <ProductMock />
                </div>
            </section>

            {/* ── El mecanismo: una venta, tres efectos ───────────────── */}
            <section className="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:py-28">
                <h2 className="max-w-2xl text-3xl leading-tight font-semibold sm:text-4xl">Una venta se registra una vez y lo demás ocurre solo.</h2>

                <ol className="mt-12 grid gap-10 md:grid-cols-3 md:gap-8">
                    {PASOS.map((paso, i) => (
                        <li key={paso.titulo} className="relative border-t-2 border-[var(--brand-teal)] pt-6">
                            <span className="font-display text-brand-teal-ink text-sm font-semibold">Paso {i + 1}</span>
                            <h3 className="mt-2 text-xl font-semibold">{paso.titulo}</h3>
                            <p className="text-muted-foreground mt-3 leading-relaxed">{paso.texto}</p>
                        </li>
                    ))}
                </ol>
            </section>

            {/* ── Funciones ──────────────────────────────────────────── */}
            <section id="funciones" className="bg-card scroll-mt-16 border-y">
                <div className="mx-auto grid max-w-6xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-[0.8fr_1.6fr] lg:gap-16 lg:py-28">
                    <div className="lg:sticky lg:top-28 lg:self-start">
                        <h2 className="text-3xl leading-tight font-semibold sm:text-4xl">Todo el negocio en el mismo sistema.</h2>
                        <p className="text-muted-foreground mt-5 leading-relaxed">
                            Siete áreas que comparten los mismos productos, clientes y tiendas. Lo que registras en una ya lo saben las otras.
                        </p>
                        <ClipboardList className="text-brand-teal-ink mt-8 hidden size-10 lg:block" aria-hidden="true" strokeWidth={1.5} />
                    </div>

                    <dl className="divide-y">
                        {AREAS.map(({ icono: Icono, titulo, texto, puntos }) => (
                            <div key={titulo} className="grid gap-x-6 gap-y-4 py-8 first:pt-0 last:pb-0 sm:grid-cols-[auto_1fr]">
                                <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-lg">
                                    <Icono className="size-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <dt className="font-display text-lg font-semibold">{titulo}</dt>
                                    <dd className="text-muted-foreground mt-1.5 leading-relaxed">{texto}</dd>
                                    <dd className="mt-4">
                                        <ul className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                                            {puntos.map((punto) => (
                                                <li key={punto} className="flex gap-2.5">
                                                    <Check className="text-brand-teal-ink mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                                    {punto}
                                                </li>
                                            ))}
                                        </ul>
                                    </dd>
                                </div>
                            </div>
                        ))}
                    </dl>
                </div>
            </section>

            {/* ── Facturación SIAT ───────────────────────────────────── */}
            <section id="facturacion" className="scroll-mt-16">
                <div className="mx-auto grid max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:py-28">
                    <div>
                        <FileCheck2 className="text-brand-teal-ink size-10" aria-hidden="true" strokeWidth={1.5} />
                        <h2 className="mt-6 text-3xl leading-tight font-semibold sm:text-4xl">Facturación electrónica dentro de la venta, no al lado.</h2>
                        <p className="text-muted-foreground mt-5 leading-relaxed">
                            AVIS habla con los servicios del SIAT del Servicio de Impuestos Nacionales. Configuras tu NIT y tus puntos de venta una vez; después,
                            facturar es cobrar.
                        </p>
                    </div>

                    <ul className="bg-brand-navy grid gap-x-8 gap-y-4 rounded-2xl p-8 text-white sm:grid-cols-2 sm:p-10 dark:bg-[#0c1c36]">
                        {SIAT.map((item) => (
                            <li key={item} className="flex gap-3 text-[0.9375rem] leading-snug">
                                <Check className="mt-0.5 size-4 shrink-0 text-[#4fd6cd]" aria-hidden="true" />
                                {item}
                            </li>
                        ))}
                    </ul>
                </div>
            </section>

            {/* ── Planes ─────────────────────────────────────────────── */}
            {hayPlanes && (
                <section id="planes" className="bg-card scroll-mt-16 border-y">
                    <div className="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:py-28">
                        <div className="mx-auto mb-12 max-w-2xl text-center">
                            <h2 className="text-3xl leading-tight font-semibold sm:text-4xl">Un plan para cada tamaño de negocio.</h2>
                            <p className="text-muted-foreground mt-4 leading-relaxed">
                                Empieza gratis y sube de plan cuando lo necesites. Los cupos y los módulos están a la vista: no hay costos por fuera.
                            </p>
                        </div>
                        <Pricing plans={plans} modules={modules} />
                    </div>
                </section>
            )}

            {/* ── Preguntas ──────────────────────────────────────────── */}
            <section id="preguntas" className="mx-auto max-w-3xl scroll-mt-16 px-4 py-20 sm:px-6 lg:py-28">
                <h2 className="text-3xl leading-tight font-semibold sm:text-4xl">Preguntas frecuentes</h2>

                <div className="mt-10 divide-y border-y">
                    {PREGUNTAS.map(({ p, r }) => (
                        <details key={p} className="group">
                            <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-4 text-left text-[1.0625rem] font-semibold [&::-webkit-details-marker]:hidden">
                                {p}
                                <ChevronDown
                                    className="text-muted-foreground size-5 shrink-0 transition-transform duration-200 group-open:rotate-180"
                                    aria-hidden="true"
                                />
                            </summary>
                            <p className="text-muted-foreground max-w-[65ch] pr-9 pb-5 leading-relaxed">{r}</p>
                        </details>
                    ))}
                </div>
            </section>

            {/* ── Cierre ─────────────────────────────────────────────── */}
            <section className="bg-brand-navy text-white dark:bg-[#050d1b]">
                <div className="mx-auto flex max-w-6xl flex-col items-start gap-8 px-4 py-16 sm:px-6 md:flex-row md:items-center md:justify-between lg:py-20">
                    <div>
                        <h2 className="max-w-xl text-3xl leading-tight font-semibold sm:text-4xl">Tu primera venta en AVIS puede ser hoy.</h2>
                        <p className="mt-3 max-w-xl leading-relaxed text-white/75">
                            {signupEnabled
                                ? 'Crea la cuenta de tu empresa, carga tus productos y abre caja.'
                                : 'Entra con tu usuario y sigue donde lo dejaste.'}
                        </p>
                    </div>
                    <Button asChild size="lg" variant="brand" className="shrink-0 px-7">
                        <Link href={accion.href}>{accion.texto}</Link>
                    </Button>
                </div>
            </section>
        </MarketingLayout>
    );
}
