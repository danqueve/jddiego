<?php
// 1. Iniciar Sesión y Conectar a la BD
session_start();
require 'db_connect.php';

// 2. Seguridad: Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Por favor, inicie sesión.");
}

try {
    // 3. Consultar todos los artículos con stock (y sus proveedores)
    $sql = "
        SELECT a.*, IFNULL(p.nombre_proveedor, '-') as proveedor
        FROM articulos a
        LEFT JOIN proveedores p ON a.id_proveedor = p.id
        ORDER BY a.descripcion ASC
    ";
    $stmt = $pdo->query($sql);
    $articulos = $stmt->fetchAll();

    // 4. Calcular Totales Generales
    $total_stock_fisico = 0;
    $total_capital_costo = 0;
    $total_valor_venta = 0;

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
    <title>Reporte de Valorización de Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Source Sans Pro', sans-serif; background-color: #f8f9fa; }
        .report-container { max-width: 1100px; margin: 20px auto; padding: 40px; background-color: #fff; border: 1px solid #dee2e6; }
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
        <button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir / Guardar PDF</button>
        <button class="btn btn-dark btn-sm ms-2" onclick="window.close()">Cerrar</button>
    </div>

    <div class="report-container">
        <!-- Encabezado -->
        <div class="row header-divider align-items-end">
            <div class="col-8">
                <h2 class="fw-bold">INVENTARIO Y VALORIZACIÓN</h2>
                <p class="mb-0 text-muted">Listado de Stock y Capital Invertido</p>
            </div>
            <div class="col-4 text-end">
                <small>Fecha: <?php echo date("d/m/Y H:i"); ?></small><br>
                <small>Usuario: <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></small>
            </div>
        </div>

        <!-- Resumen Financiero -->
        <div class="row mb-4 g-3">
            <div class="col-4">
                <div class="p-3 border rounded bg-light text-center">
                    <h6 class="text-muted mb-1 text-uppercase">Items en Stock</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo number_format($total_stock_fisico, 0); ?></h3>
                </div>
            </div>
            <div class="col-4">
                <div class="p-3 border rounded bg-light text-center">
                    <h6 class="text-muted mb-1 text-uppercase">Capital (Costo)</h6>
                    <h3 class="fw-bold text-primary mb-0">$<?php echo number_format($total_capital_costo, 2); ?></h3>
                </div>
            </div>
            <div class="col-4">
                <div class="p-3 border rounded bg-light text-center">
                    <h6 class="text-muted mb-1 text-uppercase">Valor Potencial (Venta)</h6>
                    <h3 class="fw-bold text-success mb-0">$<?php echo number_format($total_valor_venta, 2); ?></h3>
                </div>
            </div>
        </div>

        <!-- Tabla Detallada -->
        <table class="table table-striped table-bordered table-sm" style="font-size: 0.9rem;">
            <thead class="table-light text-center">
                <tr>
                    <th>Cód.</th>
                    <th>Descripción</th>
                    <th>Stock</th>
                    <th class="text-end">Costo Unit.</th>
                    <th class="text-end">Venta Unit.</th>
                    <th class="text-end table-primary">Total Costo</th>
                    <th class="text-end table-success">Total Venta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($articulos as $art): 
                    $subtotal_costo = $art['stock_actual'] * $art['precio_costo'];
                    $subtotal_venta = $art['stock_actual'] * $art['precio_venta'];
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($art['codigo']); ?></td>
                    <td><?php echo htmlspecialchars($art['descripcion']); ?></td>
                    <td class="text-center fw-bold"><?php echo $art['stock_actual']; ?></td>
                    <td class="text-end text-muted">$<?php echo number_format($art['precio_costo'], 2); ?></td>
                    <td class="text-end text-muted">$<?php echo number_format($art['precio_venta'], 2); ?></td>
                    
                    <td class="text-end fw-bold text-primary">$<?php echo number_format($subtotal_costo, 2); ?></td>
                    <td class="text-end fw-bold text-success">$<?php echo number_format($subtotal_venta, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-dark">
                <tr>
                    <td colspan="5" class="text-end fw-bold">TOTALES GENERALES:</td>
                    <td class="text-end fw-bold">$<?php echo number_format($total_capital_costo, 2); ?></td>
                    <td class="text-end fw-bold">$<?php echo number_format($total_valor_venta, 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="mt-4 text-center text-muted small border-top pt-3">
            <p>Fin del Reporte de Stock</p>
        </div>
    </div>

</body>
</html>