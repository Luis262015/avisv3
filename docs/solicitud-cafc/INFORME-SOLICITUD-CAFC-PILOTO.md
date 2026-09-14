# Solicitud de CAFC para el ambiente Piloto — respaldo técnico

**Razón social:** LUIS REMBERTO ISLA ALANOCA
**NIT emisor:** 6923448010
**Código de sistema:** 2280EE3A3729A542C92F6
**Ambiente:** Piloto (`codigoAmbiente` = 2)
**Modalidad:** Computarizada (`codigoModalidad` = 2) — emisión fuera de línea (`codigoEmision` = 2)
**Sucursal:** 0 · **Puntos de venta:** 0 y 1
**Actividad económica:** 4741100 — Venta al por menor de computadoras, equipo periférico y programas informáticos
**CUIS vigente:** 74E5E97B
**Servicio:** `ServicioFacturacionCompraVenta` (`https://pilotosiatservicios.impuestos.gob.bo/v2/`)
**Fecha del informe:** 9 de septiembre de 2026

---

## 1. Qué se solicita

La **emisión de un Código de Autorización de Factura de Contingencia (CAFC) válido
en el ambiente Piloto** para el NIT 6923448010, sucursal 0, puntos de venta 0 y 1,
con vigencia que cubra los días de prueba.

Es el único requisito que falta para cerrar la **Etapa VI (Recepción Paquete de
Facturas)** de la Fase I de homologación: los casos de prueba de esa etapa que
corresponden a los motivos de evento **5, 6 y 7** exigen CAFC, y el servicio del
Piloto rechaza el único CAFC del que disponemos.

Estado de la homologación Fase I al 9 de septiembre de 2026:

| Etapa | Volumen exigido | Estado |
|---|---|---|
| I — Solicitud de CUIS | 2 | completa |
| II — Sincronización de catálogos | 1800 | completa |
| III — Solicitud de CUFD | 100 por caso | completa |
| IV — Emisión individual | 500 | completa |
| V — Eventos significativos | 14 | completa |
| **VI — Recepción de paquetes** | **160** | **100 / 160 — bloqueada por el CAFC** |
| VII — Anulación y reversión | 250 | completa |
| VIII — Firma digital | — | no aplica a la modalidad Computarizada |
| IX — Recepción masiva | 80 | completa |

## 2. El problema

Al enviar un paquete de facturas emitidas fuera de línea durante un corte declarado
con **motivo de evento 5, 6 o 7**, la operación `recepcionPaqueteFactura` responde:

```xml
<RespuestaServicioFacturacion>
  <codigoDescripcion>RECHAZADA</codigoDescripcion>
  <codigoEstado>902</codigoEstado>
  <mensajesList>
    <codigo>0</codigo>
    <descripcion>Cafc no encontrado</descripcion>
  </mensajesList>
  <transaccion>false</transaccion>
</RespuestaServicioFacturacion>
```

El CAFC enviado es **`101160D18E851F`**, obtenido del Portal SIAT, y viaja tanto en
el elemento `cafc` de `solicitudRecepcionPaquete` como en la cabecera de cada
factura del paquete (anexos A y B).

El corte que ampara esas facturas **sí fue aceptado** por el propio Piloto:
`registroEventoSignificativo` devolvió el código de recepción **9908746** para el
motivo 5 (anexo D).

## 3. Evidencia

Las cuatro pruebas siguientes se ejecutaron el **9 de septiembre de 2026 entre las
12:08 y las 12:10 (hora de Bolivia)**, todas con la misma factura, el mismo paquete
`.tar.gz`, el mismo CUFD vigente y variando un único dato. Las respuestas completas
están en el **anexo C**.

| Caso | `codigoEvento` (motivo) | `cafc` enviado | Respuesta del Piloto |
|---|---|---|---|
| A | 9908746 (motivo 5) | `101160D18E851F` | **902 RECHAZADA** · `0 Cafc no encontrado` |
| B | 9908746 (motivo 5) | `AAAAAAAAAAAAAA` (inventado) | **902 RECHAZADA** · `0 Cafc no encontrado` |
| C | 9908746 (motivo 5) | *(omitido)* | **902 RECHAZADA** · `0 Cafc no encontrado` |
| D | 9908185 (motivo 1) | `101160D18E851F` | **901 PENDIENTE**, recibido; al validarlo: **904 OBSERVADA** · `1045` |

