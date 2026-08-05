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
    'version' => env('SIAT_API_VERSION', 'v1'),

    'servicios' => [
        'codigos'        => 'FacturacionCodigos',
        'sincronizacion' => 'FacturacionSincronizacion',
        'operaciones'    => 'FacturacionOperaciones',
        'compra_venta'   => 'ServicioFacturacionCompraVenta',
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

];
