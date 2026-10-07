<?php

declare(strict_types=1);

namespace App\Models\Central\Concerns;

use App\Tenancy\Tenancy;

/**
 * Fija el modelo a la base central.
 *
 * Dentro de una empresa la conexión por defecto es la suya; sin esto, pedir
 * su plan o su suscripción iría a buscar esas tablas donde no existen.
 */
trait UsesCentralConnection
{
    public function getConnectionName(): ?string
    {
        return app(Tenancy::class)->centralConnection();
    }
}
