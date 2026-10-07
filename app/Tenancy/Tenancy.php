<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * La empresa en curso y todo lo que cambia con ella.
 *
 * Cada empresa tiene su propia base de datos. Inicializar una empresa apunta
 * ahí la conexión por defecto, de modo que ventas, inventario y SIAT siguen
 * funcionando sin saber que existe el arrendamiento. Con la base cambian
 * también la caché y los discos: las claves y las rutas de archivos llevan
 * ids que se repiten de una empresa a otra.
 */
final class Tenancy
{
    public const CONNECTION = 'tenant';

    private const DISKS = ['local', 'public', 'private'];

    private ?Tenant $tenant = null;

    private readonly string $centralConnection;

    /** @var array<string, mixed> */
    private array $original = [];

    public function __construct(
        private readonly Application $app,
        private readonly TenantDatabaseManager $databases,
    ) {
        $this->centralConnection = (string) config('database.default');
    }

    public function enabled(): bool
    {
        return (bool) config('tenancy.enabled');
    }

    public function centralConnection(): string
    {
        return $this->centralConnection;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function initialized(): bool
    {
        return $this->tenant !== null;
    }

    public function initialize(Tenant $tenant): void
    {
        if ($this->tenant?->is($tenant)) {
            return;
        }

        $this->end();
        $this->tenant = $tenant;

        // La empresa heredada vive en la base central y conserva su caché y
        // sus archivos donde estaban: no hay nada que cambiar.
        if ($tenant->is_legacy) {
            return;
        }

        $this->original = [
            'cache.prefix' => config('cache.prefix'),
        ];

        config(['database.connections.'.self::CONNECTION => $this->databases->connectionConfig($tenant)]);
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);

        config(['cache.prefix' => $this->original['cache.prefix'].'t'.$tenant->id.'_']);

        foreach (self::DISKS as $disk) {
            $root = config("filesystems.disks.{$disk}.root");
            if ($root === null) {
                continue;
            }

            $this->original["filesystems.disks.{$disk}.root"] = $root;
            config(["filesystems.disks.{$disk}.root" => $root.DIRECTORY_SEPARATOR.'tenants'.DIRECTORY_SEPARATOR.$tenant->id]);
        }

        $this->original['filesystems.disks.public.url'] = config('filesystems.disks.public.url');
        config(['filesystems.disks.public.url' => '/storage/tenants/'.$tenant->id]);

        $this->refreshResolvedServices();
    }

    public function end(): void
    {
        if ($this->tenant === null) {
            return;
        }

        $this->tenant = null;

        if ($this->original === []) {
            return;
        }

        config($this->original);
        $this->original = [];

        DB::setDefaultConnection($this->centralConnection);
        DB::purge(self::CONNECTION);

        $this->refreshResolvedServices();
    }

    /**
     * Ejecuta algo dentro de una empresa y vuelve al contexto anterior.
     *
     * @template T
     *
     * @param  Closure(Tenant): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->initialize($tenant);

        try {
            return $callback($tenant);
        } finally {
            $previous ? $this->initialize($previous) : $this->end();
        }
    }

    /**
     * Lo que ya se resolvió con la configuración anterior hay que soltarlo:
     * si no, la caché seguiría usando el prefijo de otra empresa y los discos
     * la carpeta de otra.
     */
    private function refreshResolvedServices(): void
    {
        $this->app['cache']->forgetDriver(array_keys((array) config('cache.stores')));
        $this->app->forgetInstance('cache.store');

        Storage::forgetDisk(self::DISKS);

        if ($this->app->resolved(PermissionRegistrar::class)) {
            $registrar = $this->app->make(PermissionRegistrar::class);
            $registrar->initializeCache();
            $registrar->clearPermissionsCollection();
        }
    }
}
