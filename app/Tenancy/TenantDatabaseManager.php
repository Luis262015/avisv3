<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Crea, migra y elimina la base de datos de cada empresa.
 */
final class TenantDatabaseManager
{
    public function nameFor(string $slug): string
    {
        return config('tenancy.database_prefix').str_replace('-', '_', $slug);
    }

    /**
     * Configuración de conexión de la empresa: la de la base central con el
     * nombre de base cambiado. Mismo servidor, mismas credenciales.
     *
     * @return array<string, mixed>
     */
    public function connectionConfig(Tenant $tenant): array
    {
        $central = $this->centralConfig();

        return array_merge($central, [
            'database' => $central['driver'] === 'sqlite'
                ? $this->sqliteFile($tenant->database)
                : $tenant->database,
        ]);
    }

    public function create(Tenant $tenant): void
    {
        $name = $this->safeName($tenant->database);

        if ($this->driver() === 'sqlite') {
            File::ensureDirectoryExists(dirname($this->sqliteFile($name)));
            File::put($this->sqliteFile($name), '');

            return;
        }

        $this->central()->statement(match ($this->driver()) {
            'pgsql' => "CREATE DATABASE \"{$name}\"",
            default => "CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        });
    }

    public function drop(Tenant $tenant): void
    {
        // La base heredada es la central: borrarla se llevaría la plataforma.
        if ($tenant->is_legacy) {
            return;
        }

        $name = $this->safeName($tenant->database);
        DB::purge(Tenancy::CONNECTION);

        if ($this->driver() === 'sqlite') {
            File::delete($this->sqliteFile($name));

            return;
        }

        $this->central()->statement(match ($this->driver()) {
            'pgsql' => "DROP DATABASE IF EXISTS \"{$name}\"",
            default => "DROP DATABASE IF EXISTS `{$name}`",
        });
    }

    public function exists(string $database): bool
    {
        $name = $this->safeName($database);

        return match ($this->driver()) {
            'sqlite' => File::exists($this->sqliteFile($name)),
            'pgsql' => $this->central()->table('pg_database')->where('datname', $name)->exists(),
            default => $this->central()->table('information_schema.schemata')->where('schema_name', $name)->exists(),
        };
    }

    /**
     * El nombre se interpola en DDL, que no admite parámetros: solo se acepta
     * lo que no puede escapar de las comillas.
     */
    private function safeName(string $database): string
    {
        if (! preg_match('/^[a-zA-Z0-9_]{1,64}$/', $database)) {
            throw new InvalidArgumentException("Nombre de base de datos no válido: {$database}");
        }

        return $database;
    }

    private function sqliteFile(string $name): string
    {
        return rtrim((string) config('tenancy.sqlite_path'), '/\\').DIRECTORY_SEPARATOR.$name.'.sqlite';
    }

    private function driver(): string
    {
        return (string) $this->centralConfig()['driver'];
    }

    /** @return array<string, mixed> */
    private function centralConfig(): array
    {
        return (array) config('database.connections.'.app(Tenancy::class)->centralConnection());
    }

    private function central(): Connection
    {
        return DB::connection(app(Tenancy::class)->centralConnection());
    }
}
