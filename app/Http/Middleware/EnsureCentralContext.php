<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sitio público y panel de la plataforma: no se sirven en el subdominio de
 * una empresa.
 */
final class EnsureCentralContext
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->tenancy->initialized(), 404);

        return $next($request);
    }
}
