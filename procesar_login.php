<?php
session_start();
require 'db_connect.php'; // Incluir la conexión PDO

// Configurar cabecera para devolver JSON
header('Content-Type: application/json');

// Inicializar respuesta por defecto
$response = [
    'status' => 'error',
    'message' => 'Solicitud no válida'
];

// Verificar si se enviaron datos por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario = $_POST['nombre_usuario'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validación básica
    if (empty($nombre_usuario) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Por favor complete todos los campos']);
        exit();
    }

    try {
        // 1. Buscar al usuario en la base de datos
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ?");
        $stmt->execute([$nombre_usuario]);
        $usuario = $stmt->fetch();

        // 2. Verificar si el usuario existe y si la contraseña es correcta
        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            // La contraseña es correcta. Iniciar sesión.
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
            
            // Responder con éxito y URL de redirección
            $response = [
                'status' => 'success',
                'redirect' => 'index.php'
            ];
        } else {
            // Credenciales incorrectas
            $response = [
                'status' => 'error',
                'message' => 'Usuario o contraseña incorrectos'
            ];
        }
    } catch (PDOException $e) {
        $response = [
            'status' => 'error',
            'message' => 'Error de base de datos: ' . $e->getMessage()
        ];
    }
}

echo json_encode($response);
exit();
?>