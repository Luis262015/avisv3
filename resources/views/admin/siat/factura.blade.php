<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura #{{ $invoice->numero_factura }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            background: #f0f0f0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        .factura {
            background: white;
            width: 210mm;
            min-height: 148mm;
            padding: 12mm 15mm;
        }

        /* ── Cabecera ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #003366;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .empresa-nombre {
            font-size: 18px;
            font-weight: bold;
            color: #003366;
            text-transform: uppercase;
        }

        .empresa-info { font-size: 10px; color: #444; margin-top: 3px; line-height: 1.6; }

        .factura-titulo {
            text-align: right;
        }

        .factura-titulo h2 {
            font-size: 16px;
            font-weight: bold;
            color: #003366;
            text-transform: uppercase;
        }

        .nit-box {
            display: inline-block;
            border: 2px solid #003366;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: bold;
            color: #003366;
            margin-top: 4px;
        }

        /* ── Datos de la factura ── */
        .factura-datos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 20px;
            margin: 10px 0;
            font-size: 10.5px;
        }

        .dato-row { display: flex; gap: 4px; }
        .dato-label { color: #555; white-space: nowrap; }
        .dato-value { font-weight: 600; color: #000; }
        .dato-value.mono { font-family: 'Courier New', monospace; font-size: 9.5px; word-break: break-all; }

        /* ── Sección cliente ── */
        .section-title {
            background: #003366;
            color: white;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 3px 8px;
            margin: 8px 0 4px;
            letter-spacing: 0.5px;
        }

        .cliente-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 20px;
            font-size: 10.5px;
            margin-bottom: 8px;
        }

        /* ── Tabla de detalle ── */
        table.detalle {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            margin: 6px 0;
        }

        table.detalle thead tr {
            background: #003366;
            color: white;
        }

        table.detalle thead th {
            padding: 4px 6px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }

        table.detalle thead th.right { text-align: right; }

        table.detalle tbody tr:nth-child(even) { background: #f7f9fc; }

        table.detalle tbody td {
            padding: 4px 6px;
            border-bottom: 1px solid #e5e7eb;
        }

        table.detalle tbody td.right { text-align: right; }

        /* ── Totales ── */
        .totales-wrap {
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }

        .totales-table {
            width: 200px;
            font-size: 10.5px;
        }

        .totales-table tr td:first-child { color: #555; }
        .totales-table tr td:last-child { text-align: right; font-weight: 600; }

        .totales-table tr.grand td {
            font-size: 13px;
            font-weight: bold;
            color: #003366;
            border-top: 2px solid #003366;
            padding-top: 4px;
        }

        /* ── Leyenda legal ── */
        .leyenda {
            margin-top: 12px;
            border: 1px solid #ccc;
            padding: 6px 8px;
            font-size: 9.5px;
            color: #555;
            font-style: italic;
        }

        /* ── CUF / QR ── */
        .cuf-section {
            margin-top: 10px;
            padding: 6px 8px;
            background: #f7f9fc;
            border-left: 3px solid #003366;
            font-size: 9.5px;
        }

        .cuf-label { font-weight: bold; color: #003366; margin-bottom: 2px; }
        .cuf-code { font-family: 'Courier New', monospace; font-size: 9px; word-break: break-all; }

        .qr-link {
            margin-top: 4px;
            color: #003366;
            text-decoration: none;
            font-size: 9px;
        }

        /* ── Estado ── */
        .estado-anulada {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 80px;
            font-weight: bold;
            color: rgba(200, 0, 0, 0.10);
            text-transform: uppercase;
            pointer-events: none;
            white-space: nowrap;
        }

        .factura-wrapper {
            position: relative;
        }

        /* ── Botones pantalla ── */
        @media screen {
            .factura { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
            .print-bar {
                display: flex;
                gap: 10px;
                justify-content: center;
                margin: 16px 0;
            }
            .btn {
                font-family: Arial;
                font-size: 13px;
                padding: 8px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .btn-primary { background: #003366; color: white; }
            .btn-secondary { background: white; color: #333; border: 1px solid #ccc; }
        }

        @media print {
            body { background: white; padding: 0; }
            .factura { width: 100%; min-height: auto; padding: 8mm 10mm; box-shadow: none; }
            .print-bar { display: none !important; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

@php
    $paymentLabels = [1 => 'Efectivo', 2 => 'Tarjeta', 3 => 'Transferencia bancaria', 7 => 'Código QR'];
    $tipoDocLabels = [1 => 'CI Bolivia', 2 => 'Pasaporte', 3 => 'Carnet Extranjería', 4 => 'Otro', 5 => 'NIT'];
@endphp

<div class="factura-wrapper">
    <div class="factura">

        @if($invoice->estado === 'anulada')
            <div class="estado-anulada">ANULADA</div>
        @endif

        {{-- ENCABEZADO --}}
        <div class="header">
            <div>
                <p class="empresa-nombre">{{ $store->name }}</p>
                <div class="empresa-info">
                    @if($setting?->direccion) <p>{{ $setting->direccion }}, {{ $setting?->municipio }}</p> @endif
                    @if($setting?->telefono) <p>Tel: {{ $setting->telefono }}</p> @endif
                    @if($setting?->actividad_descripcion) <p>Actividad: {{ $setting->actividad_descripcion }}</p> @endif
                </div>
            </div>
            <div class="factura-titulo">
                <h2>Factura</h2>
                <div class="nit-box">NIT: {{ $setting?->nit ?? 'N/A' }}</div>
                <p style="margin-top:6px; font-size:11px; color:#444;">
                    Nro. Factura: <strong>{{ $invoice->numero_factura }}</strong>
                </p>
                <p style="font-size:10px; color:#666;">
                    {{ $invoice->created_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}
                </p>
            </div>
        </div>

        {{-- DATOS DE LA FACTURA --}}
        <div class="factura-datos">
            <div>
                <div class="dato-row">
                    <span class="dato-label">Tipo:</span>
                    <span class="dato-value">
                        {{ $invoice->tipo_factura === 1 ? 'Con derecho a crédito fiscal' : 'Sin derecho a crédito fiscal' }}
                    </span>
                </div>
                <div class="dato-row">
                    <span class="dato-label">Método de pago:</span>
                    <span class="dato-value">{{ $paymentLabels[$invoice->metodo_pago] ?? 'Efectivo' }}</span>
                </div>
                <div class="dato-row">
                    <span class="dato-label">Emisión:</span>
                    <span class="dato-value">{{ $invoice->tipo_emision === 1 ? 'En línea' : 'Fuera de línea' }}</span>
                </div>
            </div>
            <div>
                <div class="dato-row">
                    <span class="dato-label">Actividad CAEB:</span>
                    <span class="dato-value">{{ $setting?->actividad_economica }}</span>
                </div>
                <div class="dato-row">
                    <span class="dato-label">Sucursal:</span>
                    <span class="dato-value">{{ $setting?->codigo_sucursal ?? 0 }}</span>
                </div>
                <div class="dato-row">
                    <span class="dato-label">Punto de venta:</span>
                    <span class="dato-value">{{ $setting?->nombre_punto_venta }}</span>
                </div>
            </div>
        </div>

        {{-- DATOS DEL COMPRADOR --}}
        <div class="section-title">Datos del Comprador</div>
        <div class="cliente-grid">
            <div class="dato-row">
                <span class="dato-label">{{ $tipoDocLabels[$invoice->tipo_doc_identidad] ?? 'Documento' }}:</span>
                <span class="dato-value">{{ $invoice->nit_ci === '0' ? '—' : $invoice->nit_ci }}</span>
            </div>
            <div class="dato-row">
                <span class="dato-label">Nombre / Razón Social:</span>
                <span class="dato-value">{{ $invoice->nombre_razon_social }}</span>
            </div>
        </div>

        {{-- DETALLE --}}
        <div class="section-title">Detalle</div>
        <table class="detalle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Descripción</th>
                    <th>Unidad</th>
                    <th class="right">Cant.</th>
                    <th class="right">P. Unitario</th>
                    <th class="right">Descuento</th>
                    <th class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->sale->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            {{ $item->product->name }}
                            @if($item->product->sku) <span style="color:#888; font-size:9px;">({{ $item->product->sku }})</span> @endif
                        </td>
                        <td>PZA</td>
                        <td class="right">{{ rtrim(rtrim(number_format((float)$item->quantity, 2, '.', ''), '0'), '.') }}</td>
                        <td class="right">{{ number_format((float)$item->price, 2) }}</td>
                        <td class="right">{{ (float)$item->discount > 0 ? number_format((float)$item->discount, 2) : '—' }}</td>
                        <td class="right"><strong>{{ number_format((float)$item->subtotal, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- TOTALES --}}
        <div class="totales-wrap">
            <table class="totales-table">
                @if((float)$invoice->descuento > 0)
                    <tr>
                        <td>Descuento:</td>
                        <td>- Bs {{ number_format((float)$invoice->descuento, 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td>Base CF:</td>
                    <td>Bs {{ number_format((float)$invoice->importe_base_cf, 2) }}</td>
                </tr>
                <tr class="grand">
                    <td>TOTAL Bs:</td>
                    <td>{{ number_format((float)$invoice->importe_total, 2) }}</td>
                </tr>
            </table>
        </div>

        {{-- LEYENDA LEGAL --}}
        <div class="leyenda">
            "ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LA LEY"
        </div>

        {{-- CUF Y QR --}}
        <div class="cuf-section">
            <p class="cuf-label">CUF (Código Único de Factura):</p>
            <p class="cuf-code">{{ $invoice->cuf }}</p>
            @if($invoice->codigo_qr)
                <p style="margin-top:4px; font-size:9px; color:#555;">
                    Verificar en: <a href="{{ $invoice->codigo_qr }}" class="qr-link">{{ $invoice->codigo_qr }}</a>
                </p>
            @endif
        </div>

        {{-- Pie --}}
        <div style="margin-top: 10px; text-align: center; font-size: 9px; color: #888; border-top: 1px dashed #ccc; padding-top: 6px;">
            Sistema de Facturación Electrónica Bolivia — SIAT v2 &nbsp;|&nbsp;
            Emitido por: {{ $invoice->sale->user->name }}
        </div>

    </div>{{-- .factura --}}
</div>

<div class="print-bar">
    <button class="btn btn-primary" onclick="window.print()">Imprimir Factura</button>
    <button class="btn btn-secondary" onclick="window.close()">Cerrar</button>
</div>

<script>
    window.addEventListener('load', function() {
        window.print();
    });
</script>
</body>
</html>
