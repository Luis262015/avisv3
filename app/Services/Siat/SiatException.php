<?php

namespace App\Services\Siat;

/**
 * Error de comunicación o configuración frente a los servicios del SIN.
 *
 * Se distingue de una excepción genérica para que los controladores puedan
 * mostrarla al usuario sin exponer trazas internas.
 */
class SiatException extends \RuntimeException
{
    /**
     * Si el envío se perdió por transporte, y no porque el SIN lo rechazara.
     *
     * La diferencia decide qué se escribe en el documento. Un rechazo es una
     * respuesta: el SIN lo miró y dijo que no, así que el documento no existe
     * allí. Un fallo de transporte —un `timeout`, que PHP reporta como «Error
     * Fetching http headers»— no dice nada: la petición pudo llegar y
     * procesarse, y solo se perdió la respuesta.
     *
     * Darlo por rechazado es la peor de las dos incoherencias posibles: deja el
     * documento muerto en local y vivo ante Impuestos. Pasó de verdad con la
     * nota #28 de la homologación, que quedó «rechazada» aquí y **690 VALIDA**
     * en el SIN.
     *
     * `SiatSoapClient` envuelve los `SoapFault` conservándolos como `previous`,
     * que es lo que permite distinguirlos.
     */
    public function esFalloDeComunicacion(): bool
    {
        return $this->getPrevious() instanceof \SoapFault;
    }
}
