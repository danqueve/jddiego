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

// Determinar la acción
$accion = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion'])) {
    $accion = $_GET['accion'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
}

try {
    switch ($accion) {
        
        // --- ACCIÓN: BUSCAR ARTÍCULO (para el POS) ---
        case 'buscar_articulo':
            $query = $_GET['query'];
            // Buscamos artículos (no importa el stock)
            $stmt = $pdo->prepare("
                SELECT id, codigo, descripcion, precio_costo
                FROM articulos 
                WHERE (codigo LIKE ? OR descripcion LIKE ?)
                LIMIT 10
            ");
            $stmt->execute(['%' . $query . '%', '%' . $query . '%']);
            $articulos = $stmt->fetchAll();
            
            $response = ['status' => 'success', 'data' => $articulos];
            break;

        // --- ACCIÓN: PROCESAR COMPRA (Transacción) ---
        case 'procesar_compra':
            $id_proveedor = $_POST['id_proveedor'];
            $total_compra = $_POST['total'];
            $carrito = json_decode($_POST['carrito'], true); 

            if (empty($carrito)) {
                $response['message'] = 'El detalle de compra está vacío.';
                break;
            }

            // Iniciar Transacción
            $pdo->beginTransaction();

            // 1. Insertar la cabecera de la Compra
            $stmt_compra = $pdo->prepare("
                INSERT INTO compras (id_usuario, id_proveedor, total, fecha) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt_compra->execute([$id_usuario_actual, $id_proveedor, $total_compra]);
            
            $id_compra_nueva = $pdo->lastInsertId();

            // 2. Insertar Detalle de Compra y Actualizar Stock/Costo
            $stmt_detalle = $pdo->prepare("
                INSERT INTO detalle_compra (id_compra, id_articulo, cantidad, precio_unitario_costo) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt_update_stock = $pdo->prepare("
                UPDATE articulos 
                SET 
                    stock_actual = stock_actual + ?, 
                    precio_costo = ? 
                WHERE id = ?
            ");

            foreach ($carrito as $item) {
                // Insertar en detalle_compra
                $stmt_detalle->execute([
                    $id_compra_nueva,
                    $item['id'],
                    $item['cantidad'],
                    $item['precio_costo'] // El costo actualizado desde el JS
                ]);
                
                // Actualizar el stock (sumar) y el precio de costo
                $stmt_update_stock->execute([
                    $item['cantidad'],
                    $item['precio_costo'], // Actualizamos el costo al MÁS RECIENTE
                    $item['id']
                ]);
            }

            // 3. Registrar en Caja (Egreso)
            $stmt_caja = $pdo->prepare("
                INSERT INTO movimientos_caja (id_usuario, tipo_movimiento, monto, descripcion, id_compra_relacionada)
                VALUES (?, 'Egreso', ?, ?, ?)
            ");
            $descripcion_caja = "Egreso por Compra ID: " . $id_compra_nueva;
            // Guardamos el monto en positivo, el tipo 'Egreso' indica la resta
            $stmt_caja->execute([$id_usuario_actual, $total_compra, $descripcion_caja, $id_compra_nueva]);
            

            // 5. Confirmar la transacción
            $pdo->commit();
            $response = ['status' => 'success', 'message' => 'Compra registrada exitosamente (ID: ' . $id_compra_nueva . ')'];
            
            break;
        
        default:
            $response['message'] = 'Acción desconocida: ' . $accion;
            break;
    }
} catch (Exception $e) {
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