<?php
session_start(); // Debe ser lo PRIMERO en el archivo
require_once 'config/bd.php';

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// 1. Validar método de envío
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Método no permitido";
    header('Location: ./menu/formulario_reserva.php');
    exit();
}

// 2. Validar acción
$accion = $_POST['accion'] ?? '';
if (!in_array($accion, ['agregar_al_carrito', 'eliminar'])) {
    $_SESSION['error'] = "Acción no válida";
    header('Location: ./menu/formulario_reserva.php');
    exit();
}

// 3. Manejar eliminación de ítem
if ($accion === 'eliminar') {
    if (!empty($_POST['id'])) {
        foreach ($_SESSION['carrito'] as $key => $item) {
            if ($item['id'] === $_POST['id']) {
                unset($_SESSION['carrito'][$key]);
                $_SESSION['carrito'] = array_values($_SESSION['carrito']); // Reindexar array
                break;
            }
        }
    }
    header('Location: carrito.php');
    exit();
}

// 4. Validar datos del formulario (para acción agregar)
$errores = [];
$tipo_servicio = $_POST['tipo_servicio'] ?? '';
$fecha_inicio = $_POST['fecha_inicio'] ?? '';
$fecha_fin = $_POST['fecha_fin'] ?? '';
$nombre = $_POST['nombre'] ?? '';
$email = $_POST['email'] ?? '';

// Validaciones básicas
if (empty($tipo_servicio)) {
    $errores[] = "Selecciona un tipo de servicio";
}

if (empty($fecha_inicio) || empty($fecha_fin)) {
    $errores[] = "Las fechas son obligatorias";
} elseif (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
    $errores[] = "La fecha de fin debe ser posterior a la de inicio";
}

if (empty($nombre)) {
    $errores[] = "El nombre es obligatorio";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = "El email no es válido";
}

// Validaciones específicas por tipo de servicio
switch ($tipo_servicio) {
    case 'vuelo':
        $origen = $_POST['origen_vuelo'] ?? '';
        $destino = $_POST['destino_vuelo'] ?? '';
        if (empty($origen) || empty($destino)) {
            $errores[] = "Origen y destino son obligatorios para vuelos";
        }
        break;
        
    case 'hotel':
        $habitaciones = $_POST['cantidad_habitaciones'] ?? 0;
        if ($habitaciones < 1) {
            $errores[] = "Debes reservar al menos una habitación";
        }
        break;
        
    case 'auto':
        $edad = $_POST['edad_conductor'] ?? 0;
        if ($edad < 18) {
            $errores[] = "El conductor debe tener al menos 18 años";
        }
        break;
}

// Si hay errores, redirigir al formulario
if (!empty($errores)) {
    $_SESSION['errores'] = $errores;
    $_SESSION['datos_formulario'] = $_POST;
    header('Location: ./menu/formulario_reserva.php');
    exit();
}

// 5. Crear objeto "producto" para el carrito
$producto = [
    'id' => uniqid(),
    'nombre' => "Reserva de " . ucfirst($tipo_servicio),
    'precio' => calcularPrecio($tipo_servicio, $fecha_inicio, $fecha_fin, $_POST),
    'cantidad' => 1,
    'detalles' => [
        'tipo' => $tipo_servicio,
        'fechas' => "$fecha_inicio al $fecha_fin",
        'cliente' => [
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $_POST['telefono'] ?? ''
        ]
    ]
];

