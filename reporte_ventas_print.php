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
$total_contado_general = 0;
$total_transferencia_general = 0;
$total_cta_corriente_general = 0;
$total_costo_general = 0;
$total_venta_general = 0;
$total_ganancia_general = 0;

$sql_where = " WHERE DATE(v.fecha) BETWEEN ? AND ? ";

$sql = "
    SELECT
        DATE(v.fecha) AS dia,
        SUM(v.total) AS total_ventas,
        SUM(CASE WHEN v.tipo_pago = 'Contado' THEN v.total ELSE 0 END) AS total_contado,
        SUM(CASE WHEN v.tipo_pago = 'Transferencia' THEN v.total ELSE 0 END) AS total_transferencia,
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
    <title>Reporte de Ventas por Día</title>
    <!-- Bootstrap CSS para un estilo limpio -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            color: #000;
        }
        .report-container {
            max-width: 1100px;
            margin: 20px auto;
            padding: 30px;
            background-color: #ffffff;
            border: 1px solid #dee2e6;
        }
        .report-header {
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        table {
            font-size: 0.9rem;
        }
        
        /* Estilos específicos para Impresión */
        @media print {
            body {
                background-color: #ffffff;
                margin: 0;
                padding: 0;
            }
            .report-container {
                box-shadow: none;
                border: none;
                margin: 0;
                max-width: 100%;
                padding: 0;
            }
            .btn-imprimir {
                display: none; /* Ocultar el botón al imprimir */
            }
            .table-dark {
                /* Forzar fondo oscuro en impresión */
                background-color: #212529 !important;
                -webkit-print-color-adjust: exact; 
                color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <div class="container-fluid text-center mt-3">
        <button class="btn btn-primary btn-imprimir" onclick="window.print()">
            Imprimir Reporte (o Guardar como PDF)
        </button>
    </div>

    <div class="report-container">
        <!-- Encabezado del Reporte -->
        <div class="report-header row">
            <div class="col-6">
                <h2>JD Descartables</h2>
                <!-- Título actualizado con las fechas -->
                <p class="mb-0">Reporte del <?php echo date("d/m/Y", strtotime($fecha_desde)); ?> al <?php echo date("d/m/Y", strtotime($fecha_hasta)); ?></p>
            </div>
            <div class="col-6 text-end">
                <h4>Reporte de Ventas por Día</h4>
                <p class="mb-0">Generado el: <?php echo date("d/m/Y H:i"); ?></p>
            </div>
        </div>

        <!-- Detalle de Artículos -->
        <div class="detalle-reporte">
            <table class="table table-bordered table-striped">
                <thead class="table-light text-center">
                    <tr>
                        <th>Día</th>
                        <th>T. Contado</th>
                        <th>T. Transferencia</th>
                        <th>T. Cta. Corriente</th>
                        <th>Total Costo</th>
                        <th>Total Venta</th>
                        <th>Ganancia</th>
                    </tr>
                </thead>
                <tbody class="text-end">
                    <?php
                    foreach ($reporte_dias as $row) {
                        $ganancia_dia = $row['total_ventas'] - $row['total_costo'];
                        
                        // Acumular para el pie de página
                        $total_contado_general += $row['total_contado'];
                        $total_transferencia_general += $row['total_transferencia'];
                        $total_cta_corriente_general += $row['total_cta_corriente'];
                        $total_costo_general += $row['total_costo'];
                        $total_venta_general += $row['total_ventas'];
                        $total_ganancia_general += $ganancia_dia;
                        
                        echo "<tr>";
                        echo "<td class='text-center fw-bold'>" . date("d/m/Y", strtotime($row['dia'])) . "</td>";
                        echo "<td>$" . number_format($row['total_contado'], 2) . "</td>";
                        echo "<td>$" . number_format($row['total_transferencia'], 2) . "</td>";
                        echo "<td>$" . number_format($row['total_cta_corriente'], 2) . "</td>";
                        echo "<td>$" . number_format($row['total_costo'], 2) . "</td>";
                        echo "<td class='fw-bold'>$" . number_format($row['total_ventas'], 2) . "</td>";
                        echo "<td class='fw-bold'>$" . number_format($ganancia_dia, 2) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
                <tfoot class="table-dark text-end fw-bold">
                    <tr>
                        <td class="text-center">Totales Generales</td>
                        <td>$<?php echo number_format($total_contado_general, 2); ?></td>
                        <td>$<?php echo number_format($total_transferencia_general, 2); ?></td>
                        <td>$<?php echo number_format($total_cta_corriente_general, 2); ?></td>
                        <td>$<?php echo number_format($total_costo_general, 2); ?></td>
                        <td>$<?php echo number_format($total_venta_general, 2); ?></td>
                        <td>$<?php echo number_format($total_ganancia_general, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</body>
</html>