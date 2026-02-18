<?php
// Iniciar sesión y conectar a la BD
session_start();
require 'db_connect.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    // Si no está autorizado, devolvemos un error JSON
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

// Inicializar la respuesta
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
            // Convertir el id_proveedor vacío a NULL si es necesario
            $id_proveedor = !empty($_POST['id_proveedor']) ? $_POST['id_proveedor'] : null;

            $stmt = $pdo->prepare("
                INSERT INTO articulos (codigo, descripcion, stock_actual, precio_costo, precio_venta, id_proveedor) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['codigo'],
                $_POST['descripcion'],
                $_POST['stock_actual'],
                $_POST['precio_costo'],
                $_POST['precio_venta'],
                $id_proveedor // Usamos la variable saneada
            ]);
            $response = ['status' => 'success', 'message' => 'Artículo creado exitosamente'];
            break;

        // --- ACCIÓN: OBTENER (para el modal de editar) ---
        case 'obtener':
            $id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM articulos WHERE id = ?");
            $stmt->execute([$id]);
            $articulo = $stmt->fetch();
            
            if ($articulo) {
                $response = ['status' => 'success', 'data' => $articulo];
            } else {
                $response['message'] = 'Artículo no encontrado';
            }
            break;

        // --- ACCIÓN: EDITAR (Actualizar) ---
        case 'editar':
            // Convertir el id_proveedor vacío a NULL
            $id_proveedor = !empty($_POST['id_proveedor']) ? $_POST['id_proveedor'] : null;

            $stmt = $pdo->prepare("
                UPDATE articulos 
                SET codigo = ?, descripcion = ?, stock_actual = ?, precio_costo = ?, precio_venta = ?, id_proveedor = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['codigo'],
                $_POST['descripcion'],
                $_POST['stock_actual'],
                $_POST['precio_costo'],
                $_POST['precio_venta'],
                $id_proveedor, // Usamos la variable saneada
                $_POST['id_articulo'] // El ID oculto del formulario
            ]);
            $response = ['status' => 'success', 'message' => 'Artículo actualizado exitosamente'];
            break;

        // --- ACCIÓN: ELIMINAR ---
        case 'eliminar':
            $id = $_POST['id_articulo'];
            $stmt = $pdo->prepare("DELETE FROM articulos WHERE id = ?");
            $stmt->execute([$id]);
            $response = ['status' => 'success', 'message' => 'Artículo eliminado exitosamente'];
            break;

        default:
            $response['message'] = 'Acción desconocida: ' . $accion;
            break;
    }
} catch (PDOException $e) {
    // Manejo de errores de base de datos
    // Comprobamos si es un error de código duplicado (error code 1062)
    if ($e->errorInfo[1] == 1062) {
        $response['message'] = 'Error: El código de artículo ya existe.';
    } else {
        $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    }
}

// Devolver la respuesta en formato JSON
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>