import { SVGAttributes } from 'react';

/**
 * Monograma de AVIS, redibujado a partir de `docs/logo1.jpeg`: la «A» en azul
 * marino con su pieza turquesa.
 *
 * Las partes azul marino usan `currentColor`, así que el mismo dibujo sirve
 * sobre fondo claro (heredando el marino) y sobre el menú oscuro (en blanco).
 * La pieza turquesa no cambia: es lo que hace reconocible la marca.
 *
 * Es decorativa: el nombre lo pone el texto de al lado, de modo que quien
 * la use debe marcarla `aria-hidden` o darle un `aria-label` propio.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg viewBox="0 0 48 48" strokeLinejoin="round" strokeWidth={2.2} {...props}>
            <path d="M1.4 43.4 20 5.2h12.8l10.4 18.2-14.9-7L13.5 43.4Z" fill="currentColor" stroke="currentColor" />
            <path d="M27.4 18.6 46 27.3l1 16.1H36.6V33.3l-9.2-4.3Z" fill="var(--brand-teal)" stroke="var(--brand-teal)" />
            <path d="m22.6 29.8 11 5.1v8.5l-14.6-6.6Z" fill="currentColor" stroke="currentColor" />
        </svg>
    );
}
