<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Por favor, inicie sesión.");
}

// Obtener fechas
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

try {
    // Consulta de deudas pendientes AGRUPADAS POR CLIENTE (Misma lógica que cta_corriente.php)
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
    $deudas = $stmt->fetchAll();

    // Calcular total global
    $total_pendiente = 0;
    foreach($deudas as $d) $total_pendiente += $d['total_deuda_acumulada'];

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Cuentas Corrientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Source Sans Pro', sans-serif; background-color: #f8f9fa; }
        .report-container { max-width: 1000px; margin: 20px auto; padding: 40px; background-color: #fff; border: 1px solid #dee2e6; }
        .header-divider { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        
        @media print {
            body { background-color: #fff; margin: 0; }
            .report-container { border: none; margin: 0; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="container-fluid text-center mt-3 no-print">
        <button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir / Guardar PDF</button>
        <button class="btn btn-dark btn-sm ms-2" onclick="window.close()">Cerrar</button>
    </div>

    <div class="report-container">
        <!-- Encabezado -->
        <div class="row header-divider align-items-end">
            <div class="col-8">
                <h2 class="fw-bold">CUENTAS CORRIENTES</h2>
                <p class="mb-0 text-muted">Resumen de Saldos Pendientes por Cliente</p>
                <p class="mb-0 small">Período: <?php echo date("d/m/Y", strtotime($fecha_desde)); ?> al <?php echo date("d/m/Y", strtotime($fecha_hasta)); ?></p>
            </div>
            <div class="col-4 text-end">
                <small>Generado: <?php echo date("d/m/Y H:i"); ?></small><br>
                <small>Usuario: <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></small>
            </div>
        </div>

        <!-- Resumen -->
        <div class="alert alert-warning text-center mb-4 p-2">
            <strong>TOTAL PENDIENTE DE COBRO: </strong>
            <span class="fs-4 ms-2">$<?php echo number_format($total_pendiente, 2); ?></span>
        </div>

        <!-- Tabla Agrupada por Cliente -->
        <table class="table table-striped table-bordered table-sm">
            <thead class="table-light text-center">
                <tr>
                    <th>Cliente</th>
                    <th>DNI / CUIT</th>
                    <th>Cant. Ventas Pend.</th>
                    <th>Total Adeudado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deudas as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['cliente']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($row['cliente_dni']); ?></td>
                    <td class="text-center"><?php echo $row['cantidad_ventas']; ?></td>
                    <td class="text-end fw-bold text-danger">$<?php echo number_format($row['total_deuda_acumulada'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if(empty($deudas)): ?>
            <p class="text-center text-muted my-5">No se encontraron deudas pendientes en el rango seleccionado.</p>
        <?php endif; ?>

        <div class="mt-5 text-center text-muted small border-top pt-3">
            <p>Fin del Reporte</p>
        </div>
    </div>

</body>
</html>