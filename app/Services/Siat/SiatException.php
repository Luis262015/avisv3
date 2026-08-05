<?php

namespace App\Services\Siat;

/**
 * Error de comunicación o configuración frente a los servicios del SIN.
 *
 * Se distingue de una excepción genérica para que los controladores puedan
 * mostrarla al usuario sin exponer trazas internas.
 */
class SiatException extends \RuntimeException {}
