<?php
// El registro público está deshabilitado.
// Los usuarios solo pueden ser creados por un administrador desde el sistema.
session_start();
if (isset($_SESSION['usuario_id'])) {
    header('Location: usuarios.php');
} else {
    header('Location: login.php');
}
exit();
?>
