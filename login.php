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
    <title>Login - Sistema de Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #212529; /* Fondo oscuro base */
        }
        .login-card {
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="card login-card shadow-lg">
        <div class="card-body p-4 p-md-5">
            <h3 class="card-title text-center mb-4">Iniciar Sesión</h3>
            
            <form id="formLogin">
                <div class="mb-3">
                    <label for="nombre_usuario" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary" id="btnLogin">Ingresar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- jQuery & Bootstrap -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    $(document).ready(function() {
        $('#formLogin').submit(function(e) {
            e.preventDefault(); // Evitar envío tradicional

            // Deshabilitar botón para evitar doble clic
            var btn = $('#btnLogin');
            var originalText = btn.text();
            btn.prop('disabled', true).text('Verificando...');

            $.ajax({
                url: 'procesar_login.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Bienvenido!',
                            text: 'Redirigiendo al sistema...',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = response.redirect;
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de acceso',
                            text: response.message
                        });
                        btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error del Servidor',
                        text: 'No se pudo conectar con el servidor.'
                    });
                    btn.prop('disabled', false).text(originalText);
                }
            });
        });
    });
    </script>
</body>
</html>