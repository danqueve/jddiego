<?php
// Incluir el archivo de conexión a la base de datos
require 'db_connect.php';

// Establecer la cabecera de respuesta como JSON
header('Content-Type: application/json');

// Obtener la acción del parámetro 'accion' (desde GET o POST)
$accion = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion'])) {
    $accion = $_GET['accion'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    // La acción para crear, editar o eliminar vendrá por POST
    $accion = $_POST['accion'];
}

try {
    switch ($accion) {
        case 'crear':
            // Acción para crear un nuevo cliente
            // LEEMOS DESDE $_POST (enviado por serialize())
            
            $sql = "INSERT INTO clientes (dni, apellido, nombre, celular, direccion) VALUES (:dni, :apellido, :nombre, :celular, :direccion)";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                ':dni' => $_POST['dni'],
                ':apellido' => $_POST['apellido'],
                ':nombre' => $_POST['nombre'],
                ':celular' => $_POST['celular'],
                ':direccion' => $_POST['direccion']
            ]);
            
            echo json_encode(['status' => 'success', 'message' => 'Cliente creado exitosamente.']);
            break;

        case 'obtener':
            // Acción para obtener los datos de un cliente específico (viene por GET)
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            $sql = "SELECT * FROM clientes WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            $cliente = $stmt->fetch();
            
            if ($cliente) {
                echo json_encode(['status' => 'success', 'data' => $cliente]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado.']);
            }
            break;

        case 'editar':
            // Acción para actualizar un cliente existente
            // LEEMOS DESDE $_POST (enviado por serialize())
            
            $sql = "UPDATE clientes SET dni = :dni, apellido = :apellido, nombre = :nombre, celular = :celular, direccion = :direccion WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                ':dni' => $_POST['dni'],
                ':apellido' => $_POST['apellido'],
                ':nombre' => $_POST['nombre'],
                ':celular' => $_POST['celular'],
                ':direccion' => $_POST['direccion'],
                ':id' => $_POST['id_cliente'] // ID del campo oculto
            ]);
            
            echo json_encode(['status' => 'success', 'message' => 'Cliente actualizado exitosamente.']);
            break;

        case 'eliminar':
            // Acción para eliminar un cliente
            // LEEMOS DESDE $_POST (enviado por el data de AJAX)
            $id = isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;
            
            $sql = "DELETE FROM clientes WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            echo json_encode(['status' => 'success', 'message' => 'Cliente eliminado exitosamente.']);
            break;

        default:
            // Acción por defecto o desconocida
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
            break;
    }
} catch (PDOException $e) {
    // Manejar errores de la base de datos
    // Error 1062 es para 'Entrada duplicada' (ej: DNI repetido)
    if ($e->errorInfo[1] == 1062) {
         echo json_encode(['status' => 'error', 'message' => 'Error: El DNI ingresado ya existe.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    // Manejar otros errores generales
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>