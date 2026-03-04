<?php
// Iniciar sesión y conectar a la BD
session_start();
require 'db_connect.php';

// Configurar cabecera para devolver JSON siempre
header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

$response = ['status' => 'error', 'message' => 'Error inesperado'];
$id_usuario_actual = $_SESSION['usuario_id'];

// --- 1. LECTURA ROBUSTA DE DATOS ---
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

if (is_null($data)) {
    $data = $_REQUEST;
}

$accion = $data['accion'] ?? '';

if (empty($accion)) {
    echo json_encode(['status' => 'error', 'message' => 'El servidor no recibió una acción válida.']);
    exit();
}

try {
    switch ($accion) {

        // --- ACCIÓN: BUSCAR ARTÍCULO ---
        case 'buscar_articulo':
            $query = $data['query'] ?? '';
            $stmt = $pdo->prepare("
                SELECT id, codigo, descripcion, precio_venta, stock_actual 
                FROM articulos 
                WHERE (codigo LIKE ? OR descripcion LIKE ?) AND stock_actual > 0
                LIMIT 10
            ");
            $stmt->execute(['%' . $query . '%', '%' . $query . '%']);
            $articulos = $stmt->fetchAll();
            $response = ['status' => 'success', 'data' => $articulos];
            break;

        // --- ACCIÓN: OBTENER VENTA ---
        case 'obtener_venta':
            $id = (int) ($data['id'] ?? 0);
            if ($id <= 0) {
                $response['message'] = 'ID inválido.';
                break;
            }

            $stmt = $pdo->prepare("SELECT * FROM ventas WHERE id = ?");
            $stmt->execute([$id]);
            $venta = $stmt->fetch();

            if (!$venta) {
                $response['message'] = 'Venta no encontrada';
                break;
            }

            $stmt_det = $pdo->prepare("
                SELECT 
                    dv.id_articulo AS id,
                    a.codigo,
                    a.descripcion,
                    dv.cantidad,
                    dv.precio_unitario_venta AS precio,
                    a.stock_actual
                FROM detalle_venta dv
                JOIN articulos a ON dv.id_articulo = a.id
                WHERE dv.id_venta = ?
            ");
            $stmt_det->execute([$id]);
            $detalles = $stmt_det->fetchAll();

            foreach ($detalles as &$item) {
                $item['stock_max'] = $item['stock_actual'] + $item['cantidad'];
            }

            $response = [
                'status' => 'success',
                'data' => [
                    'venta' => $venta,
                    'detalles' => $detalles
                ]
            ];
            break;

        // --- ACCIÓN: ELIMINAR VENTA (¡NUEVO!) ---
        case 'eliminar_venta':
            $id_venta = (int) ($data['id'] ?? 0);

            if ($id_venta <= 0) {
                throw new Exception("ID de venta no válido.");
            }

            $pdo->beginTransaction();

            // 1. RECUPERAR ARTÍCULOS PARA DEVOLVER STOCK
            $stmt_items = $pdo->prepare("SELECT id_articulo, cantidad FROM detalle_venta WHERE id_venta = ?");
            $stmt_items->execute([$id_venta]);
            $items = $stmt_items->fetchAll();

            // 2. DEVOLVER EL STOCK AL INVENTARIO
            $stmt_restock = $pdo->prepare("UPDATE articulos SET stock_actual = stock_actual + ? WHERE id = ?");
            foreach ($items as $item) {
                $stmt_restock->execute([$item['cantidad'], $item['id_articulo']]);
            }

            // 3. ELIMINAR MOVIMIENTO DE CAJA (Si hubo ingreso)
            $stmt_del_caja = $pdo->prepare("DELETE FROM movimientos_caja WHERE id_venta_relacionada = ?");
            $stmt_del_caja->execute([$id_venta]);

            // 4. ELIMINAR LA VENTA
            // (Por la configuración de la BD, esto eliminará en cascada el detalle_venta y pagos_cta_corriente)
            $stmt_del_venta = $pdo->prepare("DELETE FROM ventas WHERE id = ?");
            $stmt_del_venta->execute([$id_venta]);

            $pdo->commit();
            $response = ['status' => 'success', 'message' => 'Venta eliminada. El stock ha sido devuelto y la caja ajustada.'];
            break;


        // --- ACCIÓN: EDITAR VENTA ---
        case 'editar_venta':
            $id_venta = (int) ($data['id_venta'] ?? 0);
            if ($id_venta <= 0)
                throw new Exception('ID de venta inválido.');
            $id_cliente = $data['id_cliente'];
            $tipo_pago = $data['tipo_pago'];
            $total_venta = $data['total'];
            $descuento_porcentaje = isset($data['descuento']) ? floatval($data['descuento']) : 0;
            $carrito = $data['carrito'] ?? [];

            if (empty($carrito))
                throw new Exception('El carrito no puede estar vacío.');

            $pdo->beginTransaction();

            // Revertir stock anterior
            $stmt_old = $pdo->prepare("SELECT id_articulo, cantidad FROM detalle_venta WHERE id_venta = ?");
            $stmt_old->execute([$id_venta]);
            $items_anteriores = $stmt_old->fetchAll();
            $stmt_ret = $pdo->prepare("UPDATE articulos SET stock_actual = stock_actual + ? WHERE id = ?");
            foreach ($items_anteriores as $old)
                $stmt_ret->execute([$old['cantidad'], $old['id_articulo']]);

            // Borrar detalle anterior
            $pdo->prepare("DELETE FROM detalle_venta WHERE id_venta = ?")->execute([$id_venta]);

            // Nuevo detalle
            $stmt_det = $pdo->prepare("INSERT INTO detalle_venta (id_venta, id_articulo, cantidad, precio_unitario, precio_unitario_venta) VALUES (?, ?, ?, ?, ?)");
            $stmt_take = $pdo->prepare("UPDATE articulos SET stock_actual = stock_actual - ? WHERE id = ? AND stock_actual >= ?");

            foreach ($carrito as $item) {
                $stmt_det->execute([$id_venta, $item['id'], $item['cantidad'], $item['precio'], $item['precio']]);
                $stmt_take->execute([$item['cantidad'], $item['id'], $item['cantidad']]);
                if ($stmt_take->rowCount() === 0)
                    throw new Exception('Stock insuficiente para: ' . $item['descripcion']);
            }

            // Actualizar cabecera
            $saldo_pendiente = ($tipo_pago == 'Cuenta Corriente') ? $total_venta : 0;
            $stmt_upd = $pdo->prepare("UPDATE ventas SET id_cliente = ?, tipo_pago = ?, total = ?, saldo_pendiente = ?, descuento_porcentaje = ? WHERE id = ?");
            $stmt_upd->execute([$id_cliente, $tipo_pago, $total_venta, $saldo_pendiente, $descuento_porcentaje, $id_venta]);

            // Corregir Caja
            $pdo->prepare("DELETE FROM movimientos_caja WHERE id_venta_relacionada = ?")->execute([$id_venta]);
            if ($tipo_pago != 'Cuenta Corriente') {
                $pdo->prepare("INSERT INTO movimientos_caja (id_usuario, tipo_movimiento, monto, descripcion, id_venta_relacionada, fecha) VALUES (?, 'Ingreso', ?, ?, ?, NOW())")
                    ->execute([$id_usuario_actual, $total_venta, "Ingreso por Venta ID: " . $id_venta . " (Editada)", $id_venta]);
            }

            $pdo->commit();
            $response = ['status' => 'success', 'message' => 'Venta modificada exitosamente.'];
            break;

        // --- ACCIÓN: PROCESAR VENTA NUEVA ---
        case 'procesar_venta':
            $id_cliente = $data['id_cliente'];
            $tipo_pago = $data['tipo_pago'];
            $total_venta = $data['total'];
            $descuento_porcentaje = isset($data['descuento']) ? floatval($data['descuento']) : 0;
            $carrito = $data['carrito'] ?? [];
            if (is_string($carrito))
                $carrito = json_decode($carrito, true);

            if (empty($carrito))
                throw new Exception('El carrito está vacío.');

            $pdo->beginTransaction();
            $saldo_pendiente = ($tipo_pago == 'Cuenta Corriente') ? $total_venta : 0;

            $stmt_venta = $pdo->prepare("INSERT INTO ventas (id_usuario, id_cliente, tipo_pago, saldo_pendiente, total, descuento_porcentaje, fecha) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt_venta->execute([$id_usuario_actual, $id_cliente, $tipo_pago, $saldo_pendiente, $total_venta, $descuento_porcentaje]);
            $id_venta_nueva = $pdo->lastInsertId();

            $stmt_det = $pdo->prepare("INSERT INTO detalle_venta (id_venta, id_articulo, cantidad, precio_unitario, precio_unitario_venta) VALUES (?, ?, ?, ?, ?)");
            $stmt_upd_stk = $pdo->prepare("UPDATE articulos SET stock_actual = stock_actual - ? WHERE id = ? AND stock_actual >= ?");

            foreach ($carrito as $item) {
                $stmt_det->execute([$id_venta_nueva, $item['id'], $item['cantidad'], $item['precio'], $item['precio']]);
                $stmt_upd_stk->execute([$item['cantidad'], $item['id'], $item['cantidad']]);
                if ($stmt_upd_stk->rowCount() === 0)
                    throw new Exception('Stock insuficiente: ' . $item['descripcion']);
            }

            if ($tipo_pago != 'Cuenta Corriente') {
                $pdo->prepare("INSERT INTO movimientos_caja (id_usuario, tipo_movimiento, monto, descripcion, id_venta_relacionada) VALUES (?, 'Ingreso', ?, ?, ?)")
                    ->execute([$id_usuario_actual, $total_venta, "Ingreso por Venta ID: " . $id_venta_nueva, $id_venta_nueva]);
            }

            $pdo->commit();
            $response = ['status' => 'success', 'message' => 'Venta registrada exitosamente (ID: ' . $id_venta_nueva . ')', 'id_venta' => (int) $id_venta_nueva];
            break;

        default:
            $response['message'] = 'Acción desconocida: [' . htmlspecialchars($accion) . ']';
            break;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error en la operación: ' . $e->getMessage();
}

echo json_encode($response);
exit();
?>