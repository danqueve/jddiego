<?php
require 'db_connect.php';

header('Content-Type: application/json');

$q = $_GET['q'] ?? '';

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, codigo, descripcion, stock_actual, precio_costo, precio_venta 
        FROM articulos 
        WHERE codigo LIKE ? OR descripcion LIKE ? 
        LIMIT 20
    ");
    $stmt->execute(["%$q%", "%$q%"]);
    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($articulos);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
