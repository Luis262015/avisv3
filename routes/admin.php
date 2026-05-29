<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CashRegisterController;
use App\Http\Controllers\Admin\CashShiftController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeDocumentController;
use App\Http\Controllers\Admin\EmployeeIncidentController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FinancialReportController;
use App\Http\Controllers\Admin\HrReportController;
use App\Http\Controllers\Admin\IncomeController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\LeaveRequestController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\PayableController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\PurchaseReportController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\ReceivableController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SaleReturnController;
use App\Http\Controllers\Admin\SalesOrderController;
use App\Http\Controllers\Admin\SalesReportController;
use App\Http\Controllers\Admin\SiatInvoiceController;
use App\Http\Controllers\Admin\SiatSettingController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TrainingController;
use App\Http\Controllers\Admin\TrainingParticipantController;
use App\Http\Controllers\Admin\SupplierEvaluationController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\WarrantyClaimController;
use App\Http\Controllers\Admin\WarrantyController;
use App\Http\Controllers\Admin\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    // ── Solo admin ─────────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::resource('stores', StoreController::class)->except(['show']);
        Route::resource('cash-registers', CashRegisterController::class)->except(['show']);

        // SIAT Bolivia v2 — Configuración
        Route::prefix('siat')->name('siat.')->group(function () {
            Route::resource('settings', SiatSettingController::class)
                ->except(['show'])
                ->names('settings');
            Route::post('settings/{setting}/generate-cufd', [SiatSettingController::class, 'generateCufd'])
                ->name('settings.generate-cufd');
            Route::get('settings/{setting}/cufd-history', [SiatSettingController::class, 'cufdHistory'])
                ->name('settings.cufd-history');
        });

        // ── Recursos Humanos ────────────────────────────────────────────────
        // Áreas / departamentos
        Route::resource('departments', DepartmentController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Empleados — ficha completa con historial
        Route::resource('employees', EmployeeController::class);

        // Documentos del empleado (cumplimiento normativo)
        Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
            ->name('employees.documents.store');
        Route::get('employees/{employee}/documents/{document}/download', [EmployeeDocumentController::class, 'download'])
            ->name('employees.documents.download');
        Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])
            ->name('employees.documents.destroy');

        // Relaciones laborales (llamadas de atención, sanciones, reconocimientos)
        Route::post('employees/{employee}/incidents', [EmployeeIncidentController::class, 'store'])
            ->name('employees.incidents.store');
        Route::delete('employees/{employee}/incidents/{incident}', [EmployeeIncidentController::class, 'destroy'])
            ->name('employees.incidents.destroy');

        // Nómina / planillas
        Route::resource('payrolls', PayrollController::class)
            ->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::patch('payrolls/{payroll}/items/{item}', [PayrollController::class, 'updateItem'])
            ->name('payrolls.items.update');
        Route::patch('payrolls/{payroll}/approve', [PayrollController::class, 'approve'])
            ->name('payrolls.approve');
        Route::patch('payrolls/{payroll}/pay', [PayrollController::class, 'markPaid'])
            ->name('payrolls.pay');

        // Tiempo y asistencia
        Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::post('attendances', [AttendanceController::class, 'store'])->name('attendances.store');
        Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy'])->name('attendances.destroy');

        // Ausencias, permisos y vacaciones
        Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
        Route::patch('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::patch('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
        Route::delete('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->name('leave-requests.destroy');

        // Capacitación y formación
        Route::resource('trainings', TrainingController::class);
        Route::post('trainings/{training}/participants', [TrainingParticipantController::class, 'store'])
            ->name('trainings.participants.store');
        Route::patch('trainings/{training}/participants/{employee}', [TrainingParticipantController::class, 'update'])
            ->name('trainings.participants.update');
        Route::delete('trainings/{training}/participants/{employee}', [TrainingParticipantController::class, 'destroy'])
            ->name('trainings.participants.destroy');

        // Análisis y reportes de RR.HH.
        Route::get('hr-reports', [HrReportController::class, 'index'])->name('hr.reports');
    });

    // ── Admin + Operador ────────────────────────────────────────────────────
    Route::middleware('role:admin|operador')->group(function () {
        Route::resource('brands', BrandController::class)->except(['show']);
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('tags', TagController::class)->except(['show']);

        // Proveedores — incluye show para ficha con historial y evaluaciones
        Route::resource('suppliers', SupplierController::class);

        // Evaluaciones de proveedores
        Route::prefix('suppliers/{supplier}/evaluations')->name('suppliers.evaluations.')->group(function () {
            Route::get('/', [SupplierEvaluationController::class, 'index'])->name('index');
            Route::get('/create', [SupplierEvaluationController::class, 'create'])->name('create');
            Route::post('/', [SupplierEvaluationController::class, 'store'])->name('store');
            Route::delete('/{evaluation}', [SupplierEvaluationController::class, 'destroy'])->name('destroy');
        });

        // Products
        Route::resource('products', ProductController::class);
        Route::delete('product-images/{image}', [ProductController::class, 'destroyImage'])->name('product-images.destroy');
        Route::patch('product-images/{image}/primary', [ProductController::class, 'setPrimaryImage'])->name('product-images.primary');

        // Órdenes de compra — planificación
        Route::resource('purchase-orders', PurchaseOrderController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::patch('purchase-orders/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm'])
            ->name('purchase-orders.confirm');
        Route::patch('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'markSent'])
            ->name('purchase-orders.send');
        Route::post('purchase-orders/{purchaseOrder}/convert', [PurchaseOrderController::class, 'convert'])
            ->name('purchase-orders.convert');
        Route::patch('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->name('purchase-orders.cancel');

        // Compras
        Route::resource('purchases', PurchaseController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::patch('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])
            ->name('purchases.receive');
        Route::patch('purchases/{purchase}/receive-partial', [PurchaseController::class, 'receivePartial'])
            ->name('purchases.receive-partial');
        Route::post('purchases/{purchase}/document', [PurchaseController::class, 'attachDocument'])
            ->name('purchases.document');
        Route::patch('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])
            ->name('purchases.cancel');

        // Reportes de compras
        Route::get('purchases-reports', [PurchaseReportController::class, 'index'])
            ->name('purchases.reports');

        // ── Ventas — gestión comercial ──────────────────────────────────────
        // Clientes
        Route::resource('customers', CustomerController::class);

        // Cotizaciones y presupuestos
        Route::resource('quotes', QuoteController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::patch('quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send');
        Route::patch('quotes/{quote}/accept', [QuoteController::class, 'accept'])->name('quotes.accept');
        Route::patch('quotes/{quote}/reject', [QuoteController::class, 'reject'])->name('quotes.reject');
        Route::patch('quotes/{quote}/cancel', [QuoteController::class, 'cancel'])->name('quotes.cancel');
        Route::post('quotes/{quote}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');

        // Pedidos y envíos
        Route::resource('sales-orders', SalesOrderController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::patch('sales-orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])->name('sales-orders.confirm');
        Route::patch('sales-orders/{salesOrder}/prepare', [SalesOrderController::class, 'prepare'])->name('sales-orders.prepare');
        Route::post('sales-orders/{salesOrder}/ship', [SalesOrderController::class, 'ship'])->name('sales-orders.ship');
        Route::post('sales-orders/{salesOrder}/deliver', [SalesOrderController::class, 'deliver'])->name('sales-orders.deliver');
        Route::patch('sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->name('sales-orders.cancel');

        // Descuentos y promociones
        Route::resource('promotions', PromotionController::class)->except(['show']);
        Route::patch('promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('promotions.toggle');

        // Devoluciones
        Route::get('returns', [SaleReturnController::class, 'index'])->name('returns.index');
        Route::get('returns/create', [SaleReturnController::class, 'create'])->name('returns.create');
        Route::post('returns', [SaleReturnController::class, 'store'])->name('returns.store');
        Route::get('returns/{return}', [SaleReturnController::class, 'show'])->name('returns.show');
        Route::patch('returns/{return}/approve', [SaleReturnController::class, 'approve'])->name('returns.approve');
        Route::patch('returns/{return}/complete', [SaleReturnController::class, 'complete'])->name('returns.complete');
        Route::patch('returns/{return}/reject', [SaleReturnController::class, 'reject'])->name('returns.reject');

        // Garantías
        Route::resource('warranties', WarrantyController::class);
        Route::patch('warranties/{warranty}/void', [WarrantyController::class, 'void'])->name('warranties.void');
        Route::post('warranties/{warranty}/claims', [WarrantyClaimController::class, 'store'])->name('warranties.claims.store');
        Route::patch('warranties/{warranty}/claims/{claim}', [WarrantyClaimController::class, 'update'])->name('warranties.claims.update');

        // Reportes y análisis de ventas
        Route::get('sales-reports', [SalesReportController::class, 'index'])->name('sales.reports');

        // Reporte financiero — ventas, compras, gastos, ingresos, retiros, CxC y CxP
        Route::get('financial-reports', [FinancialReportController::class, 'index'])->name('financial.reports');

        // Inventory
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

        // Stock transfers
        Route::resource('stock-transfers', StockTransferController::class)->only(['index', 'create', 'store', 'show']);
        Route::patch('stock-transfers/{stockTransfer}/complete', [StockTransferController::class, 'complete'])->name('stock-transfers.complete');
        Route::patch('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');
    });

    // ── Edición restringida a admin + operador ──────────────────────────────
    Route::middleware('role:admin|operador')->group(function () {
        Route::get('sales/{sale}/edit', [SaleController::class, 'edit'])->name('sales.edit');
        Route::patch('sales/{sale}', [SaleController::class, 'update'])->name('sales.update');

        Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::patch('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');

        Route::get('incomes/{income}/edit', [IncomeController::class, 'edit'])->name('incomes.edit');
        Route::patch('incomes/{income}', [IncomeController::class, 'update'])->name('incomes.update');

        Route::get('withdrawals/{withdrawal}/edit', [WithdrawalController::class, 'edit'])->name('withdrawals.edit');
        Route::patch('withdrawals/{withdrawal}', [WithdrawalController::class, 'update'])->name('withdrawals.update');
    });

    // ── Todos los autenticados (admin + operador + vendedor) ────────────────
    Route::group([], function () {
        // Cash shifts – cualquier usuario puede iniciar un turno
        Route::resource('cash-shifts', CashShiftController::class)->only(['index', 'create', 'store', 'show']);
        Route::patch('cash-shifts/{cashShift}/close', [CashShiftController::class, 'close'])->name('cash-shifts.close');

        // Sales
        Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show']);
        Route::patch('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
        Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');

        // SIAT Bolivia v2 — Facturas electrónicas
        Route::prefix('siat')->name('siat.')->group(function () {
            Route::get('invoices', [SiatInvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{siatInvoice}', [SiatInvoiceController::class, 'show'])->name('invoices.show');
            Route::get('invoices/{siatInvoice}/print', [SiatInvoiceController::class, 'print'])->name('invoices.print');
            Route::post('invoices/{siatInvoice}/cancel', [SiatInvoiceController::class, 'cancel'])->name('invoices.cancel');
            Route::post('invoices/{siatInvoice}/resend', [SiatInvoiceController::class, 'resend'])->name('invoices.resend');
            Route::post('sales/{sale}/emit-invoice', [SiatInvoiceController::class, 'emit'])->name('sales.emit-invoice');
        });

        // Gastos
        Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store']);

        // Ingresos
        Route::resource('incomes', IncomeController::class)->only(['index', 'create', 'store']);

        // Retiros
        Route::resource('withdrawals', WithdrawalController::class)->only(['index', 'create', 'store']);

        // Cuentas por cobrar
        Route::resource('receivables', ReceivableController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('receivables/{receivable}/payments', [ReceivableController::class, 'storePayment'])->name('receivables.payments.store');
        Route::patch('receivables/{receivable}/cancel', [ReceivableController::class, 'cancel'])->name('receivables.cancel');

        // Cuentas por pagar
        Route::resource('payables', PayableController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('payables/{payable}/payments', [PayableController::class, 'storePayment'])->name('payables.payments.store');
        Route::patch('payables/{payable}/cancel', [PayableController::class, 'cancel'])->name('payables.cancel');
    });
});
