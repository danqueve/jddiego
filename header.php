<?php
// 1. Iniciar Sesión y Conectar a la BD
session_start();
require 'db_connect.php';

// 2. Seguridad: Si no está logueado, redirigir al login
// (Excepto si ya estamos en una página de login/registro)
$pagina_login = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['usuario_id']) && $pagina_login != 'login.php' && $pagina_login != 'procesar_login.php' && $pagina_login != 'registro.php' && $pagina_login != 'procesar_registro.php') {
    header('Location: login.php');
    exit();
}

// 3. Para saber qué página está activa en el menú
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Ventas</title>
    <link rel="icon" type="image/png" href="img/logo_1.png">

    <!-- 1. Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- 2. Bootstrap Icons (Para los íconos) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- 3. DataTables CSS (Para las tablas) -->
    <link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.css" rel="stylesheet">

    <!-- 4. SweetAlert2 (Para notificaciones bonitas) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- 5. Google Fonts (Fuente más limpia) -->
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- 5. Estilos CSS Personalizados -->
    <style>
        /* --- Estética Limpia (Top Nav) --- */
        body {
            font-family: 'Source Sans Pro', sans-serif;
            
            /* -- Nuevo fondo verde pastel (Solicitado) -- */
            background-color: rgba(0, 128, 0, 0.3); /* Color solicitado por el usuario */
            min-height: 100vh; /* Asegura que el fondo cubra toda la altura */
        }
        
        /* 1. Estilo de las tarjetas */
        .card {
            border: none; /* Quitamos el borde por defecto */
        }
        
        /* 2. Estilo de la Navbar */
        .navbar {
            border-bottom: 1px solid #e9ecef; /* Línea sutil de separación */
        }
        
        /* 3. Estilo para DataTables */
        .dataTables_wrapper .row {
            padding: 0.5rem 0; /* Espaciado más limpio en DataTables */
        }

        /* --- Dark Mode --- */
        body.dark-mode {
            background-color: #212529 !important; /* Gris oscuro / Negro suave */
            color: #f8f9fa;
        }
        
        .dark-mode .navbar {
            background-color: #343a40 !important; /* Navbar oscura */
            border-bottom: 1px solid #495057;
        }
        
        .dark-mode .navbar-brand, 
        .dark-mode .nav-link, 
        .dark-mode .navbar-toggler-icon {
            color: #f8f9fa !important;
        }
        
        /* Necesario para que el icono del toggler se vea en oscuro */
        .dark-mode .navbar-toggler {
            border-color: rgba(255,255,255,0.1);
        }
        .dark-mode .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.55%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        }

        .dark-mode .card {
            background-color: #2c3034;
            color: #ffffff;
        }
        
        .dark-mode .table {
            color: #f8f9fa;
            --bs-table-color: #f8f9fa;
            --bs-table-bg: #2c3034;
            --bs-table-border-color: #495057;
            --bs-table-striped-bg: #32373c;
            --bs-table-striped-color: #f8f9fa;
            --bs-table-active-bg: #373b3e;
            --bs-table-active-color: #f8f9fa;
            --bs-table-hover-bg: #32373c;
            --bs-table-hover-color: #f8f9fa;
        }
        
        .dark-mode .table-light {
             background-color: #343a40;
             color: #f8f9fa;
        }
        
        /* Inputs y Selects en Dark Mode */
        .dark-mode .form-control, 
        .dark-mode .form-select {
            background-color: #2c3034;
            border-color: #495057;
            color: #f8f9fa;
        }
        
        .dark-mode .input-group-text {
            background-color: #343a40;
            border-color: #495057;
            color: #f8f9fa;
        }

    </style>
