import { SVGAttributes } from 'react';

/**
 * Monograma de la aplicación: la «A» de AvisV3.
 *
 * Va trazada y no rellenada porque el menú la pinta a 20 px cuando está
 * colapsado: un contorno de grosor constante se sigue leyendo a ese tamaño,
 * mientras que una letra rellena se empasta. Hereda el color con
 * `currentColor`, así que sirve en claro y en oscuro sin variantes.
 *
 * Es decorativa: el nombre lo pone el texto de al lado, de modo que quien
 * la use debe marcarla `aria-hidden` o darle un `aria-label` propio.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2.4}
            strokeLinecap="round"
            strokeLinejoin="round"
            {...props}
        >
            {/* Los dos trazos de la A. */}
            <path d="M4 20 12 4l8 16" />
            {/* Travesaño, ligeramente bajo para que el contrapunzón respire. */}
            <path d="M7.7 14.6h8.6" />
        </svg>
    );
}
