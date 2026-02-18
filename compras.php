<?php require 'header.php'; ?>

<!-- Contenido principal -->
<div class="container mt-5 pt-5">

    <!-- 1. Cabecera de la Página -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Historial de Compras</h1>
        <a href="nueva_compra.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Nueva Compra
        </a>
    </div>

    <!-- 2. Tarjeta de Contenido (Tabla) -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaCompras" class="table table-striped table-hover" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>ID Compra</th>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Registró</th>
                            <th>Total</th>
                            <!-- Podríamos añadir un botón de "Ver Detalle" similar a ventas si fuera necesario -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Consulta para traer compras
                        $sql = "
                            SELECT 
                                c.id, 
                                c.fecha, 
                                c.total,
                                u.nombre_usuario AS registrador,
                                IFNULL(p.nombre_proveedor, '(Proveedor Eliminado)') AS proveedor
                            FROM compras c
                            JOIN usuarios u ON c.id_usuario = u.id
                            LEFT JOIN proveedores p ON c.id_proveedor = p.id
                            ORDER BY c.fecha DESC
                        ";
                        $stmt = $pdo->query($sql);

                        while ($row = $stmt->fetch()) {
                            echo "<tr>";
                            echo "<td>" . $row['id'] . "</td>";
                            echo "<td>" . date("d/m/Y H:i", strtotime($row['fecha'])) . "</td>";
                            echo "<td>" . htmlspecialchars($row['proveedor']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['registrador']) . "</td>";
                            echo "<td class='fw-bold'>$" . number_format($row['total'], 2) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================== -->
<!--          CORRECCIÓN DE ORDEN: El Footer DEBE ir ANTES 
            del script para que jQuery ($) esté definido 
<!-- ================================================================== -->
<?php require 'footer.php'; ?>

<!-- 4. JavaScript Específico de la Página -->
<script>
$(document).ready(function() {
    
    // 1. Inicializar DataTables
    $('#tablaCompras').DataTable({
        "language": {
            // CORRECCIÓN: Añadimos "https:"
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "order": [[1, "desc"]] // Ordenar por fecha descendente
    });

});
</script>