<?php

use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\SiatInvoiceController;
use App\Http\Controllers\Admin\SiatSettingController;
use App\Http\Controllers\Admin\CashRegisterController;
use App\Http\Controllers\Admin\CashShiftController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\IncomeController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\PayableController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\ReceivableController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TagController;
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
    });

    // ── Admin + Operador ────────────────────────────────────────────────────
    Route::middleware('role:admin|operador')->group(function () {
        Route::resource('brands', BrandController::class)->except(['show']);
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('tags', TagController::class)->except(['show']);
        Route::resource('suppliers', SupplierController::class)->except(['show']);

        // Products
        Route::resource('products', ProductController::class);
        Route::delete('product-images/{image}', [ProductController::class, 'destroyImage'])->name('product-images.destroy');
        Route::patch('product-images/{image}/primary', [ProductController::class, 'setPrimaryImage'])->name('product-images.primary');

        // Purchases
        Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::patch('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
        Route::patch('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');

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
