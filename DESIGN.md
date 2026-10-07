# Sistema de diseño de AVIS

La identidad sale de `docs/logo1.jpeg`: azul marino y turquesa, una «A» geométrica. Dirección elegida para el sitio público: el formato SaaS conocido, bien ejecutado. Los valores viven en `resources/css/app.css`; este documento explica cómo usarlos.

## Color

| Token | Claro | Oscuro | Uso |
|---|---|---|---|
| `--brand-navy` | `#0a2550` | — | Campo de marca: menú lateral, portada, cierre. |
| `--brand-teal` | `#00ada4` | — | Relleno de la acción destacada sobre marino. Nunca como texto sobre blanco (2.8:1). |
| `--brand-teal-ink` | `#00756f` | `#4fd6cd` | El turquesa cuando es texto, enlace o icono. |
| `--background` / `--card` | `#f5f7fa` / `#fff` | `#071224` / `#0c1c36` | Fondo de página y superficies. |
| `--foreground` / `--muted-foreground` | `#0b1d3a` / `#4e5d78` | `#e6ecf5` / `#a3b2ca` | Texto principal y secundario. |
| `--primary` | `#0a2550` | `#2bc7bd` | Botón principal. |
| `--accent` | `#ddf4f2` | `#123a4a` | Hover y selección: velo turquesa. |
| `--input` | `#8793a8` | `#5c6f8e` | Borde de campos; cumple 3:1. |
| `--ring` | `#008c85` | `#5fe0d7` | Foco. |

Reglas:

- Estrategia contenida en la aplicación (neutros + marino, turquesa solo para lo activo) y comprometida en el sitio público (secciones enteras en marino).
- `gray-*`, `neutral-*` y `slate-*` están redefinidos como una sola escala teñida de marino que **se invierte sola en oscuro**. No se les añade `dark:`.
- Los colores de estado (`green`, `red`, `amber`, `blue`…) también se adaptan en oscuro: `bg-green-100 text-green-700` sirve en ambos temas.
- Para superficies se usa `bg-card`, no `bg-white`.
- El estado nunca se comunica solo con color: lleva texto o icono.

## Tipografía

- **Lexend** (`font-display`): títulos `h1`–`h3`, cifras destacadas y el nombre AVIS. Pesos 500–700, tracking de −0.015em a −0.03em.
- **Figtree** (`font-sans`): todo lo demás.
- Las tablas usan cifras tabulares.
- Título de pantalla en la aplicación: `text-2xl font-semibold`. En el sitio público: `text-3xl sm:text-4xl` para secciones y `clamp(2.125rem, 4.4vw, 3.375rem)` para la portada.

## Forma y elevación

- Radio base `0.625rem`; tarjetas y paneles `rounded-xl`; bloques del sitio público `rounded-2xl`; píldoras solo en etiquetas y conmutadores.
- Una sola elevación por elemento: borde **o** sombra. Los paneles llevan borde; lo que flota (avisos, menús) lleva sombra.

## Componentes

- `Button`: `default` (marino), `brand` (turquesa, para destacar sobre marino), `outline`, `ghost`, `destructive`. Tamaño `lg` (48 px) en formularios de acceso y en el sitio.
- `Field` (`components/field.tsx`): etiqueta, control, ayuda y error ya enlazados (`htmlFor`, `aria-describedby`, `aria-invalid`). Úsalo en todo formulario nuevo.
- `Badge`: variantes `success`, `warning`, `danger`, `info`, `muted` para estados.
- `FlashMessage`: el error no se cierra solo y se anuncia como `alert`; el éxito como `status`.
- `components/platform/ui.tsx`: `Panel`, `DataTable`, `EmptyState`, `Pagination` y las etiquetas de estado del panel de la plataforma.
- Marca: `AppLogoIcon` (monograma; las partes marinas heredan `currentColor`) y `Brand` (monograma + nombre).

## Accesibilidad (mínimo WCAG 2.1 AA)

- Foco visible en todo elemento interactivo; enlace «Saltar al contenido» en cada marco.
- Objetivos táctiles de al menos 36 px en la aplicación y 44 px en el sitio y en móvil.
- Botones de solo icono con `aria-label`; iconos decorativos con `aria-hidden`.
- Tablas anchas dentro de una región desplazable con nombre (`role="region"`, `tabIndex={0}`, `relative`).
- Se respeta `prefers-reduced-motion`. El único movimiento autoral es la entrada escalonada de la ilustración de la portada.

## Voz

Español, de tú, frases cortas. Los botones dicen lo que hacen («Enviar comprobante», no «Enviar»). Los errores dicen qué pasó y cómo seguir. No se inventan clientes, cifras ni certificaciones.
