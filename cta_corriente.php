<?php require 'header.php'; 

// --- 1. Obtener fechas del filtro ---
// Por defecto mostramos un rango amplio para ver la mayoría de las deudas activas
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-90 days')); 
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d'); 

// --- 2. Consulta SQL Agrupada por Cliente ---
try {
    // Agrupamos por id_cliente para sumar sus deudas
    $sql = "
        SELECT 
            v.id_cliente, 
            CONCAT(c.nombre, ' ', c.apellido) AS cliente,
            c.dni AS cliente_dni,
            COUNT(v.id) as cantidad_ventas,
            SUM(v.saldo_pendiente) as total_deuda_acumulada
        FROM ventas v
        JOIN clientes c ON v.id_cliente = c.id
        WHERE v.tipo_pago = 'Cuenta Corriente' 
          AND v.saldo_pendiente > 0.01
          AND DATE(v.fecha) BETWEEN ? AND ?
        GROUP BY v.id_cliente
        ORDER BY total_deuda_acumulada DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fecha_desde, $fecha_hasta]);
    $clientes_deudores = $stmt->fetchAll();

    // Calcular el total global de deuda en pantalla
    $total_global_periodo = 0;
    foreach ($clientes_deudores as $d) {
        $total_global_periodo += $d['total_deuda_acumulada'];
    }

} catch (PDOException $e) {
    $error_db = "Error al cargar las cuentas corrientes: " . $e->getMessage();
    $clientes_deudores = [];
    $total_global_periodo = 0;
}
?>

<!-- Contenido principal -->
<div class="container mt-5 pt-5">


    <!-- 1. Cabecera de la Página -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0 fw-bold text-primary">
            <i class="bi bi-journal-text me-2"></i>Cuentas Corrientes (Resumen)
        </h1>
        
        <!-- Botón de Imprimir Reporte -->
        <a href="cta_corriente_print.php?fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>" target="_blank" class="btn btn-secondary">
            <i class="bi bi-printer me-2"></i>Imprimir Reporte
        </a>
    </div>

    <!-- 2. Formulario de Filtro -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="cta_corriente.php" class="row g-3 align-items-end">
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
                        <i class="bi bi-funnel me-2"></i>Filtrar por Fecha de Venta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Resumen Rápido -->
    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
        <div>
            <strong>Total Pendiente Global (en este período):</strong>
            <span class="fs-5 ms-2 fw-bold text-danger">$<?php echo number_format($total_global_periodo, 2); ?></span>
        </div>
    </div>

    <!-- 4. Tabla de Clientes Deudores -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <!-- Buscador en Tiempo Real -->
            <div class="input-group mb-3">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" id="buscadorCtaCorriente" class="form-control border-start-0" placeholder="Buscar cliente o DNI...">
            </div>

            <div class="table-responsive">
                <table id="tablaCtaCorriente" class="table table-striped table-hover align-middle" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th class="text-center">Ventas Pendientes</th>
                            <th class="text-end">Total Adeudado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes_deudores as $row): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-small bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px; font-weight: bold;">
                                            <?php echo strtoupper(substr($row['cliente'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <span class="fw-bold"><?php echo htmlspecialchars($row['cliente']); ?></span>
                                            <small class='text-muted d-block'><?php echo htmlspecialchars($row['cliente_dni']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill"><?php echo $row['cantidad_ventas']; ?></span>
                                </td>
                                <td class='text-end fw-bold text-danger fs-5'>
                                    $<?php echo number_format($row['total_deuda_acumulada'], 2); ?>
                                </td>
                                <td class="text-center">
                                    <a href='ficha_cliente.php?id=<?php echo $row['id_cliente']; ?>' class='btn btn-primary btn-sm shadow-sm' title='Ver Ficha y Pagar'>
                                        <i class='bi bi-eye me-1'></i> Ver Detalle / Pagar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<?php require 'footer.php'; ?>

<script>
$(document).ready(function() {
    
    // DataTables Configuración
    var table = $('#tablaCtaCorriente').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" },
        "order": [[2, "desc"]], // Ordenar por Total Adeudado descendente
        "dom": '<"row"<"col-sm-12"tr>><"row mt-3"<"col-sm-6"i><"col-sm-6"p>>',
        "pageLength": 10
    });

    // Buscador personalizado conectado a DataTables
    $('#buscadorCtaCorriente').on('keyup', function() { table.search(this.value).draw(); });

});
</script>