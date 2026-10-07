<?php

return [

    /*
    | Con el arrendamiento apagado el sistema se comporta como una sola
    | empresa: todo vive en la base por defecto y no hay subdominios. Así
    | corren también las pruebas del sistema operativo.
    */
    'enabled' => (bool) env('TENANCY_ENABLED', false),

    /*
    | Dominios donde vive el sitio público y el panel de la plataforma.
    | Cualquier otro host se interpreta como el de una empresa.
    */
    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', 'localhost,127.0.0.1')),
    ))),

    // Las empresas entran por {slug}.{base_domain}.
    'base_domain' => env('TENANCY_BASE_DOMAIN', 'localhost'),

    // Prefijo del nombre de la base de datos de cada empresa.
    'database_prefix' => env('TENANCY_DB_PREFIX', 'avis_'),

    // Solo para el driver sqlite: carpeta de los archivos de cada empresa.
    'sqlite_path' => env('TENANCY_SQLITE_PATH', database_path('tenants')),

    // Subdominios que ninguna empresa puede tomar.
    'reserved_slugs' => [
        'www', 'app', 'api', 'admin', 'plataforma', 'central', 'mail', 'ftp',
        'soporte', 'ayuda', 'static', 'assets', 'cdn', 'status', 'avis',
        'registro', 'ingresar', 'login', 'demo', 'test',
    ],
];