// Agregar detalles específicos según el tipo de servicio
switch ($tipo_servicio) {
    case 'vuelo':
        $producto['detalles']['origen'] = $_POST['origen_vuelo'];
        $producto['detalles']['destino'] = $_POST['destino_vuelo'];
        $producto['detalles']['clase'] = $_POST['clase_vuelo'];
        $producto['detalles']['pasajeros'] = $_POST['pasajeros_vuelo'];
        $producto['nombre'] = "Vuelo {$producto['detalles']['origen']}-{$producto['detalles']['destino']}";
        break;
        
    case 'hotel':
        $producto['detalles']['ciudad'] = $_POST['destino_hotel'];
        $producto['detalles']['habitaciones'] = $_POST['cantidad_habitaciones'];
        $producto['detalles']['tipo_habitacion'] = $_POST['tipo_habitacion'];
        $producto['detalles']['adultos'] = $_POST['adultos_hotel'];
        $producto['detalles']['ninos'] = $_POST['ninos_hotel'];
        $producto['nombre'] = "Hotel en {$producto['detalles']['ciudad']}";
        break;
        
    case 'auto':
        $producto['detalles']['lugar_retiro'] = $_POST['lugar_retiro'];
        $producto['detalles']['lugar_devolucion'] = $_POST['lugar_devolucion'];
        $producto['detalles']['tipo_auto'] = $_POST['tipo_auto'];
        $producto['detalles']['edad_conductor'] = $_POST['edad_conductor'];
        $producto['nombre'] = "Auto {$producto['detalles']['tipo_auto']}";
        break;
        
    case 'paquete':
        $producto['detalles']['tipo_paquete'] = $_POST['tipo_paquete'];
        $producto['detalles']['adultos'] = $_POST['adultos_paquete'];
        $producto['detalles']['ninos'] = $_POST['ninos_paquete'];
        $producto['nombre'] = "Paquete " . ucfirst($producto['detalles']['tipo_paquete']);
        break;
}

// 6. Guardar en el carrito (sesión)
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Verificar si ya existe un ítem similar para evitar duplicados
$itemExistente = false;
foreach ($_SESSION['carrito'] as &$item) {
    if ($item['nombre'] === $producto['nombre'] && 
        $item['detalles']['fechas'] === $producto['detalles']['fechas']) {
        $item['cantidad'] += 1;
        $itemExistente = true;
        break;
    }
}

if (!$itemExistente) {
    array_push($_SESSION['carrito'], $producto);
}

// Debug: registrar contenido del carrito
error_log('Contenido del carrito: ' . print_r($_SESSION['carrito'], true));

// 7. Redirigir al carrito con mensaje de éxito
$_SESSION['mensaje'] = "Reserva agregada al carrito correctamente";
header('Location: carrito.php');
exit();

/**
 * Calcula el precio basado en el tipo de servicio, fechas y otros parámetros
 */
function calcularPrecio($tipo, $fecha_inicio, $fecha_fin, $datos) {
    $precioBase = 0;
    $dias = (strtotime($fecha_fin) - strtotime($fecha_inicio)) / (60 * 60 * 24);
    
    // Precios base según tipo de servicio
    switch ($tipo) {
        case 'vuelo':
            $precioBase = 5000; // Precio base por pasajero
            $claseMultiplier = [
                'economica' => 1,
                'premium' => 1.5,
                'business' => 2,
                'primera' => 3
            ];
            $precioBase *= $claseMultiplier[$datos['clase_vuelo']] * $datos['pasajeros_vuelo'];
            break;
            
        case 'hotel':
            $precioBase = 1500; // Precio por noche por habitación
            $tipoHabitacionMultiplier = [
                'sencilla' => 1,
                'doble' => 1.3,
                'suite' => 2,
                'familiar' => 1.8
            ];
            $precioBase *= $tipoHabitacionMultiplier[$datos['tipo_habitacion']] * $datos['cantidad_habitaciones'] * $dias;
            break;
            
        case 'auto':
            $precioBase = 800; // Precio por día
            $tipoAutoMultiplier = [
                'economico' => 0.8,
                'compacto' => 1,
                'sedan' => 1.2,
                'suv' => 1.5,
                'lujo' => 2.5
            ];
            $precioBase *= $tipoAutoMultiplier[$datos['tipo_auto']] * $dias;
            break;
            
        case 'paquete':
            $precioBase = 10000; // Precio base por adulto
            $tipoPaqueteMultiplier = [
                'aventura' => 1.2,
                'playa' => 1,
                'ciudad' => 0.9,
                'romantico' => 1.3,
                'familiar' => 1.1
            ];
            $precioBase *= $tipoPaqueteMultiplier[$datos['tipo_paquete']] * $datos['adultos_paquete'];
            $precioBase += ($datos['ninos_paquete'] * ($precioBase * 0.6)); // 60% del precio para niños
            break;
    }
    
    // Aplicar recargo por temporada alta (ejemplo simplificado)
    $mesInicio = date('m', strtotime($fecha_inicio));
    if (in_array($mesInicio, ['06', '07', '08', '12'])) { // Junio-Agosto y Diciembre
        $precioBase *= 1.2; // 20% de recargo
    }
    
    return round($precioBase, 2);
}
?>