<?php 
require 'header.php'; 

// --- 1. Obtener fechas del filtro ---
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01'); // Primer día del mes actual por defecto
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d'); // Día actual por defecto

// Parámetros para las consultas
$params_fecha = [$fecha_desde, $fecha_hasta];

// --- 2. Consultas para CAJA (Dinero Real) ---

// 2.1. Total Ingresos de Caja (Ventas efectivo/transf + Ingresos manuales)
$stmt_ingresos = $pdo->prepare("
    SELECT IFNULL(SUM(monto), 0) 
    FROM movimientos_caja 
    WHERE tipo_movimiento = 'Ingreso' AND DATE(fecha) BETWEEN ? AND ?
");
$stmt_ingresos->execute($params_fecha);
$ingresos_rango = $stmt_ingresos->fetchColumn();

// 2.2. Total Egresos de Caja (Compras + Egresos manuales)
$stmt_egresos = $pdo->prepare("
    SELECT IFNULL(SUM(monto), 0) 
    FROM movimientos_caja 
    WHERE tipo_movimiento = 'Egreso' AND DATE(fecha) BETWEEN ? AND ?
");
$stmt_egresos->execute($params_fecha);
$egresos_rango = $stmt_egresos->fetchColumn();

// 2.3. Saldo del Período
$saldo_rango = $ingresos_rango - $egresos_rango;


// --- 3. Consultas para VENTAS (Facturación / Deuda) ---

// 3.1. Total Facturado (Suma de todas las ventas)
$stmt_ventas_total = $pdo->prepare("
    SELECT IFNULL(SUM(total), 0) 
    FROM ventas 
    WHERE DATE(fecha) BETWEEN ? AND ?
");
$stmt_ventas_total->execute($params_fecha);
$total_facturado = $stmt_ventas_total->fetchColumn();

// 3.2. Ventas en Contado
$stmt_ventas_contado = $pdo->prepare("
    SELECT IFNULL(SUM(total), 0) 
    FROM ventas 
    WHERE tipo_pago = 'Contado' AND DATE(fecha) BETWEEN ? AND ?
");
$stmt_ventas_contado->execute($params_fecha);
$total_contado = $stmt_ventas_contado->fetchColumn();

// 3.3. Ventas por Transferencia
$stmt_ventas_transf = $pdo->prepare("
    SELECT IFNULL(SUM(total), 0) 
    FROM ventas 
    WHERE tipo_pago = 'Transferencia' AND DATE(fecha) BETWEEN ? AND ?
");
$stmt_ventas_transf->execute($params_fecha);
$total_transferencia = $stmt_ventas_transf->fetchColumn();

// 3.4. Cta. Corriente Generada (Ventas a crédito en el periodo)
$stmt_ventas_cta = $pdo->prepare("
    SELECT IFNULL(SUM(total), 0) 
    FROM ventas 
    WHERE tipo_pago = 'Cuenta Corriente' AND DATE(fecha) BETWEEN ? AND ?
");
$stmt_ventas_cta->execute($params_fecha);
$total_cta_generada = $stmt_ventas_cta->fetchColumn();
?>

<!-- Contenido principal -->
<div class="container mt-5 pt-5">

    <!-- 1. Cabecera de la Página -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Gestión de Caja</h1>
        <div>
            <!-- Botones de Acción -->
            <a href="caja_print.php?fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>" target="_blank" class="btn btn-secondary me-2">
                <i class="bi bi-printer me-2"></i>Imprimir Reporte
            </a>
            
            <button id="btnEgresoManual" class="btn btn-danger">
                <i class="bi bi-arrow-down-circle me-2"></i>Registrar Egreso Manual
            </button>
            <button id="btnIngresoManual" class="btn btn-success">
                <i class="bi bi-arrow-up-circle me-2"></i>Registrar Ingreso Manual
            </button>
        </div>
    </div>

    <!-- 2. Filtro de Fechas -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="caja.php" class="row g-3 align-items-end">
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
    
    <!-- 3. Tarjetas de Resumen (Estilo Pastel) -->
    <div class="row g-3 mb-4">
        <!-- Fila 1 -->
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 bg-primary-subtle border-primary-subtle h-100">
                <div class="card-body">
                    <h5 class="card-title text-primary-emphasis">Saldo del Período</h5>
                    <p class="card-text fs-2 fw-bold text-primary-emphasis">$<?php echo number_format($saldo_rango, 2); ?></p>
                    <small class="text-primary-emphasis opacity-75">(Ingresos - Egresos en el rango)</small>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 bg-info-subtle border-info-subtle h-100">
                <div class="card-body">
                    <h5 class="card-title text-info-emphasis">Total Ventas (Facturado)</h5>
                    <p class="card-text fs-2 fw-bold text-info-emphasis">$<?php echo number_format($total_facturado, 2); ?></p>
                    <small class="text-info-emphasis opacity-75">(Contado + Transf. + Cta. Cte.)</small>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 bg-danger-subtle border-danger-subtle h-100">
                <div class="card-body">
                    <h5 class="card-title text-danger-emphasis">Total Egresos de Caja</h5>
                    <p class="card-text fs-2 fw-bold text-danger-emphasis">$<?php echo number_format($egresos_rango, 2); ?></p>
                    <small class="text-danger-emphasis opacity-75">(Compras + Mov. Manuales)</small>
                </div>
            </div>
        </div>
        
        <!-- Fila 2 -->
         <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 bg-success-subtle border-success-subtle h-100">
                <div class="card-body">
                    <h5 class="card-title text-success-emphasis">Ventas en Contado</h5>
                    <p class="card-text fs-4 fw-bold text-success-emphasis">$<?php echo number_format($total_contado, 2); ?></p>
                </div>
            </div>
        </div>
         <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 bg-secondary-subtle border-secondary-subtle h-100">
                <div class="card-body">
                    <h5 class="card-title text-secondary-emphasis">Ventas por Transferencia</h5>
                    <p class="card-text fs-4 fw-bold text-secondary-emphasis">$<?php echo number_format($total_transferencia, 2); ?></p>
                </div>
            </div>
        </div>
         <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 bg-warning-subtle border-warning-subtle h-100">
                <div class="card-body">
                    <h5 class="card-title text-warning-emphasis">Cta. Corriente (Generada)</h5>
                    <p class="card-text fs-4 fw-bold text-warning-emphasis">$<?php echo number_format($total_cta_generada, 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Tabla de Historial -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h4 class="mb-3">Historial de Movimientos de Caja (Filtrado)</h4>
            <div class="table-responsive">
                <table id="tablaCaja" class="table table-striped table-hover" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>ID Mov.</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Registró</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Consulta Historial
                        $stmt_historial = $pdo->prepare("
                            SELECT 
                                m.id, 
                                m.fecha, 
                                m.tipo_movimiento, 
                                m.descripcion, 
                                m.monto,
                                u.nombre_usuario AS registrador
                            FROM movimientos_caja m
                            JOIN usuarios u ON m.id_usuario = u.id
                            WHERE DATE(m.fecha) BETWEEN ? AND ?
                            ORDER BY m.fecha DESC
                        ");
                        $stmt_historial->execute($params_fecha);

                        while ($row = $stmt_historial->fetch()) {
                            $es_ingreso = $row['tipo_movimiento'] == 'Ingreso';
                            $color_class = $es_ingreso ? 'text-success' : 'text-danger';
                            $signo = $es_ingreso ? '+' : '-';
                            
                            echo "<tr>";
                            echo "<td>" . $row['id'] . "</td>";
                            echo "<td>" . date("d/m/Y H:i", strtotime($row['fecha'])) . "</td>";
                            echo "<td class='fw-bold " . $color_class . "'>" . $row['tipo_movimiento'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['descripcion']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['registrador']) . "</td>";
                            echo "<td class='fw-bold " . $color_class . "'>" . $signo . "$" . number_format($row['monto'], 2) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 5. Modal Movimiento Manual -->
<div class="modal fade" id="modalMovimientoManual" tabindex="-1" aria-labelledby="modalLabelCaja" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabelCaja">Registrar Movimiento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formMovimientoManual">
                    <input type="hidden" id="accionCaja" name="accion" value="">
                    
                    <div class="mb-3">
                        <label for="monto" class="form-label">Monto</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="monto" name="monto" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" form="formMovimientoManual" id="btnGuardarMovimiento">Guardar Movimiento</button>
            </div>
        </div>
    </div>
</div>

<!-- Footer ANTES de los scripts -->
<?php require 'footer.php'; ?>

<!-- 6. JavaScript -->
<script>
$(document).ready(function() {
    
    // DataTables
    $('#tablaCaja').DataTable({
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "order": [[1, "desc"]] 
    });

    var modalMovimientoManual = new bootstrap.Modal($('#modalMovimientoManual'));

    // Abrir Ingreso
    $('#btnIngresoManual').click(function() {
        $('#formMovimientoManual')[0].reset();
        $('#accionCaja').val('ingreso_manual');
        $('#modalLabelCaja').text('Registrar Ingreso Manual');
        $('#btnGuardarMovimiento').removeClass('btn-danger').addClass('btn-success').text('Registrar Ingreso');
        modalMovimientoManual.show();
    });

    // Abrir Egreso
    $('#btnEgresoManual').click(function() {
        $('#formMovimientoManual')[0].reset();
        $('#accionCaja').val('egreso_manual');
        $('#modalLabelCaja').text('Registrar Egreso Manual');
        $('#btnGuardarMovimiento').removeClass('btn-success').addClass('btn-danger').text('Registrar Egreso');
        modalMovimientoManual.show();
    });

    // Submit Manual
    $('#formMovimientoManual').submit(function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        
        $.ajax({
            url: 'gestionar_caja.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    modalMovimientoManual.hide();
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(function() {
                        // Recargar manteniendo fechas
                        window.location.search = window.location.search;
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error al conectar con el servidor.', 'error');
            }
        });
    });

});
</script>