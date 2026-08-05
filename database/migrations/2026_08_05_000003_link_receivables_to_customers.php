<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las cuentas por cobrar guardaban al cliente como texto libre, sin relación con
 * el módulo de Clientes que ya usan ventas, cotizaciones, pedidos, devoluciones y
 * garantías. Eso impedía ver la deuda total de un cliente y multiplicaba al mismo
 * cliente escrito de formas distintas.
 *
 * Los campos de texto se conservan: sirven para deudores ocasionales que no están
 * en el padrón de clientes, y como respaldo de lo ya registrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivables', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('sale_id')
                ->constrained()->nullOnDelete();
        });

        $this->backfillFromSales();
        $this->backfillFromExactNameMatch();
    }

    public function down(): void
    {
        Schema::table('receivables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }

    /**
     * Vía más confiable: la venta de origen ya apunta al cliente.
     */
    private function backfillFromSales(): void
    {
        DB::table('receivables')
            ->whereNull('customer_id')
            ->whereNotNull('sale_id')
            ->orderBy('id')
            ->each(function ($receivable) {
                $customerId = DB::table('sales')
                    ->where('id', $receivable->sale_id)
                    ->value('customer_id');

                if ($customerId) {
                    DB::table('receivables')
                        ->where('id', $receivable->id)
                        ->update(['customer_id' => $customerId]);
                }
            });
    }

    /**
     * Para las cuentas sueltas: solo se enlaza cuando el nombre coincide con
     * exactamente UN cliente. Ante ambigüedad se deja sin enlazar antes que
     * arriesgar atribuir una deuda al cliente equivocado.
     */
    private function backfillFromExactNameMatch(): void
    {
        DB::table('receivables')
            ->whereNull('customer_id')
            ->whereNotNull('customer_name')
            ->orderBy('id')
            ->each(function ($receivable) {
                $matches = DB::table('customers')
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($receivable->customer_name))])
                    ->pluck('id');

                if ($matches->count() === 1) {
                    DB::table('receivables')
                        ->where('id', $receivable->id)
                        ->update(['customer_id' => $matches->first()]);
                }
            });
    }
};
