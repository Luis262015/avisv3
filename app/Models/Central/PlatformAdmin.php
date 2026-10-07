<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\Central\Concerns\UsesCentralConnection;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Quien administra la plataforma: empresas, planes y cobros.
 *
 * No es un usuario de ninguna empresa y entra por su propio guard, así que
 * una sesión de empresa nunca abre el panel central ni al revés.
 */
final class PlatformAdmin extends Authenticatable
{
    use UsesCentralConnection;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
