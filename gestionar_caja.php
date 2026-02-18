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

// Determinar la acción (solo POST)
$accion = $_POST['accion'] ?? '';

try {
    switch ($accion) {
        
        // --- ACCIÓN: INGRESO MANUAL ---
        case 'ingreso_manual':
            $stmt = $pdo->prepare("
                INSERT INTO movimientos_caja (id_usuario, tipo_movimiento, monto, descripcion, fecha)
                VALUES (?, 'Ingreso', ?, ?, NOW())
            ");
            $stmt->execute([
                $id_usuario_actual,
                $_POST['monto'],
                $_POST['descripcion']
            ]);
            $response = ['status' => 'success', 'message' => 'Ingreso manual registrado'];
            break;

        // --- ACCIÓN: EGRESO MANUAL ---
        case 'egreso_manual':
            $stmt = $pdo->prepare("
                INSERT INTO movimientos_caja (id_usuario, tipo_movimiento, monto, descripcion, fecha)
                VALUES (?, 'Egreso', ?, ?, NOW())
            ");
            $stmt->execute([
                $id_usuario_actual,
                $_POST['monto'],
                $_POST['descripcion']
            ]);
            $response = ['status' => 'success', 'message' => 'Egreso manual registrado'];
            break;

        default:
            $response['message'] = 'Acción desconocida: ' . $accion;
            break;
    }
} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

// Devolver la respuesta en formato JSON
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>