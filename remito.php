<?php
// 1. Iniciar Sesión y Conectar a la BD
session_start();
require 'db_connect.php';

// 2. Seguridad: Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Por favor, inicie sesión.");
}

// 3. Obtener el ID de la venta desde la URL
$id_venta = $_GET['id'] ?? 0;
if ($id_venta <= 0) {
    die("ID de venta no válido.");
}

try {
    // 4. Obtener la información principal de la venta (Cabecera)
    $stmt_venta = $pdo->prepare("
        SELECT 
            v.id, v.fecha, v.total, v.tipo_pago, v.descuento_porcentaje,
            u.nombre_usuario AS vendedor,
            IFNULL(CONCAT(c.nombre, ' ', c.apellido), 'Consumidor Final') AS cliente_nombre,
            IFNULL(c.dni, 'N/A') AS cliente_dni,
            IFNULL(c.direccion, 'N/A') AS cliente_direccion
        FROM ventas v
        JOIN usuarios u ON v.id_usuario = u.id
        LEFT JOIN clientes c ON v.id_cliente = c.id
        WHERE v.id = ?
    ");
    $stmt_venta->execute([$id_venta]);
    $venta = $stmt_venta->fetch();

    if (!$venta) {
        die("Venta no encontrada.");
    }

    // 5. Obtener el detalle (los artículos) de la venta
    $stmt_detalle = $pdo->prepare("
        SELECT 
            a.codigo,
            a.descripcion,
            dv.cantidad,
            dv.precio_unitario_venta AS precio_unitario,
            (dv.cantidad * dv.precio_unitario_venta) AS subtotal_linea
        FROM detalle_venta dv
        JOIN articulos a ON dv.id_articulo = a.id
        WHERE dv.id_venta = ?
    ");
    $stmt_detalle->execute([$id_venta]);
    $detalles = $stmt_detalle->fetchAll();

    // 6. Calcular Subtotal Real (Suma de los ítems ANTES del descuento)
    $subtotal_real = 0;
    foreach ($detalles as $item) {
        $subtotal_real += $item['subtotal_linea'];
    }
    
    // 7. Calcular el monto del descuento (si existe)
    $monto_descuento = 0;
    $porcentaje = floatval($venta['descuento_porcentaje']);
    
    if ($porcentaje > 0) {
        $monto_descuento = $subtotal_real * ($porcentaje / 100);
    }

} catch (PDOException $e) {
    die("Error al consultar la base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remito Venta #<?php echo $venta['id']; ?></title>
    <!-- Bootstrap CSS para un estilo limpio -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fuente Source Sans Pro -->
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Estilos para el visor */
        body {
            background-color: #525659; /* Fondo oscuro estilo visor de PDF */
            font-family: 'Source Sans Pro', sans-serif;
        }
        .remito-paper {
            max-width: 800px; /* Ancho A4 aprox */
            margin: 30px auto;
            padding: 40px;
            background-color: #ffffff; /* Papel blanco */
            box-shadow: 0 0 15px rgba(0,0,0,0.5);
            min-height: 600px;
        }
        .header-divider {
            border-bottom: 2px solid #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .table-items th {
            background-color: #f8f9fa;
            font-weight: 700;
        }
        .table-items td {
            vertical-align: middle;
        }
        
        /* Estilo para el LOGO */
        .logo-empresa {
            max-width: 150px; /* Ancho máximo del logo */
            max-height: 80px; /* Alto máximo */
            object-fit: contain; /* Mantiene la proporción */
            margin-bottom: 10px;
            display: block;
        }
        
        /* Estilos específicos para Impresión */
        @media print {
            body {
                background-color: #ffffff;
                margin: 0;
                padding: 0;
            }
            .remito-paper {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
                width: 100%;
                border: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            /* Asegurar colores de fondo en impresión */
            .bg-light {
                background-color: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <!-- Botones de Acción (No se imprimen) -->
    <div class="container-fluid text-center mt-3 no-print">
        <button class="btn btn-light btn-sm shadow-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> Imprimir / Guardar PDF
        </button>
        <button class="btn btn-dark btn-sm ms-2 shadow-sm" onclick="window.close()">
            Cerrar
        </button>
    </div>

    <!-- Hoja del Remito -->
    <div class="remito-paper">
        
        <!-- Encabezado -->
        <div class="row header-divider align-items-center"> <!-- align-items-center para centrar logo verticalmente con el texto -->
            <div class="col-7">
                <!-- LOGO AQUÍ -->
                <!-- Asegúrate de tener una imagen llamada 'logo.png' en la misma carpeta -->
                <!-- Si no tienes imagen, el 'alt' mostrará el texto o puedes borrar la etiqueta img -->
                <img src="img/logo.jpg" alt="Logo Empresa" class="logo-empresa">
                
                <h2 class="fw-bold text-uppercase fs-4 m-0">Descartables Los Amigos</h2>
                <p class="mb-0 small">Jose (pedidos): 3515904651</p>
                <p class="mb-0 small">Administracion y Pedidos: 3512114715</p>
                
            </div>
            <div class="col-5 text-end">
                <h4 class="fw-bold text-secondary">REMITO / PRESUPUESTO</h4>
                <h5 class="mb-0">Nº: <?php echo str_pad($venta['id'], 8, '0', STR_PAD_LEFT); ?></h5>
                <p class="mb-0 small text-muted">Fecha: <?php echo date("d/m/Y H:i", strtotime($venta['fecha'])); ?></p>
            </div>
        </div>

        <!-- Datos del Cliente -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="p-3 bg-light border rounded">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Cliente:</strong> <?php echo htmlspecialchars($venta['cliente_nombre']); ?><br>
                            <strong>DNI/CUIT:</strong> <?php echo htmlspecialchars($venta['cliente_dni']); ?>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <strong>Condición de Venta:</strong> <?php echo htmlspecialchars($venta['tipo_pago']); ?><br>
                            <strong>Dirección:</strong> <?php echo htmlspecialchars($venta['cliente_direccion']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Artículos -->
        <table class="table table-bordered table-items mb-4">
            <thead>
                <tr>
                    <th style="width: 15%">Cód.</th>
                    <th style="width: 45%">Descripción</th>
                    <th class="text-center" style="width: 10%">Cant.</th>
                    <th class="text-end" style="width: 15%">P. Unit.</th>
                    <th class="text-end" style="width: 15%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $item): ?>
                <tr>
                    <td><small><?php echo htmlspecialchars($item['codigo']); ?></small></td>
                    <td><?php echo htmlspecialchars($item['descripcion']); ?></td>
                    <td class="text-center"><?php echo $item['cantidad']; ?></td>
                    <td class="text-end">$<?php echo number_format($item['precio_unitario'], 2); ?></td>
                    <td class="text-end">$<?php echo number_format($item['subtotal_linea'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Sección de Totales -->
        <div class="row justify-content-end">
            <div class="col-md-5">
                <table class="table table-borderless table-sm">
                    <!-- Subtotal -->
                    <tr>
                        <td class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end">$<?php echo number_format($subtotal_real, 2); ?></td>
                    </tr>
                    
                    <!-- Descuento (Solo si aplica) -->
                    <?php if ($porcentaje > 0): ?>
                    <tr class="text-danger">
                        <td class="text-end"><strong>Descuento (<?php echo $porcentaje + 0; ?>%):</strong></td>
                        <td class="text-end">-$<?php echo number_format($monto_descuento, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Total Final -->
                    <tr class="border-top">
                        <td class="text-end fs-5"><strong>TOTAL:</strong></td>
                        <td class="text-end fs-5"><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Pie de Página -->
        <div class="mt-5 pt-4 border-top text-center text-muted small">
            <p class="mb-1">Vendedor: <?php echo htmlspecialchars($venta['vendedor']); ?></p>
            <p>Gracias por su compra. Este documento no es válido como factura fiscal.</p>
        </div>

    </div>

</body>
</html>