<?php
// Configuración de la base de datos
$host = 'localhost'; // O tu host de base de datos (ej: 127.0.0.1)
$db_name = 'a0040079_ventas'; // El nombre de tu base de datos
$username = 'a0040079_ventas'; // Tu usuario de MySQL
$password = 'dusuGO38fi'; // Tu contraseña de MySQL

// Opciones de PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Activa los errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve resultados como array asociativo
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactiva la emulación de consultas preparadas
];

try {
    // Crear una nueva instancia de PDO
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password, $options);
    
    // Descomenta la siguiente línea si quieres verificar la conexión al cargar
    // echo "Conexión a la base de datos exitosa.";

} catch (PDOException $e) {
    // Manejar errores de conexión
    die("Error de conexión: " . $e->getMessage());
}
?>