</head>
<body class=""> <!-- Clase bg-light eliminada para que el color del body funcione -->

    <!-- Script Dark Mode (Inline para evitar flickeo) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
            }
        })();

        document.addEventListener('DOMContentLoaded', function() {
            const btnToggle = document.getElementById('btnThemeToggle');
            const icon = btnToggle.querySelector('i');
            const body = document.body;

            // Función para actualizar icono
            const updateIcon = (isDark) => {
                if (isDark) {
                    icon.classList.remove('bi-moon-stars-fill');
                    icon.classList.add('bi-sun-fill');
                    btnToggle.classList.add('text-warning');
                    btnToggle.classList.remove('text-secondary');
                } else {
                    icon.classList.remove('bi-sun-fill');
                    icon.classList.add('bi-moon-stars-fill');
                    btnToggle.classList.remove('text-warning');
                    btnToggle.classList.add('text-secondary');
                }
            };

            // Verificar estado inicial al cargar DOM
            if (body.classList.contains('dark-mode')) {
                updateIcon(true);
            } else {
                updateIcon(false);
            }

            btnToggle.addEventListener('click', function() {
                body.classList.toggle('dark-mode');
                const isDark = body.classList.contains('dark-mode');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                updateIcon(isDark);
            });
        });
    </script>

    <!-- 1. Nueva Barra de Navegación Superior -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container-fluid">
            <!-- Título Principal -->
            <a class="navbar-brand fw-bold text-primary" href="index.php">Ventas JD</a>
            
            <!-- Botón Hamburguesa para Móvil -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navegacionPrincipal" aria-controls="navegacionPrincipal" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Enlaces Centrados -->
            <div class="collapse navbar-collapse" id="navegacionPrincipal">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina_actual == 'index.php') ? 'active' : ''; ?>" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina_actual == 'stock.php') ? 'active' : ''; ?>" href="stock.php">Artículos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina_actual == 'clientes.php') ? 'active' : ''; ?>" href="clientes.php">Clientes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina_actual == 'proveedores.php') ? 'active' : ''; ?>" href="proveedores.php">Proveedores</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo ($pagina_actual == 'ventas.php' || $pagina_actual == 'nueva_venta.php') ? 'active' : ''; ?>" href="#" id="navbarDropdownVentas" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Ventas
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownVentas">
                            <li><a class="dropdown-item" href="nueva_venta.php">Nueva Venta (POS)</a></li>
                            <li><a class="dropdown-item" href="ventas.php">Historial de Ventas</a></li>
                        </ul>
                    </li>
                     <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo ($pagina_actual == 'compras.php' || $pagina_actual == 'nueva_compra.php') ? 'active' : ''; ?>" href="#" id="navbarDropdownCompras" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Compras
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownCompras">
                            <li><a class="dropdown-item" href="nueva_compra.php">Nueva Compra</a></li>
                            <li><a class="dropdown-item" href="compras.php">Historial de Compras</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina_actual == 'cta_corriente.php') ? 'active' : ''; ?>" href="cta_corriente.php">Cta. Corriente</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina_actual == 'caja.php') ? 'active' : ''; ?>" href="caja.php">Caja</a>
                    </li>
                    <!-- NUEVO ENLACE A REPORTES -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina_actual == 'reporte_ventas.php') ? 'active' : ''; ?>" href="reporte_ventas.php">Reportes</a>
                    </li>
                </ul>
                
                <!-- Usuario y Salir (Alineado a la derecha) -->
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Botón Dark Mode -->
                    <li class="nav-item me-3">
                        <button class="btn btn-link nav-link p-0 fs-5" id="btnThemeToggle" title="Cambiar Tema" style="text-decoration: none;">
                            <i class="bi bi-moon-stars-fill"></i>
                        </button>
                    </li>
                     <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownUsuario" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownUsuario">
                            <li><a class="dropdown-item" href="usuarios.php"><i class="bi bi-people me-2"></i>Gestión de Usuarios</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- 2. Contenedor Principal (se cierra en footer.php) -->
    <!-- Damos un padding-top al wrapper para compensar la navbar fija -->
    <div class="content-wrapper" style="padding-top: 56px;">

    <!-- Script Global para Alertas via URL (PHP -> JS) -->
    <?php
    if (isset($_GET['mensaje']) || isset($_GET['success']) || isset($_GET['error'])) {
        $status = 'info';
        $msg = '';
        $title = 'Aviso';

        if (isset($_GET['success'])) {
            $status = 'success';
            $msg = $_GET['success'];
            $title = '¡Éxito!';
        } elseif (isset($_GET['error'])) {
            $status = 'error';
            $msg = $_GET['error'];
            $title = 'Error';
        } elseif (isset($_GET['mensaje'])) {
            $status = 'info';
            $msg = $_GET['mensaje'];
            $title = 'Información';
        }
        
        // Limpiamos el mensaje para evitar XSS básico
        $msg = htmlspecialchars($msg);
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '$status',
                    title: '$title',
                    text: '$msg',
                    confirmButtonColor: '#3085d6'
                });
                // Limpiar URL (opcional, para qu no salga al recargar)
                window.history.replaceState(null, null, window.location.pathname);
            });
        </script>";
    }
    ?>