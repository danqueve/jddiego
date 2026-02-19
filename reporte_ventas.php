<?php 
require 'header.php'; 

// --- 1. Obtener fechas del filtro ---
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// --- 2. Inicializar Totales Generales ---
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
        SUM(CASE WHEN v.tipo_pago = 'Cuenta Corriente' THEN v.total ELSE 0 END) AS total_cta_corriente,
        IFNULL(SUM(dv.cantidad * a.precio_costo), 0) AS total_costo
    FROM ventas v
    LEFT JOIN detalle_venta dv ON v.id = dv.id_venta
    LEFT JOIN articulos a ON dv.id_articulo = a.id
    $sql_where
    GROUP BY DATE(v.fecha)
    ORDER BY dia ASC
";
// Nota: Cambié ORDER BY a ASC para el gráfico, luego lo invertimos para la tabla o usamos JS
$stmt = $pdo->prepare($sql);
$stmt->execute([$fecha_desde, $fecha_hasta]);
$reporte_dias = $stmt->fetchAll();

// Arrays para el gráfico
$labels_chart = [];
$data_ventas_chart = [];
$data_ganancia_chart = [];

// Procesar datos para totales y gráfico
// (Como ordenamos ASC para el gráfico, para la tabla requerimos invertir el array si queremos DESC, o usamos DataTables order)
foreach ($reporte_dias as $row) {
    $ganancia = $row['total_ventas'] - $row['total_costo'];
    
    // Acumuladores
    $total_cta_corriente_general += $row['total_cta_corriente'];
    $total_costo_general += $row['total_costo'];
    $total_venta_general += $row['total_ventas'];
    $total_ganancia_general += $ganancia;

    // Datos Gráfico
    $labels_chart[] = date("d/m", strtotime($row['dia']));
    $data_ventas_chart[] = $row['total_ventas'];
    $data_ganancia_chart[] = $ganancia;
}

// Invertimos el array para mostrar la tabla de más reciente a más antiguo (si se prefiere así por defecto)
$reporte_tabla = array_reverse($reporte_dias);
?>

<!-- Contenido principal -->
<div class="container mt-4 pt-5">
    
    <!-- Título y Filtro -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reporte de Ventas</h2>
            <p class="text-muted mb-0">Del <?php echo date("d/m/Y", strtotime($fecha_desde)); ?> al <?php echo date("d/m/Y", strtotime($fecha_hasta)); ?></p>
        </div>
        <div class="d-flex gap-2">
             <!-- Formulario de filtro rápido -->
            <form method="GET" action="reporte_ventas.php" class="d-flex gap-2">
                <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fecha_desde; ?>" required>
                <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>" required>
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
            </form>
            <a href="reporte_ventas_print.php?fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>" target="_blank" class="btn btn-outline-secondary" title="Imprimir">
                <i class="bi bi-printer"></i>
            </a>
        </div>
    </div>

    <!-- 1. Tarjetas de Totales (KPIs) -->
    <div class="row g-3 mb-4">
        <!-- Total Ventas -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-4 border-success h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase mb-2">Total Ventas</h6>
                    <h3 class="fw-bold text-success mb-0">$<?php echo number_format($total_venta_general, 2); ?></h3>
                </div>
            </div>
        </div>
        <!-- Total Costo -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-4 border-danger h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase mb-2">Total Costo</h6>
                    <h3 class="fw-bold text-danger mb-0">$<?php echo number_format($total_costo_general, 2); ?></h3>
                </div>
            </div>
        </div>
        <!-- Ganancia Neta -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-4 border-primary h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase mb-2">Ganancia Neta</h6>
                    <h3 class="fw-bold text-primary mb-0">$<?php echo number_format($total_ganancia_general, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Gráfico -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-0 py-3">
             <h5 class="fw-bold mb-0"><i class="bi bi-graph-up me-2"></i>Evolución de Ventas y Ganancias</h5>
        </div>
        <div class="card-body">
            <canvas id="chartVentas" style="max-height: 300px;"></canvas>
        </div>
    </div>

    <!-- 3. Tabla Detallada -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 py-3">
             <h5 class="fw-bold mb-0"><i class="bi bi-table me-2"></i>Detalle por Día</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tablaReportes" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Fecha</th>
                            <th>T. Cta. Corriente</th>
                            <th>Total Costo</th>
                            <th>Total Venta</th>
                            <th class="pe-4 text-end">Ganancia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reporte_tabla as $row): 
                            $ganancia_dia = $row['total_ventas'] - $row['total_costo'];
                        ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?php echo date("d/m/Y", strtotime($row['dia'])); ?></td>
                                <td>$<?php echo number_format($row['total_cta_corriente'], 2); ?></td>
                                <td class="text-danger">$<?php echo number_format($row['total_costo'], 2); ?></td>
                                <td class="text-success fw-bold">$<?php echo number_format($row['total_ventas'], 2); ?></td>
                                <td class="pe-4 text-end text-primary fw-bold">$<?php echo number_format($ganancia_dia, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td class="ps-4">TOTALES</td>
                            <td>$<?php echo number_format($total_cta_corriente_general, 2); ?></td>
                            <td class="text-danger">$<?php echo number_format($total_costo_general, 2); ?></td>
                            <td class="text-success">$<?php echo number_format($total_venta_general, 2); ?></td>
                            <td class="pe-4 text-end text-primary">$<?php echo number_format($total_ganancia_general, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
    
    // 1. DataTables
    $('#tablaReportes').DataTable({
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "order": [], // Sin orden inicial forzado (ya viene ordenado por PHP)
        "paging": true,
        "pageLength": 10,
        "lengthChange": false,
        "searching": false,
        "dom": 'tp' // Solo tabla y paginación
    });

    // 2. Gráfico
    const ctx = document.getElementById('chartVentas').getContext('2d');
    const chartVentas = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels_chart); ?>,
            datasets: [
                {
                    label: 'Ventas ($)',
                    data: <?php echo json_encode($data_ventas_chart); ?>,
                    borderColor: '#198754', // Success green
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Ganancia ($)',
                    data: <?php echo json_encode($data_ganancia_chart); ?>,
                    borderColor: '#0d6efd', // Primary blue
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                         label: function(context) {
                            return context.dataset.label + ': $' + context.parsed.y.toLocaleString('es-AR');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return '$' + value; }
                    }
                }
            }
        }
    });

});
</script>