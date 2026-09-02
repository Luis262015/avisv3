# Reporte de incidencia — las operaciones de anulación devuelven un error interno en el ambiente Piloto

**NIT emisor:** 6923448010
**Código de sistema:** 2280EE3A3729A542C92F6
**Ambiente:** Piloto (codigoAmbiente = 2)
**Modalidad:** Computarizada en línea (codigoModalidad = 2)
**Sucursal / Punto de venta:** 0 / 0
**Servicios:** `ServicioFacturacionDocumentoAjuste` · `ServicioFacturacionCompraVenta`
**Fecha de las pruebas:** 2 de septiembre de 2026

---

## Resumen

**Las dos operaciones de anulación del ambiente Piloto devuelven un error interno
al procesar una solicitud válida:**

| Servicio | Operación | Respuesta |
|---|---|---|
| `ServicioFacturacionDocumentoAjuste` | `anulacionDocumentoAjuste` | `codigoEstado -1` · `Error inesperado: null` |
| `ServicioFacturacionCompraVenta` | `anulacionFactura` | `codigoEstado 906` · `ERROR EN LA EJECUCION DEL SERVICIO (RCV)` |

En ambos casos el resto de operaciones de esos mismos servicios funcionan con
normalidad sobre los mismos documentos: se reciben correctamente y
`verificacionEstadoFactura` / `verificacionEstadoDocumentoAjuste` los reportan como
**690 VALIDA**.

`anulacionFactura` funcionaba correctamente en este mismo ambiente el 6 de agosto
de 2026, con las mismas credenciales y el mismo código, devolviendo
**905 ANULACION CONFIRMADA**.

Esto impide completar la **Etapa VII (Anulación y Reversión)** del proceso de
homologación Fase I, que exige 250 anulaciones.

## Documentos afectados

| Sector | CUF | Código de recepción | Estado según el SIN |
|---|---|---|---|
| 24 | `1D9B8B69B433E572D02B4930B9F7870CF1CCC6F86A4345EB2A2EE2BF74` | `fc846ad8-a6f9-11f1-956a-3bde5558fafd` | 690 VALIDA |
| 47 | `1D9B8B69B433E572D03C548430D407DFFD7A94506A2345EB2A2EE2BF74` | `13ae22b2-a6fa-11f1-956a-3bde5558fafd` | 690 VALIDA |

## Evidencia

### A) Petición de anulación conforme a la documentación → error interno

Los campos enviados corresponden exactamente a los descritos en
*Anulación de Documento de Ajuste*
(`/facturacion-en-linea/implementacion-servicios-facturacion/nota-credito-debito-comp/anulacion-nota-credito-debito`)
y al contrato del WSDL (`solicitudAnulacion` extiende `solicitudRecepcion` y añade
`codigoMotivo` y `cuf`).

```xml
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
                   xmlns:ns1="https://siat.impuestos.gob.bo/">
  <SOAP-ENV:Body>
    <ns1:anulacionDocumentoAjuste>
      <SolicitudServicioAnulacionDocumentoAjuste>
        <codigoAmbiente>2</codigoAmbiente>
        <codigoDocumentoSector>24</codigoDocumentoSector>
        <codigoEmision>1</codigoEmision>
        <codigoModalidad>2</codigoModalidad>
        <codigoPuntoVenta>0</codigoPuntoVenta>
        <codigoSistema>2280EE3A3729A542C92F6</codigoSistema>
        <codigoSucursal>0</codigoSucursal>
        <cufd>FBQUFCd2nDk0dBI5QTU0MkM5MkY2Q0FpbzdOREphVUMjI4MEVFM0EzNz</cufd>
        <cuis>F4B9FE8F</cuis>
        <nit>6923448010</nit>
        <tipoFacturaDocumento>3</tipoFacturaDocumento>
        <codigoMotivo>2</codigoMotivo>
        <cuf>1D9B8B69B433E572D02B4930B9F7870CF1CCC6F86A4345EB2A2EE2BF74</cuf>
      </SolicitudServicioAnulacionDocumentoAjuste>
    </ns1:anulacionDocumentoAjuste>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```

Respuesta:

```xml
<RespuestaServicioFacturacion>
  <codigoDescripcion>Error inesperado: null</codigoDescripcion>
  <codigoEstado>-1</codigoEstado>
  <transaccion>false</transaccion>
</RespuestaServicioFacturacion>
```

