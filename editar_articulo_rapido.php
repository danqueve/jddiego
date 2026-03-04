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
$id = $_POST['id'] ?? null;
$stock = $_POST['stock'] ?? null;
$costo = $_POST['costo'] ?? null;
$precio = $_POST['precio'] ?? null;
$descripcion = trim($_POST['descripcion'] ?? '');

if (!$id || $stock === null || $costo === null || $precio === null || $descripcion === '') {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE articulos
        SET descripcion = ?, stock_actual = ?, precio_costo = ?, precio_venta = ?
        WHERE id = ?
    ");
    $stmt->execute([$descripcion, $stock, $costo, $precio, $id]);

    echo json_encode(['status' => 'success', 'message' => 'Artículo actualizado']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar: ' . $e->getMessage()]);
}
?>
