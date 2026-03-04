<?php
// 1. Iniciar Sesión y Conectar a la BD
session_start();
require 'db_connect.php';

// 2. Seguridad: Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Por favor, inicie sesión.");
}

// 3. Obtener ID del cliente (cast seguro a entero)
$id_cliente = (int) ($_GET['id'] ?? 0);
if ($id_cliente <= 0) {
    die("ID de cliente no válido.");
}

try {
    // 4. Consultar datos del Cliente
    $stmt_cliente = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt_cliente->execute([$id_cliente]);
    $cliente = $stmt_cliente->fetch();

    if (!$cliente) {
        die("Cliente no encontrado.");
    }

    // 5. Calcular Saldo Total Pendiente
    $stmt_saldo = $pdo->prepare("
        SELECT SUM(saldo_pendiente) 
        FROM ventas 
        WHERE id_cliente = ? AND tipo_pago = 'Cuenta Corriente'
    ");
    $stmt_saldo->execute([$id_cliente]);
    $saldo_total = $stmt_saldo->fetchColumn() ?: 0;

    // 6. Obtener Historial (Ventas y Pagos unificados)
    $sql_historial = "
        (SELECT 
            'Venta' as tipo,
            id as id_ref,
            fecha,
            total as debe,
            0.00 as haber,
            saldo_pendiente
        FROM ventas 
        WHERE id_cliente = ? AND tipo_pago = 'Cuenta Corriente')
        
        UNION ALL
        
        (SELECT 
            'Pago' as tipo,
            p.id as id_ref,
            p.fecha,
            0.00 as debe,
            p.monto_pagado as haber,
            0.00 as saldo_pendiente
        FROM pagos_cta_corriente p
        JOIN ventas v ON p.id_venta = v.id
        WHERE v.id_cliente = ?)
        
        ORDER BY fecha DESC, tipo ASC
    ";
    $stmt_historial = $pdo->prepare($sql_historial);
    $stmt_historial->execute([$id_cliente, $id_cliente]);
    $historial = $stmt_historial->fetchAll();

} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de Cuenta - <?php echo htmlspecialchars($cliente['apellido']); ?></title>
    <!-- Bootstrap CSS para estilo limpio -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap"
        rel="stylesheet">

    <style>
        /* ── Base ── */
        * {
            box-sizing: border-box;
        }

        body {
            background-color: #f8f9fa;
            color: #000;
            font-family: 'Source Sans Pro', sans-serif;
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
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        /* ── Estilos de impresión / PDF ── */
        @media print {

            /* Definir página A4 vertical con márgenes */
            @page {
                size: A4 portrait;
                margin: 14mm 12mm 16mm 12mm;
            }

            html,
            body {
                background-color: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .report-container {
                box-shadow: none;
                border: none;
                margin: 0;
                max-width: 100%;
                padding: 0;
            }

            .btn-imprimir {
                display: none !important;
            }

            /* Repetir encabezado de tabla en cada página */
            thead {
                display: table-header-group;
            }

            tfoot {
                display: table-footer-group;
            }

            tbody tr {
                page-break-inside: avoid;
            }

            /* Mantener colores en PDF */
            .table-light {
                background-color: #f8f9fa !important;
            }

            .bg-light {
                background-color: #f8f9fa !important;
            }

            .text-danger {
                color: #dc3545 !important;
            }

            .text-success {
                color: #198754 !important;
            }

            .text-muted {
                color: #6c757d !important;
            }

            .fw-bold {
                font-weight: 700 !important;
            }
        }
    </style>
</head>

<body>

    <div class="container-fluid text-center mt-3 btn-imprimir">
        <button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir / Guardar PDF</button>
        <button class="btn btn-dark btn-sm ms-2" onclick="window.close()">Cerrar</button>
    </div>

    <div class="report-container">
        <!-- Encabezado -->
        <div class="row report-header align-items-end">
            <div class="col-8">
                <h2 class="fw-bold text-uppercase">Estado de Cuenta</h2>
                <p class="mb-0 text-muted">Resumen de movimientos de Cuenta Corriente</p>
            </div>
            <div class="col-4 text-end">
                <p class="mb-0 small">Fecha de Emisión: <?php echo date("d/m/Y H:i"); ?></p>
                <p class="mb-0 small">Usuario: <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></p>
            </div>
        </div>

        <!-- Datos del Cliente y Saldo -->
        <div class="row mb-4">
            <div class="col-md-7">
                <div class="p-3 bg-light border rounded">
                    <h5 class="fw-bold mb-3">Datos del Cliente</h5>
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td style="width: 100px;" class="text-muted">Nombre:</td>
                            <td class="fw-bold">
                                <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">DNI/CUIT:</td>
                            <td><?php echo htmlspecialchars($cliente['dni']); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dirección:</td>
                            <td><?php echo htmlspecialchars($cliente['direccion'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Teléfono:</td>
                            <td><?php echo htmlspecialchars($cliente['celular'] ?? '-'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="col-md-5">
                <div class="p-3 border rounded bg-white text-center h-100 d-flex flex-column justify-content-center">
                    <h6 class="text-muted text-uppercase mb-2">Saldo Total Pendiente</h6>
                    <h2
                        class="display-5 fw-bold <?php echo $saldo_total > 0.01 ? 'text-danger' : 'text-success'; ?> mb-0">
                        $<?php echo number_format($saldo_total, 2); ?></h2>
                    <?php if ($saldo_total <= 0.01): ?>
                        <small class="text-success fw-semibold">✔ Al día</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabla de Historial -->
        <h5 class="fw-bold border-bottom pb-2 mb-3">Detalle de Movimientos</h5>
        <table class="table table-striped table-bordered table-sm">
            <thead class="table-light text-center">
                <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Referencia</th>
                    <th>Debe (Venta)</th>
                    <th>Haber (Pago)</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historial)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No hay movimientos registrados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($historial as $mov):
                        $es_venta = $mov['tipo'] == 'Venta';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo date("d/m/Y H:i", strtotime($mov['fecha'])); ?></td>
                            <td><?php echo htmlspecialchars($mov['tipo']); ?></td>
                            <td class="text-center text-muted"><small>#<?php echo $mov['id_ref']; ?></small></td>

                            <!-- Columna Debe (Ventas) -->
                            <td class="text-end">
                                <?php if ($es_venta): ?>
                                    <span class="text-danger fw-bold">$<?php echo number_format($mov['debe'], 2); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <!-- Columna Haber (Pagos) -->
                            <td class="text-end">
                                <?php if (!$es_venta): ?>
                                    <span class="text-success fw-bold">$<?php echo number_format($mov['haber'], 2); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <!-- Estado / Saldo de la venta específica -->
                            <td class="text-end">
                                <?php if ($es_venta): ?>
                                    <?php if ($mov['saldo_pendiente'] > 0.01): ?>
                                        <span class="text-danger fw-bold">Pend:
                                            $<?php echo number_format($mov['saldo_pendiente'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-success fw-bold">PAGADO</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small class="text-muted">Imputado</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="mt-5 text-center text-muted small">
            <p>Fin del Reporte</p>
        </div>

    </div>

</body>

</html>