No se devuelve `mensajesList`, por lo que no hay ningún código de error de negocio
que permita corregir la petición desde nuestro lado.

### B) El propio servicio confirma que el valor enviado es el correcto

Enviando la **misma petición** con `tipoFacturaDocumento = 1`, el servicio responde
correctamente y **indica que el valor esperado es precisamente el 3** que se envía
en el caso A:

```xml
<RespuestaServicioFacturacion>
  <codigoDescripcion>ANULACION RECHAZADA</codigoDescripcion>
  <codigoEstado>906</codigoEstado>
  <mensajesList>
    <codigo>915</codigo>
    <descripcion>EL PARAMETRO TIPO FACTURA DOCUMENTO ES INVALIDO Tipo de factura esperado 3, enviado 1.</descripcion>
  </mensajesList>
  <transaccion>false</transaccion>
</RespuestaServicioFacturacion>
```

Es decir: cuando la petición es **incorrecta**, el servicio responde con un código
de negocio claro (906 + 915); cuando la petición es **correcta**, se produce el
error interno. El fallo aparece únicamente al procesar una solicitud válida.

### C) La cabecera es aceptada por el resto del servicio

`verificacionEstadoDocumentoAjuste`, con exactamente la misma cabecera y el mismo
CUF, responde con normalidad:

```xml
<RespuestaServicioFacturacion>
  <codigoDescripcion>VALIDA</codigoDescripcion>
  <codigoEstado>690</codigoEstado>
  <codigoRecepcion>fc846ad8-a6f9-11f1-956a-3bde5558fafd</codigoRecepcion>
  <transaccion>true</transaccion>
</RespuestaServicioFacturacion>
```

`reversionAnulacionDocumentoAjuste` también responde correctamente: sobre una nota
no anulada devuelve **909 REVERSION DE ANULACION RECHAZADA**, que es el
comportamiento esperado.

### D) El mismo patrón en `anulacionFactura`

Sobre una factura del documento sector 1 en estado **690 VALIDA**, las peticiones
**incorrectas** obtienen errores de negocio precisos y solo la **correcta** falla:

| Petición | Respuesta |
|---|---|
| correcta | `906` · `ERROR EN LA EJECUCION DEL SERVICIO (RCV)` |
| `cuf` inexistente | `906` · `LA FACTURA O NOTA, NO EXISTE EN LA BASE DE DATOS DEL SIN` |
| `tipoFacturaDocumento` = 3 | `906` · `EL PARAMETRO TIPO FACTURA DOCUMENTO ES INVALIDO Tipo de factura esperado 1, enviado 3.` |
| `codigoMotivo` = 99 | `906` · `EL PARAMETRO MOTIVO DE ANULACION ES INVALIDO Codigo motivo anulacion enviado 99.` |
| `verificacionEstadoFactura` (misma cabecera) | `690` · `VALIDA` |

Se probaron los cuatro motivos de anulación, facturas emitidas el mismo día y
facturas de agosto, y tanto el CUFD vigente como el CUFD con el que se emitió cada
factura. El resultado es el mismo en todos los casos.

## Pruebas adicionales realizadas

- **Los cuatro motivos de anulación** de la paramétrica
  `sincronizarParametricaMotivoAnulacion` (1 FACTURA MAL EMITIDA, 2 NOTA DE
  CREDITO-DEBITO MAL EMITIDA, 3 DATOS DE EMISION INCORRECTOS, 4 FACTURA O NOTA DE
  CREDITO-DEBITO DEVUELTA) producen el mismo error interno.
- Con un **CUF inexistente**, el servicio responde 906 ANULACION RECHAZADA, sin
  error interno.
- El comportamiento es **idéntico en los documentos sector 24 y 47**.
- Durante las pruebas el servicio `ServicioFacturacionDocumentoAjuste` pasó
  temporalmente a **HTTP 503**, mientras `ServicioFacturacionCompraVenta` seguía
  disponible. Al restablecerse el servicio, **el error se reprodujo de forma
  idéntica**, por lo que no se trata de una indisponibilidad puntual.

## Solicitud

Se solicita la revisión de las operaciones `anulacionFactura` y
`anulacionDocumentoAjuste` en el ambiente Piloto, dado que su indisponibilidad
impide avanzar en la Etapa VII del proceso de homologación.

En caso de que la operación requiera alguna condición adicional no recogida en la
documentación publicada, agradeceríamos que se nos indique para ajustar la
implementación.