### 3.1 El servicio recibe y procesa correctamente el CAFC enviado

En el caso D —un corte de motivo 1, que no admite CAFC— la validación del paquete
devuelve el valor exacto que se envió:

```xml
<mensajesList>
  <codigo>1045</codigo>
  <descripcion>VALOR DE CAFC NO VALIDO PARA LA FACTURA Cafc esperado null enviado 101160D18E851F para el codigo evento 1</descripcion>
</mensajesList>
```

Es decir: **el campo llega, se lee y se contrasta**. La petición está bien formada y
el valor no se pierde ni se altera en el camino.

### 3.2 Para los motivos 5, 6 y 7 la respuesta es la misma con el CAFC real, con uno inventado y sin ninguno

Los casos A, B y C devuelven **exactamente el mismo mensaje**. El servicio no
distingue entre `101160D18E851F` y un valor inventado, lo que indica que **ese CAFC
no está registrado en la base de datos del ambiente Piloto**.

### 3.3 El resto del circuito de contingencia funciona en este mismo ambiente

Con un corte de motivo 1 (que no requiere CAFC) el circuito completo se cierra sin
observaciones. Paquete de 250 facturas del evento 9908185:

| Operación | Respuesta |
|---|---|
| `registroEventoSignificativo` | código de recepción `9908185` |
| `recepcionPaqueteFactura` | `901` · código de recepción `167c2d38-abd0-11f1-aa49-b16e2d246119` |
| `validacionRecepcionPaqueteFactura` | **`908` VALIDADA**, sin observaciones |

Por lo tanto el fallo **no está en la construcción del paquete, ni en el XML, ni en
el hash, ni en la cabecera de identificación**: es únicamente el CAFC.

### 3.4 Reiteración del error

El rechazo se ha reproducido de forma idéntica los días **8 y 9 de septiembre de
2026**, en distintos cortes, con distintos CUFD y con lotes de 500 y de 250
facturas (anexos D y E). Hay en este momento **3.000 facturas emitidas fuera de
línea que no pueden enviarse** por esta causa.

## 4. Conclusión

El aplicativo construye y transmite el CAFC conforme al contrato del WSDL —lo
confirma el propio servicio al devolverlo en el mensaje 1045—, pero el código
**`101160D18E851F` no es reconocido por el ambiente Piloto**.

Entendemos que puede deberse a que el CAFC fue emitido en el Portal de producción y
que el ambiente Piloto mantiene su propio registro, del mismo modo que ocurre con el
Token Delegado y con el CUIS, que se emiten por separado en cada ambiente. En la
documentación publicada no hemos encontrado la vía para solicitar un CAFC específico
del ambiente Piloto, y ninguno de los servicios web (`FacturacionCodigos`,
`FacturacionOperaciones`, `ServicioFacturacionCompraVenta`) expone una operación que
lo emita.

## 5. Petición concreta

1. La **emisión de un CAFC válido en el ambiente Piloto** para el NIT 6923448010,
   sucursal 0, puntos de venta 0 y 1, con vigencia que incluya los días de prueba.
2. En su defecto, la **indicación del procedimiento** para solicitarlo en el
   ambiente Piloto, si es distinto del Portal SIAT de producción.
3. Si el CAFC `101160D18E851F` debiera ser válido también en Piloto, la revisión de
   su registro en ese ambiente.

## 6. Anexos adjuntos

| Anexo | Archivo | Contenido |
|---|---|---|
| A | `anexo-a-peticion-recepcionPaqueteFactura.xml` | Petición SOAP completa enviada al Piloto, con el CAFC en `solicitudRecepcionPaquete` |
| B | `anexo-b-factura-fuera-de-linea.xml` | XML de una factura del paquete, con `<cafc>101160D18E851F</cafc>` en la cabecera |
| C | `anexo-c-respuestas-del-piloto.txt` | Respuestas SOAP íntegras de los cuatro casos de la sección 3 |
| D | `anexo-d-base-de-datos.csv` | Extracto de la base de datos del aplicativo: cortes declarados, paquetes enviados y facturas afectadas |
| E | `anexo-e-registro-aplicacion.txt` | Extracto del registro de errores de la aplicación con los rechazos del 8 y 9 de septiembre |

---

**Contacto:** islaluis25@gmail.com
