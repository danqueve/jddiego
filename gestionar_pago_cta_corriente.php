<?php
// Iniciar sesión y conectar a la BD
session_start();
require 'db_connect.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

// Inicializar respuesta
$response = ['status' => 'error', 'message' => 'Acción no válida'];
$id_usuario_actual = $_SESSION['usuario_id'];

// Solo aceptamos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit();
}

$accion = $_POST['accion'] ?? 'registrar_pago';

// ── ELIMINAR PAGO ──────────────────────────────────────────
if ($accion === 'eliminar_pago') {
    $id_pago = $_POST['id_pago'] ?? 0;
    if ($id_pago <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID de pago inválido.']);
        exit();
    }
    try {
        $pdo->beginTransaction();

        // 1. Obtener el pago
        $stmt = $pdo->prepare("SELECT id_venta, monto_pagado FROM pagos_cta_corriente WHERE id = ?");
        $stmt->execute([$id_pago]);
        $pago = $stmt->fetch();

        if (!$pago) {
            throw new Exception("El pago no existe.");
        }

        // 2. Restaurar saldo_pendiente en la venta
        $stmt = $pdo->prepare("UPDATE ventas SET saldo_pendiente = saldo_pendiente + ? WHERE id = ?");
        $stmt->execute([$pago['monto_pagado'], $pago['id_venta']]);

        // 3. Eliminar el movimiento de caja asociado (el más reciente que coincida)
        $stmt = $pdo->prepare("
            DELETE FROM movimientos_caja
            WHERE tipo_movimiento = 'Ingreso'
              AND id_venta_relacionada = ?
              AND monto = ?
              AND descripcion LIKE 'Pago Cta. Corriente%'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$pago['id_venta'], $pago['monto_pagado']]);

        // 4. Eliminar el pago
        $stmt = $pdo->prepare("DELETE FROM pagos_cta_corriente WHERE id = ?");
        $stmt->execute([$id_pago]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Pago eliminado y saldo restaurado correctamente.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ── REGISTRAR PAGO ─────────────────────────────────────────
// Recoger datos
$id_venta = $_POST['id_venta'] ?? 0;
$monto_pagado = $_POST['monto_pagado'] ?? 0;

if ($monto_pagado <= 0 || $id_venta <= 0) {
    $response['message'] = 'Datos inválidos.';
    echo json_encode($response);
    exit();
}

try {
    // Iniciar Transacción (¡MUY IMPORTANTE!)
    $pdo->beginTransaction();

    // 1. Obtener y bloquear la venta para evitar concurrencia
    $stmt_venta = $pdo->prepare("SELECT saldo_pendiente, id_cliente FROM ventas WHERE id = ? FOR UPDATE");
    $stmt_venta->execute([$id_venta]);
    $venta = $stmt_venta->fetch();

    if (!$venta) {
        throw new Exception("La venta no fue encontrada.");
    }

    $saldo_actual = $venta['saldo_pendiente'];

    // 2. Validar que el pago no sea mayor al saldo
    // (Usamos un pequeño margen de 0.001 para errores de precisión decimal)
    if ($monto_pagado > ($saldo_actual + 0.001)) {
        throw new Exception("El monto a pagar ($monto_pagado) es mayor que el saldo pendiente ($saldo_actual).");
    }

    // 3. Registrar el pago en la nueva tabla 'pagos_cta_corriente'
    $stmt_pago = $pdo->prepare("
        INSERT INTO pagos_cta_corriente (id_venta, id_usuario, monto_pagado, fecha, descripcion)
        VALUES (?, ?, ?, NOW(), ?)
    ");
    $stmt_pago->execute([$id_venta, $id_usuario_actual, $monto_pagado, "Pago Cta. Corriente (Venta ID: $id_venta)"]);

    // 4. Actualizar el saldo_pendiente en la tabla 'ventas'
    $nuevo_saldo = $saldo_actual - $monto_pagado;
    $stmt_update_venta = $pdo->prepare("
        UPDATE ventas SET saldo_pendiente = ? WHERE id = ?
    ");
    $stmt_update_venta->execute([$nuevo_saldo, $id_venta]);

    // 5. Registrar el INGRESO en la CAJA
    $stmt_caja = $pdo->prepare("
        INSERT INTO movimientos_caja (id_usuario, tipo_movimiento, monto, descripcion, id_venta_relacionada)
        VALUES (?, 'Ingreso', ?, ?, ?)
    ");
    $descripcion_caja = "Pago Cta. Corriente (Venta ID: $id_venta)";
    $stmt_caja->execute([$id_usuario_actual, $monto_pagado, $descripcion_caja, $id_venta]);

    // 6. Confirmar la transacción
    $pdo->commit();
    $response = ['status' => 'success', 'message' => 'Pago registrado exitosamente. El saldo ha sido actualizado.'];

} catch (Exception $e) {
    // Si algo falla, deshacer todo
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error en la operación: ' . $e->getMessage();
}

// Devolver la respuesta en formato JSON
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>