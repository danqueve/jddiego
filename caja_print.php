<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Por favor, inicie sesión.");
}

// --- Obtener fechas del filtro ---
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$params_fecha = [$fecha_desde, $fecha_hasta];

try {
    // --- Replicar Cálculos de Caja ---
    
    // Saldo del Período
    $stmt_ingresos = $pdo->prepare("SELECT IFNULL(SUM(monto), 0) FROM movimientos_caja WHERE tipo_movimiento = 'Ingreso' AND DATE(fecha) BETWEEN ? AND ?");
    $stmt_ingresos->execute($params_fecha);
    $ingresos_rango = $stmt_ingresos->fetchColumn();

    $stmt_egresos = $pdo->prepare("SELECT IFNULL(SUM(monto), 0) FROM movimientos_caja WHERE tipo_movimiento = 'Egreso' AND DATE(fecha) BETWEEN ? AND ?");
    $stmt_egresos->execute($params_fecha);
    $egresos_rango = $stmt_egresos->fetchColumn();

    $saldo_rango = $ingresos_rango - $egresos_rango;

    // Totales de Ventas
    $stmt_ventas_total = $pdo->prepare("SELECT IFNULL(SUM(total), 0) FROM ventas WHERE DATE(fecha) BETWEEN ? AND ?");
    $stmt_ventas_total->execute($params_fecha);
    $total_facturado = $stmt_ventas_total->fetchColumn();

    $stmt_ventas_contado = $pdo->prepare("SELECT IFNULL(SUM(total), 0) FROM ventas WHERE tipo_pago = 'Contado' AND DATE(fecha) BETWEEN ? AND ?");
    $stmt_ventas_contado->execute($params_fecha);
    $total_contado = $stmt_ventas_contado->fetchColumn();

    $stmt_ventas_transf = $pdo->prepare("SELECT IFNULL(SUM(total), 0) FROM ventas WHERE tipo_pago = 'Transferencia' AND DATE(fecha) BETWEEN ? AND ?");
    $stmt_ventas_transf->execute($params_fecha);
    $total_transferencia = $stmt_ventas_transf->fetchColumn();
    
    $stmt_ventas_cta = $pdo->prepare("SELECT IFNULL(SUM(total), 0) FROM ventas WHERE tipo_pago = 'Cuenta Corriente' AND DATE(fecha) BETWEEN ? AND ?");
    $stmt_ventas_cta->execute($params_fecha);
    $total_cta_generada = $stmt_ventas_cta->fetchColumn();

    // Historial de Movimientos
    $sql = "
        SELECT 
            m.id, m.fecha, m.tipo_movimiento, m.descripcion, m.monto,
            u.nombre_usuario AS registrador
        FROM movimientos_caja m
        JOIN usuarios u ON m.id_usuario = u.id
        WHERE DATE(m.fecha) BETWEEN ? AND ?
        ORDER BY m.fecha ASC
    ";
    $stmt_movimientos = $pdo->prepare($sql);
    $stmt_movimientos->execute($params_fecha);
    $movimientos = $stmt_movimientos->fetchAll();

} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Caja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Source Sans Pro', sans-serif; background-color: #f8f9fa; }
        .report-container { max-width: 1000px; margin: 20px auto; padding: 30px; background-color: #fff; border: 1px solid #dee2e6; }
        .header-divider { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        
        @media print {
            body { background-color: #fff; margin: 0; }
            .report-container { border: none; margin: 0; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            .bg-light { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="container-fluid text-center mt-3 no-print">
        <button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir Reporte</button>
        <button class="btn btn-dark btn-sm ms-2" onclick="window.close()">Cerrar</button>
    </div>

    <div class="report-container">
        <!-- Encabezado -->
        <div class="row header-divider align-items-end">
            <div class="col-8">
                <h2 class="fw-bold">REPORTE DE CAJA</h2>
                <p class="mb-0">Desde: <strong><?php echo date("d/m/Y", strtotime($fecha_desde)); ?></strong> Hasta: <strong><?php echo date("d/m/Y", strtotime($fecha_hasta)); ?></strong></p>
            </div>
            <div class="col-4 text-end">
                <small>Generado: <?php echo date("d/m/Y H:i"); ?></small><br>
                <small>Usuario: <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></small>
            </div>
        </div>

        <!-- Resumen Financiero -->
        <div class="row mb-4 g-3">
            <div class="col-4">
                <div class="p-3 border rounded bg-light text-center">
                    <h6 class="text-muted mb-1">SALDO DEL PERÍODO</h6>
                    <h3 class="fw-bold text-primary mb-0">$<?php echo number_format($saldo_rango, 2); ?></h3>
                </div>
            </div>
            <div class="col-4">
                <div class="p-3 border rounded bg-light text-center">
                    <h6 class="text-muted mb-1">INGRESOS CAJA</h6>
                    <h3 class="fw-bold text-success mb-0">$<?php echo number_format($ingresos_rango, 2); ?></h3>
                </div>
            </div>
            <div class="col-4">
                <div class="p-3 border rounded bg-light text-center">
                    <h6 class="text-muted mb-1">EGRESOS CAJA</h6>
                    <h3 class="fw-bold text-danger mb-0">$<?php echo number_format($egresos_rango, 2); ?></h3>
                </div>
            </div>
        </div>

        <h5 class="fw-bold mt-4 mb-3 border-bottom pb-2">Detalle de Ventas (Facturación)</h5>
        <div class="row mb-4">
            <div class="col-12">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Concepto</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Ventas en Contado (Efectivo)</td>
                            <td class="text-end">$<?php echo number_format($total_contado, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Ventas por Transferencia</td>
                            <td class="text-end">$<?php echo number_format($total_transferencia, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Ventas en Cta. Corriente (A cobrar)</td>
                            <td class="text-end">$<?php echo number_format($total_cta_generada, 2); ?></td>
                        </tr>
                        <tr class="fw-bold bg-light">
                            <td>TOTAL FACTURADO</td>
                            <td class="text-end">$<?php echo number_format($total_facturado, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>