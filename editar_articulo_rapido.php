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

if (!$id || $stock === null || $costo === null || $precio === null) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE articulos 
        SET stock_actual = ?, precio_costo = ?, precio_venta = ? 
        WHERE id = ?
    ");
    $stmt->execute([$stock, $costo, $precio, $id]);

    echo json_encode(['status' => 'success', 'message' => 'Artículo actualizado']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar: ' . $e->getMessage()]);
}
?>
