# Casos de prueba — Homologación SIAT Fase I

Los nueve Excel oficiales del SIN, bajados el 2026-09-10 de
<https://siatinfo.impuestos.gob.bo/index.php/724-fase-i> (carpeta
`/images/archivos_tecnicos/archivos_apoyo/`). Se guardan aquí porque la matriz de
`HomologacionMatriz` sale de cruzarlos con lo que el servicio asocia al NIT, y sin
ellos no hay forma de contrastar por qué un caso existe.

El de sincronización se renombró a `CasosDePruebaSincronizacionCatalogos.xlsx`
—el original lleva acentos— para no depender de la codificación del nombre.

| Etapa | Archivo | Filas | Casos que enumera |
|---|---|---:|---|
| I CUIS | `CasosDePruebaCUIS.xlsx` | 2 | punto de venta 1 y 0 |
| II Sincronización | `CasosDePruebaSincronizacionCatalogos.xlsx` | 2 | punto de venta 1 y 0 |
| III CUFD | `CasosDePruebaCUFD.xlsx` | 2 | punto de venta 1 y 0 |
| IV Emisión individual | `CasosDePruebaEmisionIndividual.xlsx` | 56 | 2 por sector × 28 sectores |
| V Eventos significativos | `CasosDePruebaEventosSignificativos.xlsx` | 14 | 7 motivos × 2 puntos |
| VI Paquetes | `CasosDePruebaEmisionPorPaquetes.xlsx` | 432 | 16 por sector × 27 sectores |
| VII Anulación/Reversión | `CasosDePruebaAnulacionReversion.xlsx` | 56 | 2 por sector × 28 sectores |
| VIII Firma digital | `CasosDePruebaFirmaDigital.xlsx` | 56 | N/A en modalidad computarizada |
| IX Masiva | `CasosDePruebaEmisionMasiva.xlsx` | 216 | 8 por sector × 27 sectores |

## Dos cosas que estos archivos NO dicen, y cuestan caro

**1. No traen cuántas pruebas pide cada caso.** Solo enumeran los casos; el número
sale de la columna «Pruebas Esperadas» del panel de Seguimiento del Portal. La
página de la Fase I da una cifra por etapa, **y puede engañar**: en la VII dice
«debe realizar 250 anulaciones» y el panel pide **125 en cada uno de sus 12
casos = 1500**. Repartir esas 250 entre los casos daba 21, así que casos con 42
anulaciones hechas figuraban «completados» mientras el panel los daba al 33 %.

Por eso **la fuente buena es el panel, caso por caso**, no la frase de la página.
Queda una etapa sin contrastar con esa misma redacción: **la IV**, que tiene los
mismos 2 casos por sector que la VII —12 casos para este NIT— y hoy va con 42 por
caso. Si el panel pidiera 125, está igual de corta que estaba la VII.

**2. Los sectores están desactualizados.** Listan del 1 al 24 y del 28 al 31; no
aparecen el **34**, el **35** ni el **47**, que sí están asociados a este NIT.
Para el alcance manda `sincronizarListaActividadesDocumentoSector`, no el Excel:
el panel de la etapa VII tiene fila para el 47 aunque el archivo no lo liste.

## Cómo leerlos sin PhpSpreadsheet

Son ZIP. Basta `ZipArchive` + `xl/sharedStrings.xml` + `xl/worksheets/sheet1.xml`,
resolviendo las celdas con `t="s"` contra la tabla de cadenas compartidas.
