<?php
session_start();
require_once 'config/bd.php';

// Verificar que el carrito no esté vacío
if (empty($_SESSION['carrito'])) {
    $_SESSION['error'] = "No hay items en el carrito para procesar";
    header('Location: carrito.php');
    exit();
}

// Verificar si el usuario está logueado
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario']['id'])) {
    $_SESSION['error'] = "Debes iniciar sesión para finalizar la compra";
    header('Location: login.php?redirect=carrito.php');
    exit();
}

// Verificar que el email del cliente esté disponible
if (empty($_SESSION['carrito'][0]['detalles']['cliente']['email'])) {
    $_SESSION['error'] = "No se encontró información de contacto para la reserva";
    header('Location: carrito.php');
    exit();
}

// Procesar el pago
try {
    $conexion->beginTransaction();
    
    // 1. Calcular total de la reserva
    $total = 0;
    $detalles_reserva = "";
    
    foreach ($_SESSION['carrito'] as $item) {
        $subtotal = $item['precio'] * $item['cantidad'];
        $total += $subtotal;
        $detalles_reserva .= "• {$item['nombre']} - {$item['detalles']['fechas']} - {$item['cantidad']} x $" . number_format($item['precio'], 2) . " = $" . number_format($subtotal, 2) . "\n";
    }

    // 2. Crear la reserva principal
    $stmt = $conexion->prepare("INSERT INTO reservas 
        (id_usuario, fecha_reserva, total, estado, metodo_pago, email_cliente, fecha_creacion, detalles) 
        VALUES (?, NOW(), ?, 'confirmada', 'online', ?, NOW(), ?)");
    
    $stmt->execute([
        $_SESSION['usuario']['id'],
        $total,
        $_SESSION['carrito'][0]['detalles']['cliente']['email'],
        $detalles_reserva
    ]);
    
    $id_reserva = $conexion->lastInsertId();

    // 3. Guardar los detalles de la reserva
    foreach ($_SESSION['carrito'] as $item) {
        $stmt = $conexion->prepare("INSERT INTO reserva_detalles 
            (id_reserva, tipo_servicio, descripcion, cantidad, precio, detalles, fecha_creacion) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $id_reserva,
            $item['detalles']['tipo'],
            $item['nombre'],
            $item['cantidad'],
            $item['precio'],
            json_encode($item['detalles'])
        ]);
    }

    // 4. Registrar el pago
    $stmt = $conexion->prepare("INSERT INTO pagos 
        (id_reserva, metodo, monto, estado, fecha_pago, datos) 
        VALUES (?, 'online', ?, 'completado', NOW(), ?)");
    
    $stmt->execute([
        $id_reserva,
        $total,
        json_encode([
            'cliente' => $_SESSION['carrito'][0]['detalles']['cliente'],
            'items' => count($_SESSION['carrito'])
        ])
    ]);

    $conexion->commit();
    
    // 5. Enviar email de confirmación
    $email_enviado = enviarEmailConfirmacion(
        $_SESSION['carrito'][0]['detalles']['cliente']['email'],
        $id_reserva,
        $_SESSION['carrito'],
        $total
    );

    // 6. Vaciar el carrito y preparar datos para la confirmación
    $datos_reserva = [
        'id_reserva' => $id_reserva,
        'fecha' => date('d/m/Y H:i'),
        'total' => $total,
        'cliente' => $_SESSION['carrito'][0]['detalles']['cliente'],
        'items' => $_SESSION['carrito']
    ];
    
    unset($_SESSION['carrito']);
    $_SESSION['reserva_confirmada'] = $datos_reserva;

    // 7. Redirigir a confirmación
    header('Location: confirmacion_reserva.php?id='.$id_reserva);
    exit();
    
} catch (PDOException $e) {
    $conexion->rollBack();
    $_SESSION['error'] = "Error al procesar el pago: " . $e->getMessage();
    error_log("Error en procesar_pago.php: " . $e->getMessage());
    header('Location: carrito.php');
    exit();
}

/**
 * Función para enviar email de confirmación
 */
function enviarEmailConfirmacion($email, $id_reserva, $items, $total) {
    // Configuración básica del email
    $asunto = "Confirmación de Reserva #$id_reserva";
    $mensaje = "¡Gracias por tu reserva!\n\n";
    $mensaje .= "Número de reserva: #$id_reserva\n";
    $mensaje .= "Fecha: " . date('d/m/Y H:i') . "\n\n";
    $mensaje .= "Detalles de tu reserva:\n";
    
    foreach ($items as $item) {
        $mensaje .= "----------------------------------------\n";
        $mensaje .= "Servicio: {$item['nombre']}\n";
        $mensaje .= "Fechas: {$item['detalles']['fechas']}\n";
        $mensaje .= "Cantidad: {$item['cantidad']}\n";
        $mensaje .= "Precio unitario: $" . number_format($item['precio'], 2) . "\n";
        $mensaje .= "Subtotal: $" . number_format($item['precio'] * $item['cantidad'], 2) . "\n";
        
        // Agregar detalles específicos según el tipo de servicio
        if ($item['detalles']['tipo'] === 'vuelo') {
            $mensaje .= "Ruta: {$item['detalles']['origen']} a {$item['detalles']['destino']}\n";
            $mensaje .= "Clase: " . ucfirst($item['detalles']['clase']) . "\n";
        } elseif ($item['detalles']['tipo'] === 'hotel') {
            $mensaje .= "Ubicación: {$item['detalles']['ciudad']}\n";
            $mensaje .= "Habitaciones: {$item['detalles']['habitaciones']} ({$item['detalles']['tipo_habitacion']})\n";
        }
    }
    
    $mensaje .= "\n----------------------------------------\n";
    $mensaje .= "TOTAL: $" . number_format($total, 2) . "\n\n";
    $mensaje .= "Para cualquier consulta, contáctanos a soporte@tusitio.com\n";
    
    // Cabeceras del email
    $headers = "From: no-reply@tusitio.com\r\n";
    $headers .= "Reply-To: no-reply@tusitio.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Enviar el email (en producción usar PHPMailer o similar)
    $enviado = mail($email, $asunto, $mensaje, $headers);
    
    if (!$enviado) {
        error_log("Error al enviar email de confirmación para la reserva #$id_reserva");
    }
    
    return $enviado;
}
?>