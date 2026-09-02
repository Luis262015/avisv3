<?php

/*
|--------------------------------------------------------------------------
| SIAT — Impuestos Nacionales, Bolivia
|--------------------------------------------------------------------------
|
| Endpoints de los servicios web del SIN. Los de producción se comunican al
| finalizar el proceso de autorización, por eso son configurables por entorno.
|
| @see https://siatinfo.impuestos.gob.bo/index.php/facturacion-en-linea/factura-electronica
|
*/

return [

    /*
    | Código de ambiente que espera el SIN en cada petición.
    | Producción: 1 · Pruebas y Piloto: 2
    */
    'codigos_ambiente' => [
        'produccion' => 1,
        'piloto'     => 2,
    ],

    /*
    | Host base de los servicios SOAP por ambiente.
    */
    'hosts' => [
        'piloto'     => env('SIAT_HOST_PILOTO', 'https://pilotosiatservicios.impuestos.gob.bo'),
        'produccion' => env('SIAT_HOST_PRODUCCION', 'https://siatrest.impuestos.gob.bo'),
    ],

    /*
    | Servicios publicados. La ruta completa se arma como {host}/{version}/{servicio}?wsdl
    */
    'version' => env('SIAT_API_VERSION', 'v2'),

    'servicios' => [
        'codigos'        => 'FacturacionCodigos',
        'sincronizacion' => 'FacturacionSincronizacion',
        'operaciones'    => 'FacturacionOperaciones',
        'compra_venta'   => 'ServicioFacturacionCompraVenta',
        // Registro de Compras: es el contribuyente el que reporta lo que compró,
        // no lo que factura. Servicio y formato distintos de los de venta.
        'compras'        => 'ServicioRecepcionCompras',
        // Notas de crédito-débito (documentos sector 24 y 47). Servicio aparte del
        // de compra-venta, y sin envío por paquete ni masivo.
        'ajuste'         => 'ServicioFacturacionDocumentoAjuste',
    ],

    /*
    | Base del QR impreso en la representación gráfica.
    | La ruta productiva la comunica el SIN al autorizar el sistema.
    */
    'qr_base' => [
        'piloto'     => env('SIAT_QR_PILOTO', 'https://pilotosiat.impuestos.gob.bo/consulta/QR'),
        'produccion' => env('SIAT_QR_PRODUCCION', 'https://siat.impuestos.gob.bo/consulta/QR'),
    ],

    /*
    | Tiempo máximo de espera de una llamada SOAP, en segundos.
    */
    'timeout' => env('SIAT_TIMEOUT', 30),

    /*
    | Horas que se conservan en caché las paramétricas del SIN (leyendas,
    | actividades, unidades de medida...). Son estables dentro de una jornada.
    */
    'cache_catalogos_horas' => env('SIAT_CACHE_CATALOGOS_HORAS', 12),

    /*
    | Valores de la Factura Compra Venta.
    |
    | El tipo de emisión es independiente de la modalidad: una factura
    | Computarizada (modalidad 2) se emite igualmente en línea (emisión 1).
    */
    /*
    | Zona horaria del SIN.
    |
    | La aplicación trabaja en UTC, pero el SIN espera hora de Bolivia y solo
    | tolera 5 minutos de desfase en `fechaEnvio`. Además la fecha de emisión
    | entra en el cálculo del CUF, así que enviarla en UTC lo invalida.
    */
    'timezone' => env('SIAT_TIMEZONE', 'America/La_Paz'),

    'factura' => [
        'documento_sector' => 1,   // Factura Compra Venta
        'emision_online'   => 1,
        'codigo_moneda'    => 1,   // Boliviano
        'tipo_cambio'      => 1.00,

        /*
        | Códigos del SIN para productos sin homologar. `unidad_medida` 57 es
        | "UNIDAD (BIENES)"; ambos se pueden sobrescribir por producto.
        */
        'unidad_medida_default'      => env('SIAT_UNIDAD_MEDIDA_DEFAULT', 57),
        'codigo_producto_sin_default' => env('SIAT_PRODUCTO_SIN_DEFAULT'),
    ],

    /*
    | Nota de Crédito-Débito: los documentos sector que ajustan una factura ya
    | emitida. La actividad 4741100 tiene habilitados los dos.
    |
    | El 24 y el 47 comparten servicio, algoritmo de CUF y casi todo el XML: el
    | de descuento solo añade `descuentoAdicional` en cabecera y `nroItem` en
    | cada línea del detalle.
    */
    'nota' => [
        'documentos_sector' => [
            24 => 'NOTA DE CRÉDITO-DÉBITO',
            47 => 'NOTA CREDITO DEBITO DESCUENTO',
        ],

        /*
        | Los dos sectores exigen tipo de factura 3 (documento de ajuste).
        */
        'tipo_factura' => 3,

        /*
        | `montoEfectivoCreditoDebito` es el 13 % del monto devuelto —el crédito
        | fiscal que se revierte—, no el efectivo entregado al cliente.
        */
        'alicuota_iva' => env('SIAT_ALICUOTA_IVA', 0.13),
    ],

];
