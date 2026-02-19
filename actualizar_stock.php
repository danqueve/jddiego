<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// Obtener datos
$id_articulo = $_POST['id_articulo'] ?? null;
$cantidad = $_POST['cantidad'] ?? null;
$nuevo_costo = $_POST['nuevo_costo'] ?? null;

if (!$id_articulo || !$cantidad || $cantidad <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Obtener stock actual
    $stmt = $pdo->prepare("SELECT stock_actual, precio_costo FROM articulos WHERE id = ?");
    $stmt->execute([$id_articulo]);
    $articulo = $stmt->fetch();

    if (!$articulo) {
        throw new Exception("Artículo no encontrado");
    }

    // 2. Actualizar Stock
    $sql_update = "UPDATE articulos SET stock_actual = stock_actual + ?";
    $params = [$cantidad];

    // Si se envió un costo y es diferente o mayor a 0, actualizarlo
    if ($nuevo_costo !== null && $nuevo_costo >= 0) {
        $sql_update .= ", precio_costo = ?";
        $params[] = $nuevo_costo;
    }

    $sql_update .= " WHERE id = ?";
    $params[] = $id_articulo;

    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute($params);

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Stock actualizado correctamente']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar: ' . $e->getMessage()]);
}
?>
