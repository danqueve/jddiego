<?php
// 1. Iniciar Sesión y Conectar a la BD
session_start();
require 'db_connect.php';

// 2. Seguridad
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Por favor, inicie sesión.");
}

try {
    // 3. Consultar todos los artículos
    $sql = "
        SELECT a.*, IFNULL(p.nombre_proveedor, '-') as proveedor
        FROM articulos a
        LEFT JOIN proveedores p ON a.id_proveedor = p.id
        ORDER BY a.descripcion ASC
    ";
    $stmt = $pdo->query($sql);
    $articulos = $stmt->fetchAll();

    // 4. Calcular Totales
    $total_stock_fisico = 0;
    $total_capital_costo = 0;
    $total_valor_venta = 0;
    $total_articulos = count($articulos);

    foreach ($articulos as $art) {
        $total_stock_fisico += $art['stock_actual'];
        $total_capital_costo += ($art['stock_actual'] * $art['precio_costo']);
        $total_valor_venta += ($art['stock_actual'] * $art['precio_venta']);
    }

} catch (PDOException $e) {
    die("Error al generar reporte: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Stock — <?php echo date('d/m/Y'); ?></title>

    <style>
        /* ── Tipografía y reset ── */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #212529;
            background: #f0f0f0;
        }

        /* ── Contenedor de previsualización en pantalla ── */
        .page-wrapper {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            padding: 15mm 14mm 18mm 14mm;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .18);
        }

        /* ── Barra de acciones (no se imprime) ── */
        .action-bar {
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .action-bar h6 {
            margin: 0;
            flex: 1;
            font-size: 14px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 8px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity .15s;
        }

        .btn:hover {
            opacity: .85;
        }

        .btn-primary {
            background: #0d6efd;
            color: #fff;
        }

        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }

        /* ── Encabezado del reporte ── */
        .report-header {
            border-bottom: 2.5px solid #212529;
            padding-bottom: 10px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .report-header h1 {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -.3px;
            margin-bottom: 2px;
        }

        .report-header .subtitle {
            font-size: 10px;
            color: #6c757d;
        }

        .report-header .meta {
            text-align: right;
            font-size: 9.5px;
            color: #495057;
            line-height: 1.6;
        }

        /* ── Resumen de totales ── */
        .summary-box {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
        }

        .summary-item {
            flex: 1;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 8px 10px;
            text-align: center;
            background: #f8f9fa;
        }

        .summary-item .s-label {
            font-size: 8.5px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: .06em;
            color: #6c757d;
            margin-bottom: 3px;
        }

        .summary-item .s-value {
            font-size: 14px;
            font-weight: 800;
        }

        .s-dark {
            color: #212529;
        }

        .s-blue {
            color: #0d6efd;
        }

        .s-green {
            color: #198754;
        }

        /* ── Tabla de artículos ── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        thead th {
            background-color: #343a40;
            color: #fff;
            padding: 5px 6px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            font-size: 8px;
        }

        tbody tr {
            border-bottom: 1px solid #e9ecef;
        }

        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        td {
            padding: 4px 6px;
            vertical-align: middle;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .fw-bold {
            font-weight: 700;
        }

        .text-muted {
            color: #6c757d;
        }

        .text-primary {
            color: #0d6efd;
        }

        .text-success {
            color: #198754;
        }

        .text-danger {
            color: #dc3545;
        }

        /* stock 0 → resaltar fila */
        .row-sin-stock {
            background-color: #fff3f3 !important;
        }

        .row-sin-stock td {
            color: #dc3545;
        }

        /* ── Pie de tabla (totales) ── */
        tfoot tr {
            background-color: #212529 !important;
            color: #fff;
        }

        tfoot td {
            padding: 5px 6px;
            font-weight: 700;
            font-size: 9px;
        }

        /* ── Pie de página ── */
        .report-footer {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid #dee2e6;
            font-size: 8.5px;
            color: #adb5bd;
            display: flex;
            justify-content: space-between;
        }

        /* ══════════════════════════════════════
           ESTILOS EXCLUSIVOS DE IMPRESIÓN / PDF
           ══════════════════════════════════════ */
        @media print {
            @page {
                size: A4 portrait;
                margin: 13mm 12mm 16mm 12mm;
            }

            html,
            body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .action-bar {
                display: none !important;
            }

            .page-wrapper {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                background: transparent !important;
            }

            /* Repetir encabezado de tabla en cada página */
            thead {
                display: table-header-group;
            }

            tfoot {
                display: table-footer-group;
            }

            /* Evitar cortes dentro de una fila */
            tbody tr {
                page-break-inside: avoid;
            }

            /* Mantener colores de fondo en impresión */
            thead th {
                background-color: #343a40 !important;
                color: #fff !important;
            }

            tbody tr:nth-child(even) {
                background-color: #f8f9fa !important;
            }

            .row-sin-stock {
                background-color: #fff3f3 !important;
            }

            tfoot tr {
                background-color: #212529 !important;
                color: #fff !important;
            }

            .summary-item {
                background-color: #f8f9fa !important;
            }

            /* Contador de páginas nativo via CSS counter */
            .page-number::after {
                content: "Página " counter(page) " de " counter(pages);
            }
        }
    </style>
</head>

<body>

    <!-- Barra de acciones (solo pantalla) -->
    <div class="action-bar">
        <h6>📄 Vista Previa — Reporte de Stock</h6>
        <button class="btn btn-primary" onclick="window.print()">
            🖨️ Imprimir / Guardar PDF
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            ✕ Cerrar
        </button>
    </div>

    <!-- Contenedor A4 -->
    <div class="page-wrapper">

        <!-- Encabezado -->
        <div class="report-header">
            <div>
                <h1>INVENTARIO Y VALORIZACIÓN DE STOCK</h1>
                <div class="subtitle">Listado completo de artículos · Ordenado alfabéticamente</div>
            </div>
            <div class="meta">
                <strong>Fecha:</strong> <?php echo date("d/m/Y H:i"); ?><br>
                <strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?><br>
                <strong>Total artículos:</strong> <?php echo $total_articulos; ?>
            </div>
        </div>

        <!-- Resumen financiero -->
        <div class="summary-box">
            <div class="summary-item">
                <div class="s-label">Unidades en Stock</div>
                <div class="s-value s-dark"><?php echo number_format($total_stock_fisico, 0); ?></div>
            </div>
            <div class="summary-item">
                <div class="s-label">Capital (Costo)</div>
                <div class="s-value s-blue">$<?php echo number_format($total_capital_costo, 2); ?></div>
            </div>
            <div class="summary-item">
                <div class="s-label">Valor Potencial (Venta)</div>
                <div class="s-value s-green">$<?php echo number_format($total_valor_venta, 2); ?></div>
            </div>
            <div class="summary-item">
                <div class="s-label">Margen Bruto Potencial</div>
                <div class="s-value s-green">$<?php echo number_format($total_valor_venta - $total_capital_costo, 2); ?>
                </div>
            </div>
        </div>

        <!-- Tabla de artículos -->
        <table>
            <thead>
                <tr>
                    <th style="width:55px;">Cód.</th>
                    <th>Descripción</th>
                    <th style="width:45px;" class="text-center">Stock</th>
                    <th style="width:65px;" class="text-end">Costo Unit.</th>
                    <th style="width:65px;" class="text-end">Venta Unit.</th>
                    <th style="width:75px;" class="text-end">Total Costo</th>
                    <th style="width:75px;" class="text-end">Total Venta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($articulos as $art):
                    $subtotal_costo = $art['stock_actual'] * $art['precio_costo'];
                    $subtotal_venta = $art['stock_actual'] * $art['precio_venta'];
                    $row_class = ($art['stock_actual'] == 0) ? 'row-sin-stock' : '';
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td class="text-muted"><?php echo htmlspecialchars($art['codigo']); ?></td>
                        <td class="fw-bold"><?php echo htmlspecialchars($art['descripcion']); ?></td>
                        <td class="text-center fw-bold <?php echo ($art['stock_actual'] == 0) ? 'text-danger' : ''; ?>">
                            <?php echo $art['stock_actual']; ?>
                        </td>
                        <td class="text-end text-muted">$<?php echo number_format($art['precio_costo'], 2); ?></td>
                        <td class="text-end text-muted">$<?php echo number_format($art['precio_venta'], 2); ?></td>
                        <td class="text-end fw-bold text-primary">$<?php echo number_format($subtotal_costo, 2); ?></td>
                        <td class="text-end fw-bold text-success">$<?php echo number_format($subtotal_venta, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-end">TOTALES GENERALES:</td>
                    <td class="text-end">$<?php echo number_format($total_capital_costo, 2); ?></td>
                    <td class="text-end">$<?php echo number_format($total_valor_venta, 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Pie del reporte -->
        <div class="report-footer">
            <span>Sistema de Ventas JD</span>
            <span class="page-number"></span>
            <span>Reporte generado el <?php echo date("d/m/Y \a \l\a\s H:i"); ?></span>
        </div>

    </div><!-- /page-wrapper -->

</body>

</html>