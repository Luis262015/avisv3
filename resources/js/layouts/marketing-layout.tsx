import { Brand } from '@/components/brand';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

interface MarketingLayoutProps {
    children: ReactNode;
    /** Enlaces de sección; fuera de la portada apuntan a `/#…`. */
    sections?: { href: string; label: string }[];
    signupEnabled?: boolean;
    contact?: { support_email?: string | null; support_whatsapp?: string | null };
    /** La portada abre sobre azul marino y la barra arranca transparente. */
    overHero?: boolean;
}

const ID_CONTENIDO = 'contenido';

/**
 * Marco del sitio público: barra superior, contenido y pie.
 */
export default function MarketingLayout({ children, sections = [], signupEnabled = true, contact, overHero = false }: MarketingLayoutProps) {
    const [menuAbierto, setMenuAbierto] = useState(false);
    const [desplazado, setDesplazado] = useState(false);

    useEffect(() => {
        const alDesplazar = () => setDesplazado(window.scrollY > 12);
        alDesplazar();
        window.addEventListener('scroll', alDesplazar, { passive: true });
        return () => window.removeEventListener('scroll', alDesplazar);
    }, []);

    // Sobre el campo marino de la portada la barra es clara; al bajar, o en
    // cualquier otra página, pasa a fondo sólido.
    const sobreMarino = overHero && !desplazado && !menuAbierto;

    return (
        <div className="bg-background text-foreground min-h-svh">
            <a
                href={`#${ID_CONTENIDO}`}
                className="bg-card text-foreground sr-only shadow-lg focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:rounded-md focus:px-4 focus:py-2 focus:text-sm focus:font-medium"
            >
                Saltar al contenido
            </a>

            <header
                className={cn(
                    'fixed inset-x-0 top-0 z-50 transition-[background-color,border-color,box-shadow] duration-200',
                    sobreMarino ? 'border-b border-transparent' : 'bg-card/95 border-b shadow-sm backdrop-blur-md',
                )}
            >
                <div className="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 sm:px-6">
                    <Link href="/" aria-label="AVIS — inicio" className="shrink-0">
                        <Brand className={sobreMarino ? 'text-white' : undefined} iconClassName="size-8" />
                    </Link>

                    <nav aria-label="Secciones" className="hidden flex-1 items-center gap-1 md:flex">
                        {sections.map((s) => (
                            <a
                                key={s.href}
                                href={s.href}
                                className={cn(
                                    'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                    sobreMarino ? 'text-white/85 hover:text-white' : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {s.label}
                            </a>
                        ))}
                    </nav>

                    <div className="ml-auto hidden items-center gap-2 md:flex">
                        <Button
                            asChild
                            variant="ghost"
                            className={sobreMarino ? 'text-white hover:bg-white/10 hover:text-white' : undefined}
                        >
                            <Link href="/ingresar">Ingresar</Link>
                        </Button>
                        {signupEnabled && (
                            <Button asChild variant={sobreMarino ? 'brand' : 'default'}>
                                <Link href="/registro">Crear cuenta</Link>
                            </Button>
                        )}
                    </div>

                    <button
                        type="button"
                        className={cn('ml-auto flex size-11 items-center justify-center rounded-md md:hidden', sobreMarino ? 'text-white' : 'text-foreground')}
                        aria-expanded={menuAbierto}
                        aria-controls="menu-movil"
                        aria-label={menuAbierto ? 'Cerrar menú' : 'Abrir menú'}
                        onClick={() => setMenuAbierto((v) => !v)}
                    >
                        {menuAbierto ? <X className="size-5" aria-hidden="true" /> : <Menu className="size-5" aria-hidden="true" />}
                    </button>
                </div>

                {menuAbierto && (
                    <nav id="menu-movil" aria-label="Secciones" className="border-t px-4 pt-2 pb-5 md:hidden">
                        {sections.map((s) => (
                            <a
                                key={s.href}
                                href={s.href}
                                onClick={() => setMenuAbierto(false)}
                                className="flex min-h-12 items-center border-b text-base font-medium"
                            >
                                {s.label}
                            </a>
                        ))}
                        <div className="mt-4 grid gap-2">
                            <Button asChild variant="outline" size="lg">
                                <Link href="/ingresar">Ingresar</Link>
                            </Button>
                            {signupEnabled && (
                                <Button asChild size="lg">
                                    <Link href="/registro">Crear cuenta</Link>
                                </Button>
                            )}
                        </div>
                    </nav>
                )}
            </header>

            <main id={ID_CONTENIDO} tabIndex={-1} className="focus:outline-none">
                {children}
            </main>

            <footer className="border-t">
                <div className="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <Brand descriptor />
                        <p className="text-muted-foreground mt-4 max-w-xs text-sm leading-relaxed">
                            Punto de venta, inventario y facturación electrónica para comercios de Bolivia.
                        </p>
                    </div>

                    <nav aria-label="Pie de página" className="grid content-start gap-1 text-sm">
                        <h2 className="mb-2 font-sans text-sm font-semibold tracking-normal">Producto</h2>
                        <a href="/#funciones" className="text-muted-foreground hover:text-foreground py-1">
                            Funciones
                        </a>
                        <a href="/#planes" className="text-muted-foreground hover:text-foreground py-1">
                            Planes
                        </a>
                        <Link href="/ingresar" className="text-muted-foreground hover:text-foreground py-1">
                            Ingresar
                        </Link>
                        {signupEnabled && (
                            <Link href="/registro" className="text-muted-foreground hover:text-foreground py-1">
                                Crear cuenta
                            </Link>
                        )}
                    </nav>

                    {(contact?.support_email || contact?.support_whatsapp) && (
                        <div className="grid content-start gap-1 text-sm">
                            <h2 className="mb-2 font-sans text-sm font-semibold tracking-normal">Contacto</h2>
                            {contact.support_email && (
                                <a href={`mailto:${contact.support_email}`} className="text-muted-foreground hover:text-foreground py-1 break-all">
                                    {contact.support_email}
                                </a>
                            )}
                            {contact.support_whatsapp && (
                                <a
                                    href={`https://wa.me/${contact.support_whatsapp.replace(/\D/g, '')}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-muted-foreground hover:text-foreground py-1"
                                    aria-label={`WhatsApp ${contact.support_whatsapp} (se abre en una pestaña nueva)`}
                                >
                                    WhatsApp {contact.support_whatsapp}
                                </a>
                            )}
                        </div>
                    )}
                </div>
                <div className="border-t">
                    <p className="text-muted-foreground mx-auto max-w-6xl px-4 py-5 text-xs sm:px-6">© {new Date().getFullYear()} AVIS. Hecho en Bolivia.</p>
                </div>
            </footer>
        </div>
    );
}
