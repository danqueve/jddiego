<?php 
require 'header.php'; 

// 1. Obtener ID del cliente (Casteo Estricto)
$id_cliente = (int)($_GET['id'] ?? 0);
if ($id_cliente <= 0) {
    echo "<script>window.location.href='clientes.php?error=" . urlencode("Cliente no válido.") . "';</script>";
    exit();
}

// 2. Consultar datos del Cliente
$stmt_cliente = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt_cliente->execute([$id_cliente]);
$cliente = $stmt_cliente->fetch();

if (!$cliente) {
    echo "<script>window.location.href='clientes.php?error=" . urlencode("Cliente no encontrado.") . "';</script>";
    exit();
}

// Generar Token CSRF para el modal de pagos
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 3. Calcular Saldo Total Pendiente
$stmt_saldo = $pdo->prepare("
    SELECT SUM(saldo_pendiente) 
    FROM ventas 
    WHERE id_cliente = ? AND tipo_pago = 'Cuenta Corriente'
");
$stmt_saldo->execute([$id_cliente]);
$saldo_total = $stmt_saldo->fetchColumn() ?: 0;

// 4. Obtener Pagos Realizados (Historial de Pagos)
$stmt_pagos = $pdo->prepare("
    SELECT
        p.id as id_pago,
        p.id_venta as id_venta_pago,
        p.fecha,
        p.monto_pagado as haber
    FROM pagos_cta_corriente p
    JOIN ventas v ON p.id_venta = v.id
    WHERE v.id_cliente = ?
    ORDER BY p.fecha DESC
");
$stmt_pagos->execute([$id_cliente]);
$pagos_realizados = $stmt_pagos->fetchAll();

// 5. Deudas Pendientes (Consumos y Saldos a Favor de Saldo Pendiente > 0)
$stmt_pendientes = $pdo->prepare("
    SELECT id, fecha, total, saldo_pendiente 
    FROM ventas 
    WHERE id_cliente = ? AND tipo_pago = 'Cuenta Corriente' AND saldo_pendiente > 0.01
    ORDER BY fecha ASC
");
$stmt_pendientes->execute([$id_cliente]);
$deudas_pendientes = $stmt_pendientes->fetchAll();
?>

<!-- Contenido Principal -->
<div class="container mt-5 pt-5">
    
    <!-- Cabecera con Botones -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-person-vcard text-primary me-2"></i>Ficha de Cliente</h2>
        
        <div>
            <!-- BOTÓN NUEVO: Registrar Pago -->
            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#modalRegistrarPago">
                <i class="bi bi-wallet2 me-2"></i>Registrar Pago
            </button>

            <a href="ficha_cliente_print.php?id=<?php echo $id_cliente; ?>" target="_blank" class="btn btn-info me-2 text-white">
                <i class="bi bi-printer me-2"></i>Imprimir Ficha
            </a>
            
            <a href="cta_corriente.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Volver a Cta. Corriente
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Columna Izquierda: Datos del Cliente y Saldo -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 mb-4 action-card" style="transition: transform 0.2s, box-shadow 0.2s;">
                <div class="card-body text-center p-4">
                    <div class="avatar-placeholder bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                        <?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?>
                    </div>
                    <h4 class="fw-bold"><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></h4>
                    <p class="text-muted mb-1">DNI: <?php echo htmlspecialchars($cliente['dni']); ?></p>
                    <p class="text-muted mb-3"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($cliente['direccion'] ?? 'Sin dirección'); ?></p>
                    <p class="text-muted"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($cliente['celular'] ?? '-'); ?></p>
                </div>
            </div>

            <div class="card shadow-sm border-0 bg-danger-subtle action-card" style="transition: transform 0.2s, box-shadow 0.2s;">
                <div class="card-body text-center p-4">
                    <h6 class="text-danger-emphasis mb-2">DEUDA TOTAL ACTUAL</h6>
                    <h1 class="display-4 fw-bold text-danger mb-0">$<?php echo number_format($saldo_total, 2); ?></h1>
                    <small class="text-danger-emphasis">Suma de todos los saldos pendientes</small>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Tablas Desglosadas -->
        <div class="col-md-8">
            <!-- TABLA 1: Consumos Pendientes (Adeudado) -->
            <div class="card shadow-sm border-0 mb-4 border-start border-danger border-4">
                <div class="card-header bg-white border-bottom-0 py-3 d-flex align-items-center">
                    <i class="bi bi-exclamation-circle text-danger me-2 fs-5"></i>
                    <h5 class="mb-0 fw-bold text-danger-emphasis">Consumos Pendientes (Deuda Activa)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Fecha</th>
                                    <th>Comprobante</th>
                                    <th class="text-end text-muted">Total Venta</th>
                                    <th class="text-end text-danger fw-bold pe-4">Saldo Restante</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($deudas_pendientes)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-success fw-bold">
                                            <i class="bi bi-check-circle me-1"></i> El cliente no registra deudas activas.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($deudas_pendientes as $deuda): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="fw-bold d-block"><?php echo date("d/m/Y", strtotime($deuda['fecha'])); ?></span>
                                            <small class="text-muted"><?php echo date("H:i", strtotime($deuda['fecha'])); ?> hs</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark mb-1">Venta #<?php echo $deuda['id']; ?></span>
                                            <br><small><a href="remito.php?id=<?php echo $deuda['id']; ?>" target="_blank" class="text-decoration-none">Ver Remito</a></small>
                                        </td>
                                        <td class="text-end text-muted">
                                            $<?php echo number_format($deuda['total'], 2); ?>
                                        </td>
                                        <td class="text-end text-danger fw-bold pe-4">
                                            $<?php echo number_format($deuda['saldo_pendiente'], 2); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TABLA 2: Historial de Pagos -->
            <div class="card shadow-sm border-0 border-start border-success border-4">
                <div class="card-header bg-white border-bottom-0 py-3 d-flex align-items-center">
                    <i class="bi bi-receipt text-success me-2 fs-5"></i>
                    <h5 class="mb-0 fw-bold text-success-emphasis">Historial de Pagos Realizados</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Fecha de Pago</th>
                                    <th>Aplicado A</th>
                                    <th class="text-end text-success fw-bold">Monto Abonado</th>
                                    <th class="pe-4 text-center" style="width:60px;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pagos_realizados)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No se registran pagos en el historial.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pagos_realizados as $pago): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="fw-bold d-block"><?php echo date("d/m/Y", strtotime($pago['fecha'])); ?></span>
                                            <small class="text-muted"><?php echo date("H:i", strtotime($pago['fecha'])); ?> hs</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success mb-1">Pago Recibido</span>
                                            <br><small class="text-muted">Imputado a Venta #<?php echo $pago['id_venta_pago']; ?></small>
                                        </td>
                                        <td class="text-end text-success fw-bold">
                                            +$<?php echo number_format($pago['haber'], 2); ?>
                                        </td>
                                        <td class="pe-4 text-center">
                                            <button class="btn btn-sm btn-outline-danger btn-eliminar-pago"
                                                title="Revertir este pago"
                                                data-id="<?php echo $pago['id_pago']; ?>"
                                                data-monto="<?php echo number_format($pago['haber'], 2); ?>"
                                                data-id-venta="<?php echo $pago['id_venta_pago']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Registrar Pago (NUEVO para esta página) -->
<div class="modal fade" id="modalRegistrarPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-wallet2 me-2"></i>Registrar Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formRegistrarPago">
                    <input type="hidden" name="accion" value="registrar_pago">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Deuda a Pagar</label>
                        <?php if (empty($deudas_pendientes)): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>El cliente no tiene deudas pendientes.
                            </div>
                        <?php else: ?>
                            <select id="id_venta_pago" name="id_venta" class="form-select" required>
                                <option value="">-- Seleccione una venta --</option>
                                <?php foreach ($deudas_pendientes as $deuda): ?>
                                    <option value="<?php echo $deuda['id']; ?>" data-saldo="<?php echo $deuda['saldo_pendiente']; ?>">
                                        Venta #<?php echo $deuda['id']; ?> (<?php echo date("d/m/Y", strtotime($deuda['fecha'])); ?>) - Pendiente: $<?php echo number_format($deuda['saldo_pendiente'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="monto_pagado" class="form-label fw-bold">Monto a Pagar</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control fw-bold text-success" id="monto_pagado" name="monto_pagado" step="0.01" min="0.01" required <?php echo empty($deudas_pendientes) ? 'disabled' : ''; ?>>
                        </div>
                        <div id="saldoHelp" class="form-text">Seleccione una venta para ver el saldo máximo.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success px-4" form="formRegistrarPago" id="btnGuardarPago" <?php echo empty($deudas_pendientes) ? 'disabled' : ''; ?>>Confirmar Pago</button>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>

<script>
$(document).ready(function() {
    
    var saldoMaximo = 0; 
    var modalRegistrarPago = new bootstrap.Modal(document.getElementById('modalRegistrarPago'));

    // Al cambiar la venta seleccionada en el modal
    $('#id_venta_pago').change(function() {
        var selected = $(this).find('option:selected');
        var saldo = selected.data('saldo');
        
        if (saldo) {
            saldoMaximo = parseFloat(saldo);
            $('#monto_pagado').val(saldoMaximo.toFixed(2)); // Sugerir pago total
            $('#monto_pagado').attr('max', saldoMaximo.toFixed(2));
            $('#saldoHelp').text('Saldo pendiente: $' + saldoMaximo.toFixed(2));
        } else {
            saldoMaximo = 0;
            $('#monto_pagado').val('');
            $('#saldoHelp').text('Seleccione una venta para ver el saldo máximo.');
        }
    });

    // Eliminar pago
    $(document).on('click', '.btn-eliminar-pago', function () {
        const id       = $(this).data('id');
        const monto    = $(this).data('monto');
        const idVenta  = $(this).data('id-venta');
        Swal.fire({
            title: '¿Eliminar pago?',
            html: `Monto: <strong>$${monto}</strong><br><small class="text-muted">Se revertirá el saldo de la venta #${idVenta} y el movimiento de caja.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonText: 'Cancelar',
            confirmButtonText: 'Sí, eliminar'
        }).then(result => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: 'gestionar_pago_cta_corriente.php',
                type: 'POST',
                data: { accion: 'eliminar_pago', id_pago: id },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Pago eliminado', text: res.message, timer: 1800, showConfirmButton: false })
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: () => Swal.fire('Error', 'Error de conexión', 'error')
            });
        });
    });

    // Enviar formulario de pago
    $('#formRegistrarPago').submit(function(e) {
        e.preventDefault();
        var monto = parseFloat($('#monto_pagado').val());
        
        if (monto <= 0 || (saldoMaximo > 0 && monto > (saldoMaximo + 0.01))) {
            Swal.fire('Error', 'Monto inválido. No puede superar el saldo pendiente.', 'error'); 
            return;
        }
        
        $('#btnGuardarPago').prop('disabled', true).text('Procesando...');
        var formData = $(this).serialize();
        
        $.ajax({
            url: 'gestionar_pago_cta_corriente.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    modalRegistrarPago.hide();
                    Swal.fire('¡Éxito!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                    $('#btnGuardarPago').prop('disabled', false).text('Confirmar Pago');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error de conexión.', 'error');
                $('#btnGuardarPago').prop('disabled', false).text('Confirmar Pago');
            }
        });
    });
});
</script>

<style>
.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
.btn {
    transition: all 0.2s ease-in-out;
}
.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
table.table-hover tbody tr {
    transition: background-color 0.2s;
}
</style>