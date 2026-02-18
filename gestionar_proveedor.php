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

// Determinar la acción (GET para 'obtener', POST para C/U/D)
$accion = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion'])) {
    $accion = $_GET['accion'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
}

try {
    switch ($accion) {
        // --- ACCIÓN: CREAR ---
        case 'crear':
            $stmt = $pdo->prepare("INSERT INTO proveedores (nombre_proveedor, contacto, telefono, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['nombre_proveedor'],
                $_POST['contacto'],
                $_POST['telefono'],
                $_POST['email']
            ]);
            $response = ['status' => 'success', 'message' => 'Proveedor creado exitosamente'];
            break;

        // --- ACCIÓN: OBTENER (para editar) ---
        case 'obtener':
            $id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
            $stmt->execute([$id]);
            $proveedor = $stmt->fetch();
            
            if ($proveedor) {
                $response = ['status' => 'success', 'data' => $proveedor];
            } else {
                $response['message'] = 'Proveedor no encontrado';
            }
            break;

        // --- ACCIÓN: EDITAR (Actualizar) ---
        case 'editar':
            $stmt = $pdo->prepare("UPDATE proveedores SET nombre_proveedor = ?, contacto = ?, telefono = ?, email = ? WHERE id = ?");
            $stmt->execute([
                $_POST['nombre_proveedor'],
                $_POST['contacto'],
                $_POST['telefono'],
                $_POST['email'],
                $_POST['id_proveedor'] // El ID oculto del formulario
            ]);
            $response = ['status' => 'success', 'message' => 'Proveedor actualizado exitosamente'];
            break;

        // --- ACCIÓN: ELIMINAR ---
        case 'eliminar':
            $id = $_POST['id_proveedor'];
            
            // NOTA: La BDD está configurada con ON DELETE SET NULL
            // para la tabla 'articulos'. Esto significa que si eliminas
            // un proveedor, los artículos asociados a él no se borrarán,
            // sino que su 'id_proveedor' se pondrá en NULL.
            
            $stmt = $pdo->prepare("DELETE FROM proveedores WHERE id = ?");
            $stmt->execute([$id]);
            $response = ['status' => 'success', 'message' => 'Proveedor eliminado exitosamente'];
            break;

        default:
            $response['message'] = 'Acción desconocida: ' . $accion;
            break;
    }
} catch (PDOException $e) {
    // Manejo de errores de base de datos
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

// Devolver la respuesta en formato JSON
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>