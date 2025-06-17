<?php

// Configuración de la base de datos
define('DB_HOST', 'sql210.infinityfree.com');
define('DB_NAME', 'if0_39256117_aerolinea');
define('DB_USER', 'if0_39256117');
define('DB_PASS', 'SNRJftW03TQ');
define('DB_CHARSET', 'utf8mb4');

try {
    $conexion = new PDO("mysql:host=$host;dbname=$bd;charset=utf8", $usuario, $contrasenia);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $ex) {
    // Log del error (no mostrar al usuario)
    error_log("Error de conexión: " . $ex->getMessage());
    die("Error al conectar con la base de datos. Por favor, intente más tarde.");
}
?>
