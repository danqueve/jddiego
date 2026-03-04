<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$accion = $_POST['accion'] ?? '';

try {
    switch ($accion) {

        case 'crear':
            $nombre   = trim($_POST['nombre_usuario'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmar = $_POST['confirmar_password'] ?? '';

            if ($nombre === '' || $password === '') {
                echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
                exit;
            }
            if ($password !== $confirmar) {
                echo json_encode(['status' => 'error', 'message' => 'Las contraseñas no coinciden']);
                exit;
            }
            if (strlen($password) < 6) {
                echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 6 caracteres']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ?");
            $stmt->execute([$nombre]);
            if ($stmt->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'El nombre de usuario ya está en uso']);
                exit;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre_usuario, password_hash, fecha_registro) VALUES (?, ?, NOW())");
            $stmt->execute([$nombre, $hash]);
            echo json_encode(['status' => 'success', 'message' => "Usuario \"$nombre\" creado exitosamente"]);
            break;

        case 'cambiar_password':
            $id        = $_POST['id'] ?? null;
            $password  = $_POST['password'] ?? '';
            $confirmar = $_POST['confirmar_password'] ?? '';

            if (!$id || $password === '') {
                echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
                exit;
            }
            if ($password !== $confirmar) {
                echo json_encode(['status' => 'error', 'message' => 'Las contraseñas no coinciden']);
                exit;
            }
            if (strlen($password) < 6) {
                echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 6 caracteres']);
                exit;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $id]);
            echo json_encode(['status' => 'success', 'message' => 'Contraseña actualizada']);
            break;

        case 'eliminar':
            $id = $_POST['id'] ?? null;

            if (!$id) {
                echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
                exit;
            }
            if ($id == $_SESSION['usuario_id']) {
                echo json_encode(['status' => 'error', 'message' => 'No podés eliminar tu propia cuenta']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Usuario eliminado']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>
