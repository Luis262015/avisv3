<?php

return [

    /*
    | Módulos que un plan puede incluir. Lo que no aparece aquí es parte del
    | núcleo y lo tiene cualquier plan: punto de venta, turnos de caja,
    | productos, inventario, compras, proveedores, clientes y devoluciones.
    */
    'modules' => [
        'facturacion' => [
            'label' => 'Facturación electrónica SIAT',
            'description' => 'Emisión en línea, contingencia, notas de crédito y débito, y registro de compras ante el SIN.',
        ],
        'comercial' => [
            'label' => 'Gestión comercial',
            'description' => 'Cotizaciones, pedidos y envíos, promociones, combos y garantías.',
        ],
        'finanzas' => [
            'label' => 'Finanzas',
            'description' => 'Gastos, ingresos, retiros, cuentas por cobrar y por pagar.',
        ],
        'multitienda' => [
            'label' => 'Transferencias entre tiendas',
            'description' => 'Mueve existencias de una sucursal a otra con trazabilidad.',
        ],
        'rrhh' => [
            'label' => 'Recursos humanos',
            'description' => 'Empleados, asistencia, ausencias, nómina y capacitación.',
        ],
        'reportes' => [
            'label' => 'Reportes avanzados',
            'description' => 'Reportes de ventas, compras, finanzas y personal.',
        ],
    ],

    /*
    | Qué módulo protege cada sección. Se compara por prefijo de ruta, de más
    | específico a menos, así que no hace falta tocar routes/admin.php.
    */
    'route_modules' => [
        'admin/siat' => 'facturacion',

        'admin/quotes' => 'comercial',
        'admin/sales-orders' => 'comercial',
        'admin/shipments' => 'comercial',
        'admin/promotions' => 'comercial',
        'admin/warranties' => 'comercial',
        'admin/warranty-claims' => 'comercial',

        'admin/expenses' => 'finanzas',
        'admin/incomes' => 'finanzas',
        'admin/withdrawals' => 'finanzas',
        'admin/receivables' => 'finanzas',
        'admin/payables' => 'finanzas',

        'admin/stock-transfers' => 'multitienda',

        'admin/departments' => 'rrhh',
        'admin/employees' => 'rrhh',
        'admin/attendances' => 'rrhh',
        'admin/leave-requests' => 'rrhh',
        'admin/payrolls' => 'rrhh',
        'admin/trainings' => 'rrhh',

        'admin/sales-reports' => 'reportes',
        'admin/purchases-reports' => 'reportes',
        'admin/financial-reports' => 'reportes',
        'admin/hr-reports' => 'reportes',
    ],

    /*
    | Rutas de alta que consumen un cupo del plan: nombre de ruta => recurso.
    */
    'route_limits' => [
        'admin.stores.store' => 'stores',
        'admin.users.store' => 'users',
        'admin.products.store' => 'products',
        'admin.sales.store' => 'invoices',
    ],

    'limits' => [
        'stores' => ['column' => 'max_stores', 'label' => 'Tiendas', 'singular' => 'tienda'],
        'users' => ['column' => 'max_users', 'label' => 'Usuarios', 'singular' => 'usuario'],
        'products' => ['column' => 'max_products', 'label' => 'Productos', 'singular' => 'producto'],
        'invoices' => ['column' => 'max_invoices_month', 'label' => 'Facturas al mes', 'singular' => 'factura'],
    ],
];
