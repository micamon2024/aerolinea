<?php
session_start();
require './config/bd.php';

header('Content-Type: application/json');

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

// Validar CSRF
if (!isset($data['csrf_token']) || $data['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Token de seguridad inválido']);
    exit;
}

// Validar y limpiar items del carrito
$items = [];
foreach ($data['items'] ?? [] as $item) {
    if (!isset($item['id']) || !isset($item['nombre']) || !isset($item['precio'])) {
        continue;
    }
    
    // Mantener la estructura consistente con procesar_compra.php
    $cleanItem = [
        'id' => (int)$item['id'],
        'nombre' => htmlspecialchars($item['nombre']),
        'precio' => (float)$item['precio'],
        'cantidad' => isset($item['cantidad']) ? max(1, (int)$item['cantidad']) : 1,
        'detalles' => [
            'fechas' => $item['detalles']['fechas'] ?? null,
            'tipo' => $item['detalles']['tipo'] ?? null,
            'cliente' => $item['detalles']['cliente'] ?? null,
            // Agregar otros detalles según sea necesario
        ]
    ];
    
    // Mantener campos específicos del tipo de servicio
    if (isset($item['detalles']['origen'])) {
        $cleanItem['detalles']['origen'] = htmlspecialchars($item['detalles']['origen']);
    }
    if (isset($item['detalles']['destino'])) {
        $cleanItem['detalles']['destino'] = htmlspecialchars($item['detalles']['destino']);
    }
    if (isset($item['detalles']['habitaciones'])) {
        $cleanItem['detalles']['habitaciones'] = (int)$item['detalles']['habitaciones'];
    }
    
    $items[] = $cleanItem;
}

// Actualizar carrito en sesión
$_SESSION['carrito'] = $items;

echo json_encode(['success' => true, 'count' => count($items)]);