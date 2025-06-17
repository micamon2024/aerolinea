<?php
define("KEY", "aerolinea");
define("COD", "AES-128-ECB");

// Configuración de la base de datos
define('DB_HOST', 'sql210.infinityfree.com');
define('DB_NAME', 'if0_39256117_aerolinea');
define('DB_USER', 'if0_39256117');
define('DB_PASS', 'SNRJftW03TQ');
define('DB_CHARSET', 'utf8mb4');

try {
    $conexion= new PDO ("mysql:host=$host;dbname=$bd", $usuario, $contrasenia);
    // Creo la variable pdo me comunica con la bd, nombre del host, db
    // if($conexion){echo "conectado";}  Pregunto si la conexion se hizo y imprimo conectado
}

catch (Exception $ex) {
    // Si ocurre un error, toma el erro y muestra el mensaje
    echo $ex->getmessage();
}
?>
