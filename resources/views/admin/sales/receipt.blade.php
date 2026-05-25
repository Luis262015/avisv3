<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo {{ $sale->folio }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.4;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            padding: 20px;
        }

        .receipt {
            background: white;
            width: 80mm;
            padding: 8mm 5mm;
        }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-left   { text-align: left; }

        .store-name {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .store-info {
            font-size: 10px;
            color: #333;
            margin-top: 2px;
        }

        .divider-double {
            border-top: 2px double #000;
            margin: 5px 0;
        }

        .divider-single {
            border-top: 1px solid #000;
            margin: 4px 0;
        }

        .divider-dashed {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }

        .meta { font-size: 11px; margin: 2px 0; }

        .items-header {
            display: flex;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 4px 0 2px;
        }

        .item-row {
            display: flex;
            font-size: 11px;
            margin: 2px 0;
            align-items: flex-start;
        }

        .col-qty   { width: 22px; flex-shrink: 0; }
        .col-name  { flex: 1; padding: 0 3px; word-break: break-word; }
        .col-price { width: 42px; flex-shrink: 0; text-align: right; }
        .col-total { width: 46px; flex-shrink: 0; text-align: right; }

        .totals-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin: 2px 0;
        }

        .totals-row.grand {
            font-size: 14px;
            font-weight: bold;
            margin: 4px 0;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin: 2px 0;
        }

        .footer-msg {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .footer-sub {
            font-size: 10px;
            margin-top: 2px;
            color: #333;
        }

        /* ── Pantalla: recuadro visible ── */
        @media screen {
            .receipt {
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            }
            .print-btn-wrap {
                display: flex;
                gap: 8px;
                justify-content: center;
                margin-top: 12px;
            }
            .print-btn {
                font-family: inherit;
                font-size: 13px;
                padding: 8px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                background: #1a1a1a;
                color: white;
            }
            .close-btn {
                font-family: inherit;
                font-size: 13px;
                padding: 8px 20px;
                border: 1px solid #ccc;
                border-radius: 4px;
                cursor: pointer;
                background: white;
                color: #333;
            }
        }

        /* ── Impresión: solo el recibo ── */
        @media print {
            body { background: white; padding: 0; }
            .receipt { width: 100%; padding: 2mm 3mm; box-shadow: none; }
            .print-btn-wrap { display: none !important; }
            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="receipt">

    {{-- Encabezado de tienda --}}
    <div class="text-center">
        <p class="store-name">{{ $store->name }}</p>
        @if($store->address)
            <p class="store-info">{{ $store->address }}</p>
        @endif
        @if($store->phone || $store->rfc)
            <p class="store-info">
                @if($store->phone) Tel: {{ $store->phone }} @endif
                @if($store->phone && $store->rfc) &nbsp;|&nbsp; @endif
                @if($store->rfc) RFC: {{ $store->rfc }} @endif
            </p>
        @endif
    </div>

    <div class="divider-double"></div>

    {{-- Datos de la venta --}}
    <p class="meta">Fecha : {{ $sale->created_at->format('d/m/Y  H:i:s') }}</p>
    <p class="meta">Folio : {{ $sale->folio }}</p>
    <p class="meta">Cajero: {{ $sale->user->name }}</p>
    <p class="meta">Caja  : {{ $cashRegister->name }}</p>

    <div class="divider-double"></div>

    {{-- Encabezado de columnas --}}
    <div class="items-header">
        <span class="col-qty">Cant</span>
        <span class="col-name">Producto</span>
        <span class="col-price">P/U</span>
        <span class="col-total">Total</span>
    </div>

    <div class="divider-dashed"></div>

    {{-- Artículos --}}
    @foreach($sale->items as $item)
        <div class="item-row">
            <span class="col-qty">{{ rtrim(rtrim(number_format((float)$item->quantity, 2, '.', ''), '0'), '.') }}</span>
            <span class="col-name">{{ $item->product->name }}</span>
            <span class="col-price">${{ number_format((float)$item->price, 2) }}</span>
            <span class="col-total">${{ number_format((float)$item->subtotal, 2) }}</span>
        </div>
        @if((float)$item->discount > 0)
            <div class="item-row" style="color:#555; font-size:10px;">
                <span class="col-qty"></span>
                <span class="col-name" style="padding-left:6px;">Descuento</span>
                <span class="col-total" style="color:red;">-${{ number_format((float)$item->discount, 2) }}</span>
            </div>
        @endif
    @endforeach

    <div class="divider-single"></div>

    {{-- Totales --}}
    <div class="totals-row">
        <span>Subtotal</span>
        <span>${{ number_format((float)$sale->subtotal, 2) }}</span>
    </div>
    @if((float)$sale->discount > 0)
        <div class="totals-row" style="color: #b00;">
            <span>Descuento</span>
            <span>-${{ number_format((float)$sale->discount, 2) }}</span>
        </div>
    @endif
    @if((float)$sale->tax > 0)
        <div class="totals-row">
            <span>IVA</span>
            <span>${{ number_format((float)$sale->tax, 2) }}</span>
        </div>
    @endif

    <div class="divider-double"></div>

    <div class="totals-row grand">
        <span>TOTAL</span>
        <span>${{ number_format((float)$sale->total, 2) }}</span>
    </div>

    <div class="divider-double"></div>

    {{-- Información de pago --}}
    @php
        $paymentLabels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'mixed' => 'Mixto'];
    @endphp
    <div class="payment-row">
        <span>Método de pago</span>
        <span>{{ $paymentLabels[$sale->payment_method] ?? $sale->payment_method }}</span>
    </div>
    <div class="payment-row">
        <span>Recibido</span>
        <span>${{ number_format((float)$sale->amount_paid, 2) }}</span>
    </div>
    <div class="payment-row" style="font-weight: bold;">
        <span>Cambio</span>
        <span>${{ number_format((float)$sale->change_amount, 2) }}</span>
    </div>

    @if($sale->notes)
        <div class="divider-dashed"></div>
        <p class="meta" style="font-size:10px; color:#555;">Nota: {{ $sale->notes }}</p>
    @endif

    <div class="divider-double"></div>

    {{-- Pie --}}
    <div class="text-center">
        <p class="footer-msg">* GRACIAS POR SU COMPRA *</p>
        <p class="footer-sub">Conserve su ticket</p>
        @if($store->email)
            <p class="footer-sub">{{ $store->email }}</p>
        @endif
    </div>

</div>

<div class="print-btn-wrap">
    <button class="print-btn" onclick="window.print()">Imprimir</button>
    <button class="close-btn" onclick="window.close()">Cerrar</button>
</div>

<script>
    window.addEventListener('load', function () {
        window.print();
    });
</script>

</body>
</html>
