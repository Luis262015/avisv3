import { Brand } from '@/components/brand';
import { ThemeToggle } from '@/components/theme-toggle';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { FileCheck2, PackageSearch, ScanBarcode } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title: string;
    description: string;
    /** Sustituye el panel de marca: el panel de la plataforma dice otra cosa. */
    aside?: React.ReactNode;
}

const PUNTOS = [
    { icono: ScanBarcode, texto: 'Vende desde el mostrador con lector de códigos y turnos de caja.' },
    { icono: FileCheck2, texto: 'La factura electrónica sale del mismo cobro, validada por el SIN.' },
    { icono: PackageSearch, texto: 'Las existencias de cada tienda se descuentan solas con cada venta.' },
];

/**
 * Pantallas de acceso: el formulario a un lado y la marca al otro.
 *
 * El formulario va primero en el código y a la izquierda en pantalla: es lo
 * que viene a hacer quien llega aquí, y con teclado o lector de pantalla no
 * hay que atravesar el panel de marca para alcanzarlo. En pantallas pequeñas
 * el panel desaparece y queda solo el formulario.
 */
export default function AuthLayout({ children, title, description, aside }: AuthLayoutProps) {
    const { tenant } = usePage<SharedData>().props;

    return (
        <div className="bg-card grid min-h-svh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
            <main className="flex flex-col px-6 py-6 sm:px-10">
                <div className="flex items-center justify-between">
                    <Brand descriptor />
                    <ThemeToggle />
                </div>

                <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">
                    {tenant && <p className="text-brand-teal-ink mb-3 text-sm font-semibold">{tenant.name}</p>}
                    <h1 className="text-3xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground mt-2 mb-8 text-[0.9375rem] leading-relaxed">{description}</p>
                    {children}
                </div>
            </main>

            <aside className="bg-brand-navy relative hidden overflow-hidden text-white lg:flex lg:flex-col lg:justify-center lg:px-14 xl:px-20 dark:bg-[#050d1b]">
                {/* La pieza turquesa del logo, a gran escala, como única figura. */}
                <svg
                    viewBox="0 0 48 48"
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-24 -bottom-28 w-[34rem] opacity-[0.14]"
                    strokeLinejoin="round"
                    strokeWidth={2.2}
                >
                    <path d="M27.4 18.6 46 27.3l1 16.1H36.6V33.3l-9.2-4.3Z" fill="var(--brand-teal)" stroke="var(--brand-teal)" />
                    <path d="M1.4 43.4 20 5.2h12.8l10.4 18.2-14.9-7L13.5 43.4Z" fill="#fff" stroke="#fff" />
                </svg>

                <div className="relative max-w-md">
                    {aside ?? (
                        <>
                            <p className="font-display text-[2rem] leading-[1.15] font-semibold tracking-[-0.02em] text-balance">
                                Tu venta, tu inventario y tu factura, en un solo paso.
                            </p>
                            <ul className="mt-10 space-y-5">
                                {PUNTOS.map(({ icono: Icono, texto }) => (
                                    <li key={texto} className="flex gap-4 text-[0.9375rem] leading-relaxed text-white/85">
                                        <Icono className="mt-0.5 size-5 shrink-0 text-[#4fd6cd]" aria-hidden="true" />
                                        {texto}
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </div>
            </aside>
        </div>
    );
}
