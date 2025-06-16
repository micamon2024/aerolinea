<?php
session_start();

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include('./estructura/cabecera.php');
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex align-items-center mb-4">
                <h1 class="mb-0 me-3"><i class="bi bi-cart3 text-primary"></i></h1>
                <div>
                    <h1 class="mb-0">Tu Carrito de Viajes</h1>
                    <p class="text-muted mb-0">Revisa y gestiona tus reservas antes de pagar</p>
                </div>
            </div>

            <!-- ALERTAS -->
            <?php if (isset($_GET['mensaje'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_GET['mensaje']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <!-- Productos disponibles (si el carrito está vacío) -->
            <section id="productos" class="mb-5 <?= empty($_SESSION['carrito']) ? '' : 'd-none' ?>">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">Descubre nuestros paquetes</h3>
                    <a href="./menu/formulario_reserva.php" class="btn btn-outline-primary">
                        <i class="bi bi-plus-circle me-2"></i>Nueva Reserva
                    </a>
                </div>
                <div id="lista-productos" class="row row-cols-1 row-cols-md-3 g-4">
                    <?php if (empty($_SESSION['carrito'])): ?>
                        <div class="col-12 text-center py-5">
                            <i class="bi bi-cart-x fs-1 text-muted"></i>
                            <h5 class="mt-3">Tu carrito está vacío</h5>
                        </div>
                    <?php else: ?>
                        <?php foreach ($_SESSION['carrito'] as $item): ?>
                            <div class="col">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($item['nombre']) ?></h5>
                                        <p class="card-text text-muted small">
                                            <i class="bi bi-calendar"></i> <?= htmlspecialchars($item['detalles']['fechas']) ?><br>
                                            <?php if (!empty($item['detalles']['origen'])): ?>
                                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($item['detalles']['origen']) ?>
                                            <?php endif; ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="h5 text-primary">$<?= number_format($item['precio'], 2) ?></span>
                                            <button class="btn btn-sm btn-danger" onclick="eliminarDelCarrito('<?= $item['id'] ?>')">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Tabla del carrito -->
            <?php if (!empty($_SESSION['carrito'])): ?>
            <div class="card shadow-sm border-0 overflow-hidden">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="tablaCarrito">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40%">Servicio</th>
                                    <th>Precio Unitario</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                foreach ($_SESSION['carrito'] as $item): 
                                    $subtotal = $item['precio'] * $item['cantidad'];
                                    $total += $subtotal;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($item['nombre']) ?></strong><br>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i><?= htmlspecialchars($item['detalles']['fechas']) ?><br>
                                            <?php if (!empty($item['detalles']['origen'])): ?>
                                                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($item['detalles']['origen']) ?>
                                                <?php if (!empty($item['detalles']['destino'])): ?>
                                                    → <?= htmlspecialchars($item['detalles']['destino']) ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>$<?= number_format($item['precio'], 2) ?></td>
                                    <td>
                                        <div class="input-group" style="width: 120px;">
                                            <button class="btn btn-outline-secondary" 
                                                onclick="actualizarCantidad('<?= $item['id'] ?>', -1)">-</button>
                                            <input type="number" class="form-control text-center" 
                                                value="<?= $item['cantidad'] ?>" min="1" 
                                                onchange="actualizarCantidad('<?= $item['id'] ?>', 0, this.value)">
                                            <button class="btn btn-outline-secondary" 
                                                onclick="actualizarCantidad('<?= $item['id'] ?>', 1)">+</button>
                                        </div>
                                    </td>
                                    <td>$<?= number_format($subtotal, 2) ?></td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" 
                                            onclick="eliminarDelCarrito('<?= $item['id'] ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Resumen -->
                    <div class="row mt-4 g-3">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>Detalles del Cliente</h5>
                                    <?php if (!empty($_SESSION['carrito'][0]['detalles']['cliente'])): ?>
                                    <div class="mt-3">
                                        <p class="mb-1"><strong>Nombre:</strong> <?= htmlspecialchars($_SESSION['carrito'][0]['detalles']['cliente']['nombre']) ?></p>
                                        <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($_SESSION['carrito'][0]['detalles']['cliente']['email']) ?></p>
                                        <?php if (!empty($_SESSION['carrito'][0]['detalles']['cliente']['telefono'])): ?>
                                        <p class="mb-0"><strong>Teléfono:</strong> <?= htmlspecialchars($_SESSION['carrito'][0]['detalles']['cliente']['telefono']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-receipt me-2"></i>Resumen de compra</h5>
                                    <div class="d-flex justify-content-between mt-3">
                                        <span>Subtotal:</span>
                                        <span>$<?= number_format($total * 0.82, 2) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Impuestos (21%):</span>
                                        <span>$<?= number_format($total * 0.21, 2) ?></span>
                                    </div>
                                    <hr class="my-2 bg-white">
                                    <div class="d-flex justify-content-between fw-bold fs-5">
                                        <span>Total:</span>
                                        <span>$<?= number_format($total, 2) ?></span>
                                    </div>
                                    <button id="btnFinalizar" class="btn btn-light w-100 py-3 mt-3 fw-bold" onclick="finalizarCompra()">
                                        <i class="bi bi-credit-card me-2"></i>Finalizar Compra
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Función para eliminar ítem del carrito
function eliminarDelCarrito(id) {
    if (confirm('¿Estás seguro de eliminar este item de tu carrito?')) {
        fetch('actualizar_carrito.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>',
                action: 'remove',
                id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Error al eliminar el item');
            }
        });
    }
}

// Función para actualizar cantidad
function actualizarCantidad(id, cambio, valorExacto = null) {
    const carrito = <?= json_encode($_SESSION['carrito'] ?? []) ?>;
    const item = carrito.find(item => item.id === id);
    
    if (!item) return;
    
    let nuevaCantidad = valorExacto !== null ? 
        parseInt(valorExacto) : 
        item.cantidad + cambio;
    
    if (nuevaCantidad < 1) nuevaCantidad = 1;
    
    fetch('actualizar_carrito.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>',
            action: 'update_quantity',
            id: id,
            quantity: nuevaCantidad
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Error al actualizar la cantidad');
        }
    });
}

// Función para finalizar compra
function finalizarCompra() {
    <?php if (empty($_SESSION['carrito'])): ?>
        alert('Agrega productos antes de finalizar la compra');
        return;
    <?php endif; ?>

    <?php if (empty($_SESSION['usuario'])): ?>
        if (confirm('Debes iniciar sesión para finalizar la compra. ¿Deseas ir a la página de login?')) {
            window.location.href = 'login.php?redirect=carrito.php';
        }
        return;
    <?php endif; ?>

    window.location.href = 'procesar_pago.php';
}
</script>

<?php include('./estructura/pie.php'); ?>