<?php
session_start();
require 'db_connect.php'; // Incluir la conexión PDO

// Verificar si se enviaron datos por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario = $_POST['nombre_usuario'];
    $password = $_POST['password'];

    // 1. Buscar al usuario en la base de datos
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ?");
    $stmt->execute([$nombre_usuario]);
    $usuario = $stmt->fetch();

    // 2. Verificar si el usuario existe y si la contraseña es correcta
    if ($usuario && password_verify($password, $usuario['password_hash'])) {
        // La contraseña es correcta. Iniciar sesión.
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
        
        // Redirigir al panel principal
        header('Location: index.php');
        exit();
    } else {
        // Credenciales incorrectas
        header('Location: login.php?error=Usuario o contraseña incorrectos');
        exit();
    }
} else {
    // Si se accede directamente al script sin POST, redirigir al login
    header('Location: login.php');
    exit();
}
?>