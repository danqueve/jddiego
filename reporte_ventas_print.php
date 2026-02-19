<?php
// 1. Iniciar Sesión y Conectar a la BD
session_start();
require 'db_connect.php';

// 2. Seguridad: Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Por favor, inicie sesión.");
}

// 3. Obtener fechas del filtro (desde la URL)
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// 4. Ejecutar la misma consulta con filtro
$total_cta_corriente_general = 0;
$total_costo_general = 0;
$total_venta_general = 0;
$total_ganancia_general = 0;

$sql_where = " WHERE DATE(v.fecha) BETWEEN ? AND ? ";

$sql = "
    SELECT
        DATE(v.fecha) AS dia,
        SUM(v.total) AS total_ventas,
        SUM(CASE WHEN v.tipo_pago = 'Cuenta Corriente' THEN v.total ELSE 0 END) AS total_cta_corriente,
        IFNULL(SUM(dv.cantidad * a.precio_costo), 0) AS total_costo
    FROM ventas v
    LEFT JOIN detalle_venta dv ON v.id = dv.id_venta
    LEFT JOIN articulos a ON dv.id_articulo = a.id
    $sql_where
    GROUP BY DATE(v.fecha)
    ORDER BY dia DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$fecha_desde, $fecha_hasta]);
$reporte_dias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas</title>
    <!-- Bootstrap CSS para un estilo limpio -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            color: #000;
            font-family: sans-serif;
        }
        .report-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 40px;
            background-color: #ffffff;
            border: 1px solid #dee2e6;
        }
        .report-header {
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .summary-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .summary-title {
            font-size: 0.9rem;
            text-transform: uppercase;
            color: #6c757d;
            font-weight: 600;
        }
        .summary-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        table {
            font-size: 0.95rem;
        }
        
        /* Estilos específicos para Impresión */
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }
            body {
                background-color: #ffffff;
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
            }
            .report-container {
                box-shadow: none;
                border: none;
                margin: 0;
                max-width: 100%;
                padding: 0;
                width: 100%;
            }
            .btn-imprimir {
                display: none; /* Ocultar el botón al imprimir */
            }
            .table-dark {
                background-color: #212529 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
            }
            .summary-box {
                border: 1px solid #000;
            }
            /* Asegurar que los colores de texto se impriman bien */
            .text-success { color: #198754 !important; }
            .text-danger { color: #dc3545 !important; }
            .text-primary { color: #0d6efd !important; }
        }
    </style>
</head>
<body>

    <div class="container-fluid text-center mt-3 btn-imprimir">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer-fill"></i> Imprimir Reporte
        </button>
        <button class="btn btn-secondary ms-2" onclick="window.close()">
            Cerrar
        </button>
    </div>

    <div class="report-container">
        <!-- Encabezado del Reporte -->
        <div class="report-header d-flex justify-content-between align-items-end">
            <div>
                <h2 class="fw-bold mb-1">JD Descartables</h2>
                <p class="mb-0 text-muted">Reporte de Ventas y Ganancias</p>
            </div>
            <div class="text-end">
                <p class="mb-1"><strong>Desde:</strong> <?php echo date("d/m/Y", strtotime($fecha_desde)); ?></p>
                <p class="mb-1"><strong>Hasta:</strong> <?php echo date("d/m/Y", strtotime($fecha_hasta)); ?></p>
                <p class="mb-0 small text-muted">Generado: <?php echo date("d/m/Y H:i"); ?></p>
            </div>
        </div>

        <!-- Pre-cálculo de totales para mostrarlos arriba -->
        <?php
        foreach ($reporte_dias as $row) {
            $ganancia_dia = $row['total_ventas'] - $row['total_costo'];
            
            $total_cta_corriente_general += $row['total_cta_corriente'];
            $total_costo_general += $row['total_costo'];
            $total_venta_general += $row['total_ventas'];
            $total_ganancia_general += $ganancia_dia;
        }
        ?>

        <!-- Resumen de Totales (KPIs Print) -->
        <div class="row mb-4 g-3">
            <div class="col-4">
                <div class="summary-box">
                    <div class="summary-title">Total Ventas</div>
                    <div class="summary-value text-success">$<?php echo number_format($total_venta_general, 2); ?></div>
                </div>
            </div>
            <div class="col-4">
                <div class="summary-box">
                    <div class="summary-title">Total Costo</div>
                    <div class="summary-value text-danger">$<?php echo number_format($total_costo_general, 2); ?></div>
                </div>
            </div>
            <div class="col-4">
                <div class="summary-box">
                    <div class="summary-title">Ganancia Neta</div>
                    <div class="summary-value text-primary">$<?php echo number_format($total_ganancia_general, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Detalle de Artículos -->
        <div class="detalle-reporte">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th>Fecha</th>
                        <th>T. Cta. Corriente</th>
                        <th>Total Costo</th>
                        <th>Total Venta</th>
                        <th>Ganancia</th>
                    </tr>
                </thead>
                <tbody class="text-end">
                    <?php
                    foreach ($reporte_dias as $row) {
                        $ganancia = $row['total_ventas'] - $row['total_costo'];
                        
                        echo "<tr>";
                        echo "<td class='text-center fw-bold'>" . date("d/m/Y", strtotime($row['dia'])) . "</td>";
                        echo "<td>$" . number_format($row['total_cta_corriente'], 2) . "</td>";
                        echo "<td>$" . number_format($row['total_costo'], 2) . "</td>";
                        echo "<td class='fw-bold'>$" . number_format($row['total_ventas'], 2) . "</td>";
                        echo "<td class='fw-bold'>$" . number_format($ganancia, 2) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
                <tfoot class="table-dark text-end fw-bold">
                    <tr>
                        <td class="text-center">TOTALES</td>
                        <td>$<?php echo number_format($total_cta_corriente_general, 2); ?></td>
                        <td>$<?php echo number_format($total_costo_general, 2); ?></td>
                        <td>$<?php echo number_format($total_venta_general, 2); ?></td>
                        <td>$<?php echo number_format($total_ganancia_general, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="text-center mt-5">
            <p class="text-muted small">Fin del Reporte</p>
        </div>
    </div>

</body>
</html>