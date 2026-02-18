<?php
session_start();
// Si el usuario ya está logueado, redirigir al index.
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #212529;
        }
        .register-card {
            width: 100%;
            max-width: 450px;
        }
    </style>
</head>
<body>
    <div class="card register-card shadow-lg">
        <div class="card-body p-4 p-md-5">
            <h3 class="card-title text-center mb-4">Crear Nuevo Usuario</h3>
            
            <!-- Mostrar mensajes de error (si los hay) -->
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Mostrar mensajes de éxito (si los hay) -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>

            <form action="procesar_registro.php" method="POST">
                <div class="mb-3">
                    <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
                    <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirmar_password" class="form-label">Confirmar Contraseña</label>
                    <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Registrar Usuario</button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                <a href="login.php">¿Ya tienes cuenta? Iniciar Sesión</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>