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
<div class="container mt-4 pt-5">

    <!-- 1. Cabecera de la Página -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-wallet2 me-2"></i>Gestión de Caja</h2>
            <p class="text-muted mb-0">Control de movimientos e ingresos</p>
        </div>
        <div class="d-flex gap-2">
            <!-- Botones de Acción -->
            <a href="caja_print.php?fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>"
                target="_blank" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-2"></i>Imprimir
            </a>

            <button id="btnEgresoManual" class="btn btn-danger">
                <i class="bi bi-dash-circle me-2"></i>Registrar Egreso
            </button>
            <button id="btnIngresoManual" class="btn btn-success">
                <i class="bi bi-plus-circle me-2"></i>Registrar Ingreso
            </button>
        </div>
    </div>

    <!-- 2. Filtro de Fechas (Diseño más compacto) -->
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body py-3">
            <form method="GET" action="caja.php" class="row g-2 align-items-center">
                <div class="col-auto">
                    <span class="fw-bold text-muted"><i class="bi bi-filter me-1"></i>Filtrar:</span>
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Desde</span>
                        <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Hasta</span>
                        <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm px-3">Aplicar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Tarjetas de Resumen (KPIs Mejorados) -->
    <div class="row g-3 mb-4">
        <!-- Saldo -->
        <div class="col-lg-4 col-md-12">
            <div class="card shadow border-0 bg-primary text-white h-100 overflow-hidden">
                <div class="card-body position-relative">
                    <div class="d-flex justify-content-between align-items-center z-1 position-relative">
                        <div>
                            <h6 class="text-white-50 text-uppercase mb-1">Saldo del Período</h6>
                            <h2 class="fw-bold mb-0 display-6">$<?php echo number_format($saldo_rango, 2); ?></h2>
                        </div>
                        <div class="fs-1 text-white-50">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                    <small class="text-white-50 mt-2 d-block">Ingresos - Egresos reales</small>
                </div>
            </div>
        </div>
        <!-- Facturado -->
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 border-start border-4 border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase mb-1">Total Facturado</h6>
                            <h3 class="fw-bold text-info mb-0">$<?php echo number_format($total_facturado, 2); ?></h3>
                        </div>
                        <div class="fs-1 text-info opacity-25">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block">Ventas totales registradas</small>
                </div>
            </div>
        </div>
        <!-- Egresos -->
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 border-start border-4 border-danger h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase mb-1">Total Egresos</h6>
                            <h3 class="fw-bold text-danger mb-0">$<?php echo number_format($egresos_rango, 2); ?></h3>
                        </div>
                        <div class="fs-1 text-danger opacity-25">
                            <i class="bi bi-arrow-down-square"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block">Gastos + Salidas manuales</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Totales Desglosados (Mini Cards) -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="p-3 bg-white rounded shadow-sm border d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase">Contado</span>
                    <h5 class="fw-bold text-success mb-0">$<?php echo number_format($total_contado, 2); ?></h5>
                </div>
                <i class="bi bi-cash text-success fs-3"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-white rounded shadow-sm border d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase">Transferencia</span>
                    <h5 class="fw-bold text-secondary mb-0">$<?php echo number_format($total_transferencia, 2); ?></h5>
                </div>
                <i class="bi bi-bank text-secondary fs-3"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-white rounded shadow-sm border d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase">Cta. Corriente</span>
                    <h5 class="fw-bold text-warning mb-0">$<?php echo number_format($total_cta_generada, 2); ?></h5>
                </div>
                <i class="bi bi-credit-card text-warning fs-3"></i>
            </div>
        </div>
    </div>

    <!-- 4. Tabla de Historial -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Historial de Movimientos</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tablaCaja" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Usuario</th>
                            <th class="text-end pe-3">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Consulta Historial REPETIDA (Usando la misma lógica de arriba pero aquí iteramos)
                        // IMPORTANTE: PDO Statement no se puede iterar dos veces sin volver a ejecutar.
                        // Arriba no iteramos, así que podemos usar $stmt_historial
                        
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
                            $badge_class = $es_ingreso ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle';
                            $icon = $es_ingreso ? '<i class="bi bi-arrow-up-short"></i>' : '<i class="bi bi-arrow-down-short"></i>';
                            $signo = $es_ingreso ? '+' : '-';

                            echo "<tr>";
                            echo "<td class='ps-3 text-muted small'>#" . $row['id'] . "</td>";
                            echo "<td>" . date("d/m/Y H:i", strtotime($row['fecha'])) . "</td>";
                            echo "<td><span class='badge rounded-pill $badge_class'>$icon " . $row['tipo_movimiento'] . "</span></td>";
                            echo "<td>" . htmlspecialchars($row['descripcion']) . "</td>";
                            echo "<td><span class='text-muted small'><i class='bi bi-person me-1'></i>" . htmlspecialchars($row['registrador']) . "</span></td>";
                            echo "<td class='text-end pe-3 fw-bold " . ($es_ingreso ? 'text-success' : 'text-danger') . "'>" . $signo . "$" . number_format($row['monto'], 2) . "</td>";
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
                            <input type="number" class="form-control" id="monto" name="monto" step="0.01" min="0.01"
                                required>
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
                <button type="submit" class="btn btn-primary" form="formMovimientoManual"
                    id="btnGuardarMovimiento">Guardar Movimiento</button>
            </div>
        </div>
    </div>
</div>

<!-- Footer ANTES de los scripts -->
<?php require 'footer.php'; ?>

<!-- 6. JavaScript -->
<script>
    $(document).ready(function () {

        // DataTables
        $('#tablaCaja').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
            },
            "order": [[1, "desc"]]
        });

        var modalMovimientoManual = new bootstrap.Modal($('#modalMovimientoManual'));

        // Abrir Ingreso
        $('#btnIngresoManual').click(function () {
            $('#formMovimientoManual')[0].reset();
            $('#accionCaja').val('ingreso_manual');
            $('#modalLabelCaja').text('Registrar Ingreso Manual');
            $('#btnGuardarMovimiento').removeClass('btn-danger').addClass('btn-success').text('Registrar Ingreso');
            modalMovimientoManual.show();
        });

        // Abrir Egreso
        $('#btnEgresoManual').click(function () {
            $('#formMovimientoManual')[0].reset();
            $('#accionCaja').val('egreso_manual');
            $('#modalLabelCaja').text('Registrar Egreso Manual');
            $('#btnGuardarMovimiento').removeClass('btn-success').addClass('btn-danger').text('Registrar Egreso');
            modalMovimientoManual.show();
        });

        // Submit Manual
        $('#formMovimientoManual').submit(function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            // Deshabilitar botón para evitar doble envío
            $('#btnGuardarMovimiento').prop('disabled', true).text('Guardando...');

            $.ajax({
                url: 'gestionar_caja.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        modalMovimientoManual.hide();
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(function () {
                            location.reload(); // Recargar manteniendo URL actual
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                        $('#btnGuardarMovimiento').prop('disabled', false).text('Guardar Movimiento');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Error al conectar con el servidor.', 'error');
                }
            });
        });

    });
</script>