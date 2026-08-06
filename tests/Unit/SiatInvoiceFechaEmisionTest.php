<?php

namespace Tests\Unit;

use App\Models\SiatInvoice;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * La fecha de emisión va dentro del CUF con precisión de milisegundos
 * (`yyyyMMddHHmmssSSS`) y el SIN comprueba que coincida con la del XML. Si el
 * modelo los pierde, cualquier reenvío de una factura pendiente se rechaza.
 */
class SiatInvoiceFechaEmisionTest extends TestCase
{
    public function test_it_keeps_milliseconds_when_writing(): void
    {
        $fecha = Carbon::parse('2026-08-06 00:33:43.454', 'America/La_Paz');

        $invoice = new SiatInvoice(['fecha_emision' => $fecha]);

        // Se guarda en UTC, como el resto de fechas de la aplicación.
        $this->assertSame('2026-08-06 04:33:43.454', $invoice->getAttributes()['fecha_emision']);
    }

    public function test_the_bolivian_time_survives_the_round_trip(): void
    {
        $fecha   = Carbon::parse('2026-08-06 00:33:43.454', 'America/La_Paz');
        $invoice = new SiatInvoice(['fecha_emision' => $fecha]);

        // Releer y convertir es justo lo que hace el constructor del XML.
        $releida = $invoice->fecha_emision->copy()->setTimezone('America/La_Paz');

        $this->assertSame($fecha->format('Y-m-d H:i:s.v'), $releida->format('Y-m-d H:i:s.v'));
    }

    public function test_it_reads_older_rows_that_have_no_milliseconds(): void
    {
        $invoice = new SiatInvoice();
        $invoice->setRawAttributes(['fecha_emision' => '2026-08-06 04:33:43']);

        $this->assertSame('2026-08-06 04:33:43.000', $invoice->fecha_emision->format('Y-m-d H:i:s.v'));
    }

    public function test_it_accepts_a_null_date(): void
    {
        $this->assertNull((new SiatInvoice(['fecha_emision' => null]))->fecha_emision);
    }
}
