<?php 
require 'header.php'; 

// --- 1. Obtener fechas del filtro ---
// Si 'fecha_desde' está presente en GET, úsala. Si no, usa el primer día del mes actual.
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
// Si 'fecha_hasta' está presente en GET, úsala. Si no, usa la fecha actual.
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');


// --- 2. Inicializar Totales Generales ---
$total_contado_general = 0;
$total_transferencia_general = 0;
$total_cta_corriente_general = 0;
$total_costo_general = 0;
$total_venta_general = 0;
$total_ganancia_general = 0;

// --- 3. Consulta SQL Agrupada por Día (CON FILTRO) ---
$sql_where = " WHERE DATE(v.fecha) BETWEEN ? AND ? ";

$sql = "
    SELECT
        DATE(v.fecha) AS dia,
        SUM(v.total) AS total_ventas,
        SUM(CASE WHEN v.tipo_pago = 'Contado' THEN v.total ELSE 0 END) AS total_contado,
        SUM(CASE WHEN v.tipo_pago = 'Transferencia' THEN v.total ELSE 0 END) AS total_transferencia,
        SUM(CASE WHEN v.tipo_pago = 'Cuenta Corriente' THEN v.total ELSE 0 END) AS total_cta_corriente,
        
        -- Suma del costo: (cantidad vendida * costo actual del artículo)
        IFNULL(SUM(dv.cantidad * a.precio_costo), 0) AS total_costo
        
    FROM ventas v
    LEFT JOIN detalle_venta dv ON v.id = dv.id_venta
    LEFT JOIN articulos a ON dv.id_articulo = a.id
    $sql_where
    GROUP BY DATE(v.fecha)
    ORDER BY dia DESC
";
$stmt = $pdo->prepare($sql);
// Ejecutamos la consulta con las fechas
$stmt->execute([$fecha_desde, $fecha_hasta]);
$reporte_dias = $stmt->fetchAll();

?>

<!-- Contenido principal -->
<div class="container mt-5 pt-5">
    <!-- 1. Cabecera de la Página -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Reporte de Ventas por Día</h1>
        <!-- Pasamos las fechas al botón de imprimir -->
        <a href="reporte_ventas_print.php?fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>" target="_blank" class="btn btn-info">
            <i class="bi bi-printer me-2"></i>Exportar / Imprimir
        </a>
    </div>

    <!-- 2. Formulario de Filtro (NUEVO) -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="reporte_ventas.php" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="fecha_desde" class="form-label">Desde</label>
                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
                </div>
                <div class="col-md-4">
                    <label for="fecha_hasta" class="form-label">Hasta</label>
                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Tarjeta de Contenido (Tabla) -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaReportes" class="table table-striped table-hover" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>Día</th>
                            <th>T. Contado</th>
                            <th>T. Transferencia</th>
                            <th>T. Cta. Corriente</th>
                            <th class="text-danger">Total Costo</th>
                            <th class="text-success">Total Venta</th>
                            <th class="text-primary">Ganancia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // El bucle ahora solo muestra los días filtrados
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
                            echo "<td class='fw-bold'>" . date("d/m/Y", strtotime($row['dia'])) . "</td>";
                            echo "<td>$" . number_format($row['total_contado'], 2) . "</td>";
                            echo "<td>$" . number_format($row['total_transferencia'], 2) . "</td>";
                            echo "<td>$" . number_format($row['total_cta_corriente'], 2) . "</td>";
                            echo "<td class='text-danger'>$" . number_format($row['total_costo'], 2) . "</td>";
                            echo "<td class='text-success fw-bold'>$" . number_format($row['total_ventas'], 2) . "</td>";
                            echo "<td class='text-primary fw-bold'>$" . number_format($ganancia_dia, 2) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                    <tfoot class="table-dark">
                        <tr>
                            <th>Totales (Filtrados)</th>
                            <th>$<?php echo number_format($total_contado_general, 2); ?></th>
                            <th>$<?php echo number_format($total_transferencia_general, 2); ?></th>
                            <th>$<?php echo number_format($total_cta_corriente_general, 2); ?></th>
                            <th class="text-warning">$<?php echo number_format($total_costo_general, 2); ?></th>
                            <th class="text-info">$<?php echo number_format($total_venta_general, 2); ?></th>
                            <th class="text-success">$<?php echo number_format($total_ganancia_general, 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================== -->
<!--          El Footer DEBE ir ANTES del script 
<!-- ================================================================== -->
<?php require 'footer.php'; ?>

<!-- 4. JavaScript Específico de la Página -->
<script>
$(document).ready(function() {
    
    // 1. Inicializar DataTables
    $('#tablaReportes').DataTable({
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "order": [[0, "desc"]], // Ordenar por día descendente
        "paging": false, // Desactivar paginación para ver todos los días
        "info": false, // Ocultar "Mostrando X de Y"
        "searching": false // Ocultar el buscador
    });

});
</script>