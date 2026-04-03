<?php
// 1. Iniciar Sesión y Conectar a la BD
session_start();
require 'db_connect.php';

// 2. Seguridad: Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// 3. Obtener ID del cliente (cast seguro a entero)
$id_cliente = (int) ($_GET['id'] ?? 0);
if ($id_cliente <= 0) {
    header('Location: clientes.php?error=' . urlencode('ID de cliente no válido.'));
    exit();
}

try {
    // 4. Consultar datos del Cliente
    $stmt_cliente = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt_cliente->execute([$id_cliente]);
    $cliente = $stmt_cliente->fetch();

    if (!$cliente) {
        header('Location: clientes.php?error=' . urlencode('Cliente no encontrado para impresión.'));
        exit();
    }

    // 5. Calcular Saldo Total Pendiente
    $stmt_saldo = $pdo->prepare("
        SELECT SUM(saldo_pendiente) 
        FROM ventas 
        WHERE id_cliente = ? AND tipo_pago = 'Cuenta Corriente'
    ");
    $stmt_saldo->execute([$id_cliente]);
    $saldo_total = $stmt_saldo->fetchColumn() ?: 0;

    // 6. Obtener Pagos Realizados
    $stmt_pagos = $pdo->prepare("
        SELECT
            p.fecha,
            p.monto_pagado as haber,
            p.id_venta as id_venta_pago
        FROM pagos_cta_corriente p
        JOIN ventas v ON p.id_venta = v.id
        WHERE v.id_cliente = ?
        ORDER BY p.fecha DESC
    ");
    $stmt_pagos->execute([$id_cliente]);
    $pagos_realizados = $stmt_pagos->fetchAll();

    // 7. Obtener Deuda Activa (Consumos)
    $stmt_pendientes = $pdo->prepare("
        SELECT id, fecha, total, saldo_pendiente 
        FROM ventas 
        WHERE id_cliente = ? AND tipo_pago = 'Cuenta Corriente' AND saldo_pendiente > 0.01
        ORDER BY fecha ASC
    ");
    $stmt_pendientes->execute([$id_cliente]);
    $deudas_pendientes = $stmt_pendientes->fetchAll();

} catch (PDOException $e) {
    error_log("Error al generar el reporte PDF/Impresión (ID Cliente: $id_cliente): " . $e->getMessage());
    header('Location: clientes.php?error=' . urlencode('Ocurrió un error al cargar los datos del reporte.'));
    exit();
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

        <!-- Sección 1: Consumos Pendientes -->
        <h5 class="fw-bold border-bottom pb-2 mb-3 mt-4 text-danger-emphasis">Consumos Pendientes (Deuda Activa)</h5>
        <table class="table table-striped table-bordered table-sm mb-4">
            <thead class="table-light text-center">
                <tr>
                    <th style="width: 20%;">Fecha</th>
                    <th style="width: 35%;">Comprobante</th>
                    <th style="width: 20%;">Total Original</th>
                    <th style="width: 25%;">Saldo Restante</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deudas_pendientes)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-4 text-success fw-bold">El cliente no registra deudas activas.</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $suma_restante = 0;
                    foreach ($deudas_pendientes as $deuda): 
                        $suma_restante += $deuda['saldo_pendiente'];
                    ?>
                        <tr>
                            <td class="text-center"><?php echo date("d/m/Y H:i", strtotime($deuda['fecha'])); ?></td>
                            <td>Venta #<?php echo $deuda['id']; ?></td>
                            <td class="text-end text-muted">$<?php echo number_format($deuda['total'], 2); ?></td>
                            <td class="text-end text-danger fw-bold">$<?php echo number_format($deuda['saldo_pendiente'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-light fw-bold">
                        <td colspan="3" class="text-end">TOTAL DEUDA ACTIVA:</td>
                        <td class="text-end text-danger fs-6">$<?php echo number_format($suma_restante, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Sección 2: Historial de Pagos -->
        <h5 class="fw-bold border-bottom pb-2 mb-3 mt-5 text-success-emphasis">Historial de Pagos Realizados</h5>
        <table class="table table-striped table-bordered table-sm mb-4">
            <thead class="table-light text-center">
                <tr>
                    <th style="width: 25%;">Fecha de Pago</th>
                    <th style="width: 50%;">Aplicado A</th>
                    <th style="width: 25%;">Monto Abonado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pagos_realizados)): ?>
                    <tr>
                        <td colspan="3" class="text-center py-4 text-muted">No se registran pagos en el historial.</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $suma_pagos = 0;
                    foreach ($pagos_realizados as $pago): 
                        $suma_pagos += $pago['haber'];
                    ?>
                        <tr>
                            <td class="text-center"><?php echo date("d/m/Y H:i", strtotime($pago['fecha'])); ?></td>
                            <td>Pago Imputado a Venta #<?php echo $pago['id_venta_pago']; ?></td>
                            <td class="text-end text-success fw-bold">+$<?php echo number_format($pago['haber'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-light fw-bold">
                        <td colspan="2" class="text-end text-muted">Total Abonado en el Historial:</td>
                        <td class="text-end text-success">$<?php echo number_format($suma_pagos, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="mt-5 text-center text-muted small">
            <p>Fin del Reporte</p>
        </div>

    </div>

</body>

</html>