<?php
require 'db_connect.php'; // Incluir la conexión PDO

// Verificar si se enviaron datos por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Recoger datos del formulario
    $nombre_usuario = $_POST['nombre_usuario'];
    $password = $_POST['password'];
    $confirmar_password = $_POST['confirmar_password'];

    // 2. Validar datos
    if (empty($nombre_usuario) || empty($password) || empty($confirmar_password)) {
        header('Location: registro.php?error=Todos los campos son obligatorios');
        exit();
    }

    if ($password !== $confirmar_password) {
        header('Location: registro.php?error=Las contraseñas no coinciden');
        exit();
    }

    // 3. Verificar si el usuario ya existe
    try {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ?");
        $stmt->execute([$nombre_usuario]);
        $usuario_existente = $stmt->fetch();

        if ($usuario_existente) {
            header('Location: registro.php?error=El nombre de usuario ya está en uso');
            exit();
        }

        // 4. Hashear la contraseña (¡MUY IMPORTANTE!)
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // 5. Insertar el nuevo usuario en la base de datos
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre_usuario, password_hash, fecha_registro) VALUES (?, ?, NOW())");
        $stmt->execute([$nombre_usuario, $password_hash]);

        // 6. Redirigir al login con mensaje de éxito
        header('Location: login.php?success=Usuario registrado exitosamente. Ya puedes iniciar sesión.');
        exit();

    } catch (PDOException $e) {
        // Manejar errores de base de datos
        header('Location: registro.php?error=Error en la base de datos: ' . $e->getMessage());
        exit();
    }

} else {
    // Si se accede directamente al script sin POST, redirigir al registro
    header('Location: registro.php');
    exit();
}
?>