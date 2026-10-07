# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Dueños y administradores de comercios bolivianos** (tiendas, distribuidoras, negocios con una o varias sucursales) que necesitan vender, controlar existencias y emitir factura electrónica válida ante el SIN. Deciden si rentan el sistema y lo configuran.
- **Cajeros y vendedores**, de pie en el mostrador, con cola de clientes: abren caja, venden, cobran y facturan decenas de veces al día.
- **Operadores** que cargan compras, productos e inventario.
- **El operador de la plataforma** (quien renta AVIS): administra empresas, planes, suscripciones y cobros desde un panel central.

## Product Purpose

AVIS es un sistema de inventarios y facturación que se renta por suscripción. Cada empresa obtiene su propio espacio aislado, con punto de venta, inventario por tienda, compras, finanzas, recursos humanos y facturación electrónica SIAT. El éxito es que un comercio se registre solo, venda y facture el mismo día, y que la plataforma cobre y administre esas rentas sin intervención manual innecesaria.

## Positioning

Facturación electrónica del SIN (emisión en línea, contingencia, notas de crédito/débito, registro de compras, homologación) integrada en el mismo flujo de venta e inventario, no como un módulo aparte. Cada empresa tiene su propia base de datos: sus datos fiscales nunca se mezclan con los de otro NIT.

## Operating Context

- Mostrador con lector de códigos de barras e impresora de recibos; turnos de caja con apertura y cierre.
- Varias tiendas por empresa, con existencias y transferencias entre ellas.
- Normativa del SIN boliviano: CUIS, CUFD, CUF, puntos de venta, eventos significativos y contingencia cuando no hay conexión.
- Moneda: bolivianos (Bs). Idioma: español.
- Pago de la renta por QR o transferencia con comprobante, aprobado por el operador; pasarela en línea prevista pero aún sin proveedor elegido.

## Capabilities and Constraints

- Módulos existentes: punto de venta y turnos de caja; clientes, cotizaciones, pedidos y envíos, promociones y combos, devoluciones, garantías; órdenes de compra, compras, proveedores y su evaluación; gastos, ingresos, retiros, cuentas por cobrar y por pagar; productos, categorías, marcas, etiquetas, existencias por tienda, transferencias; empleados, áreas, asistencia, ausencias, nómina, capacitación; facturación SIAT; reportes de ventas, compras, finanzas y RR.HH.
- Roles por empresa: admin, operador, vendedor.
- Arrendamiento: una base de datos por empresa, identificada por subdominio; una base central guarda planes, empresas, suscripciones y pagos.
- Alta autoservicio desde el sitio público; no existe registro de usuarios sueltos.
- Los planes, sus precios y límites se editan desde el panel central. **Los precios cargados inicialmente son provisionales**, no una oferta comercial confirmada.
- Sin decidir: proveedor de pasarela de pago; dominio de producción.

## Brand Commitments

- Nombre: **AVIS**, con el descriptor «Inventarios y facturación».
- Logo: `docs/logo1.jpeg` — monograma «A» en azul marino con una pieza turquesa, y logotipo en mayúsculas geométricas.
- Colores obligatorios: azul marino y turquesa del logo.

## Evidence on Hand

- El propio sistema funcionando y sus pantallas.
- Documentación de homologación ante el SIN en `docs/`.
- No hay testimonios, clientes nombrables, cifras de uso ni certificaciones publicables: no deben inventarse.

## Product Principles

1. El mostrador manda: lo que se usa cien veces al día va primero y a un toque.
2. La factura no puede fallar en silencio: todo estado fiscal se dice con palabras, no solo con color.
3. Cada empresa es dueña de sus datos y están separados de los demás.
4. Rentar debe ser tan simple como registrarse: plan claro, límites visibles, sin letra chica.

## Accessibility & Inclusion

WCAG 2.1 AA como mínimo: contraste, foco visible, navegación completa por teclado, objetivos táctiles amplios (uso de pie y con el dedo), estados que no dependan solo del color y respeto de `prefers-reduced-motion`.
