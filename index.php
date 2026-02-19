<?php 
require 'header.php'; 

// --- 1. Consultas para KPIs y Gráficos (Backend) ---

// Fecha de hoy
$hoy = date('Y-m-d');
$mes_actual = date('m');
$anio_actual = date('Y');

// A. Ventas de Hoy
$stmt = $pdo->prepare("SELECT SUM(total) as total_hoy FROM ventas WHERE DATE(fecha) = ?");
$stmt->execute([$hoy]);
$total_ventas_hoy = $stmt->fetch()['total_hoy'] ?? 0;

// B. Ventas del Mes
$stmt = $pdo->prepare("SELECT SUM(total) as total_mes FROM ventas WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt->execute([$mes_actual, $anio_actual]);
$total_ventas_mes = $stmt->fetch()['total_mes'] ?? 0;

// C. Cantidad de Ventas Hoy
$stmt = $pdo->prepare("SELECT COUNT(*) as cantidad_hoy FROM ventas WHERE DATE(fecha) = ?");
$stmt->execute([$hoy]);
$cantidad_ventas_hoy = $stmt->fetch()['cantidad_hoy'] ?? 0;

// D. Ingresos en Caja (Hoy)
// Si la tabla movimientos_caja tiene 'Ingreso' como tipo.
$stmt = $pdo->prepare("SELECT SUM(monto) as total_ingresos FROM movimientos_caja WHERE tipo_movimiento = 'Ingreso' AND DATE(fecha) = ?");
$stmt->execute([$hoy]);
$ingresos_caja_hoy = $stmt->fetch()['total_ingresos'] ?? 0;


// --- E. Datos para Gráfico de Barras (Últimos 7 días) ---
$fecha_inicio = date('Y-m-d', strtotime('-6 days')); // 6 días atrás + hoy = 7 días
$stmt = $pdo->prepare("
    SELECT DATE(fecha) as dia, SUM(total) as total_venta 
    FROM ventas 
    WHERE DATE(fecha) >= ? 
    GROUP BY DATE(fecha) 
    ORDER BY DATE(fecha) ASC
");
$stmt->execute([$fecha_inicio]);
$ventas_semana = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Retorna array [dia => total]

// Rellenar días vacíos con 0
$datos_grafico_barras = [];
$labels_grafico_barras = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $labels_grafico_barras[] = date('d/m', strtotime($dia)); // Label formato 01/01
    $datos_grafico_barras[] = $ventas_semana[$dia] ?? 0;
}


// --- F. Datos para Gráfico de Dona (Top 5 Productos) ---
// Join entre detalle_venta y articulos
$stmt = $pdo->query("
    SELECT a.descripcion, SUM(d.cantidad) as total_cantidad
    FROM detalle_venta d
    JOIN articulos a ON d.id_articulo = a.id
    GROUP BY d.id_articulo
    ORDER BY total_cantidad DESC
    LIMIT 5
");
$top_productos = $stmt->fetchAll();

$labels_grafico_dona = [];
$datos_grafico_dona = [];
foreach ($top_productos as $prod) {
    $labels_grafico_dona[] = $prod['descripcion'];
    $datos_grafico_dona[] = $prod['total_cantidad'];
}


// --- G. Alerta de Stock Bajo (Con Paginación) ---
// 1. Configuración de paginación
$alertas_por_pagina = 10;
$pagina_actual_alertas = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($pagina_actual_alertas < 1) $pagina_actual_alertas = 1;

$offset = ($pagina_actual_alertas - 1) * $alertas_por_pagina;

// 2. Contar total de alertas para calcular páginas
$stmt_count = $pdo->query("SELECT COUNT(*) FROM articulos WHERE stock_actual < 5");
$total_alertas = $stmt_count->fetchColumn();
$total_paginas = ceil($total_alertas / $alertas_por_pagina);

// 3. Obtener alertas de la página actual
$stmt = $pdo->prepare("SELECT * FROM articulos WHERE stock_actual < 5 ORDER BY stock_actual ASC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $alertas_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$alertas_stock = $stmt->fetchAll();

?>

<!-- Contenido Principal -->
<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark"><i class="bi bi-speedometer2 me-2"></i>Dashboard Gerencial</h2>
        <span class="text-muted">Estado del negocio al <?php echo date('d/m/Y H:i'); ?></span>
    </div>

    <!-- 1. Tarjetas de KPIs -->
    <div class="row g-3 mb-4">
        <!-- Venta Hoy -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-success h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase mb-2">Ventas Hoy</h6>
                    <h3 class="fw-bold text-success mb-0">$<?php echo number_format($total_ventas_hoy, 2); ?></h3>
                    <small class="text-muted"><i class="bi bi-calendar-event"></i> <?php echo date('d/m/Y'); ?></small>
                </div>
            </div>
        </div>

        <!-- Ventas Mes -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-primary h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase mb-2">Ventas del Mes</h6>
                    <h3 class="fw-bold text-primary mb-0">$<?php echo number_format($total_ventas_mes, 2); ?></h3>
                    <small class="text-muted"><i class="bi bi-calendar-month"></i> Mes actual</small>
                </div>
            </div>
        </div>

        <!-- Cantidad Tickets Hoy -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-info h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase mb-2">Tickets Hoy</h6>
                    <h3 class="fw-bold text-info mb-0"><?php echo $cantidad_ventas_hoy; ?></h3>
                    <small class="text-muted"><i class="bi bi-receipt"></i> Operaciones</small>
                </div>
            </div>
        </div>

        <!-- Ingresos Caja -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-warning h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase mb-2">Ingresos Caja (Hoy)</h6>
                    <h3 class="fw-bold text-warning mb-0">$<?php echo number_format($ingresos_caja_hoy, 2); ?></h3>
                    <small class="text-muted"><i class="bi bi-cash-coin"></i> Movimientos</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Gráficos Visuales -->
    <div class="row g-4 mb-4">
        <!-- Gráfico Barras -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title fw-bold mb-0"><i class="bi bi-bar-chart-line me-2"></i>Ventas: Últimos 7 Días</h5>
                </div>
                <div class="card-body">
                    <canvas id="ventasSemanaChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Gráfico Dona -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title fw-bold mb-0"><i class="bi bi-pie-chart me-2"></i>Top 5 Productos</h5>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <div style="width: 100%; max-width: 250px;">
                        <canvas id="topProductosChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Gestión Rápida de Artículos -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title fw-bold mb-0 text-primary">
                        <i class="bi bi-pencil-square me-2"></i>Gestión Rápida de Artículos
                    </h5>
                    <small class="text-muted">Busca y edita costo, precio o stock rápidamente.</small>
                </div>
                <div class="card-body">
                    <!-- Buscador -->
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="buscadorArticulos" placeholder="Buscar por código o descripción...">
                        <button class="btn btn-primary" type="button" id="btnBuscar">Buscar</button>
                    </div>

                    <!-- Tabla de Resultados -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Cód.</th>
                                    <th>Descripción</th>
                                    <th style="width: 100px;">Stock</th>
                                    <th style="width: 120px;">Costo ($)</th>
                                    <th style="width: 120px;">Venta ($)</th>
                                    <th style="width: 80px;">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tablaResultadosArticulos">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Ingresa un término para buscar.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Sección de Alertas -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-sm border-0 border-top border-danger border-3">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title fw-bold text-danger mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Alerta de Stock Bajo
                        </h5>
                        <small class="text-muted">Mostrando <?php echo count($alertas_stock); ?> de <?php echo $total_alertas; ?> alertas</small>
                    </div>
                    <a href="index.php?view=stock_original" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-list-ul me-1"></i>Ver Inventario Completo
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Producto</th>
                                    <th class="text-center">Stock Actual</th>
                                    <th class="text-end pe-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($alertas_stock) > 0): ?>
                                    <?php foreach ($alertas_stock as $alerta): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold"><?php echo htmlspecialchars($alerta['descripcion']); ?></div>
                                                <small class="text-muted">Cód: <?php echo htmlspecialchars($alerta['codigo']); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger rounded-pill px-3">
                                                    <?php echo $alerta['stock_actual']; ?> Unid.
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-primary btn-reabastecer" 
    data-id="<?php echo $alerta['id']; ?>"
    data-nombre="<?php echo htmlspecialchars($alerta['descripcion']); ?>"
    data-costo="<?php echo $alerta['precio_costo']; ?>">
    <i class="bi bi-cart-plus"></i> Reabastecer
</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            <i class="bi bi-check-circle fs-4 d-block mb-2 text-success"></i>
                                            ¡Todo en orden! No hay productos con stock crítico.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <div class="card-footer bg-white border-0 py-3">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <!-- Botón Anterior -->
                            <li class="page-item <?php echo ($pagina_actual_alertas <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $pagina_actual_alertas - 1; ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <!-- Números de Página -->
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <li class="page-item <?php echo ($i == $pagina_actual_alertas) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Botón Siguiente -->
                            <li class="page-item <?php echo ($pagina_actual_alertas >= $total_paginas) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $pagina_actual_alertas + 1; ?>" aria-label="Siguiente">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>



</div>

<!-- Modal de Reabastecimiento Rápido -->
<div class="modal fade" id="modalReabastecer" tabindex="-1" aria-labelledby="modalLabelReabastecer" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalLabelReabastecer"><i class="bi bi-box-seam me-2"></i>Reabastecer Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formReabastecer">
                    <input type="hidden" id="id_articulo_reabastecer" name="id_articulo">
                    
                    <div class="mb-3">
                        <label for="nombre_producto_modal" class="form-label fw-bold">Producto</label>
                        <input type="text" class="form-control-plaintext" id="nombre_producto_modal" readonly>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cantidad_reabastecer" class="form-label">Cantidad a Agregar</label>
                            <input type="number" class="form-control" id="cantidad_reabastecer" name="cantidad" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nuevo_costo" class="form-label">Nuevo Costo (Opcional)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="nuevo_costo" name="nuevo_costo" step="0.01" min="0">
                            </div>
                            <div class="form-text">Dejar vacío para mantener costo actual.</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarStock">Guardar Stock</button>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // --- Configuración de Gráficos ---

    // 1. Datos desde PHP
    const labelsBarras = <?php echo json_encode($labels_grafico_barras); ?>;
    const datosBarras = <?php echo json_encode($datos_grafico_barras); ?>;
    
    const labelsDona = <?php echo json_encode($labels_grafico_dona); ?>;
    const datosDona = <?php echo json_encode($datos_grafico_dona); ?>;

    // 2. Gráfico de Barras (Ventas 7 días)
    const ctxBarras = document.getElementById('ventasSemanaChart').getContext('2d');
    new Chart(ctxBarras, {
        type: 'bar',
        data: {
            labels: labelsBarras,
            datasets: [{
                label: 'Ventas ($)',
                data: datosBarras,
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return '$' + value; }
                    }
                }
            }
        }
    });

    // 3. Gráfico de Dona (Top Productos)
    const ctxDona = document.getElementById('topProductosChart').getContext('2d');
    new Chart(ctxDona, {
        type: 'doughnut',
        data: {
            labels: labelsDona,
            datasets: [{
                data: datosDona,
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF'
                ],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, padding: 20 }
                }
            }
        }
    });

    // --- Lógica de Reabastecimiento Rápido ---
    $(document).ready(function() {
        const modalReabastecer = new bootstrap.Modal(document.getElementById('modalReabastecer'));

        // Al hacer clic en "Reabastecer"
        $('.btn-reabastecer').click(function() {
            const id = $(this).data('id');
            const nombre = $(this).data('nombre');
            const costo = $(this).data('costo');

            $('#id_articulo_reabastecer').val(id);
            $('#nombre_producto_modal').val(nombre);
            $('#nuevo_costo').val(costo); // Pre-llenar con costo actual
            $('#cantidad_reabastecer').val(''); // Limpiar cantidad
            
            modalReabastecer.show();
        });

        // Guardar Stock (AJAX)
        $('#btnGuardarStock').click(function() {
            const form = $('#formReabastecer');
            
            if(!form[0].checkValidity()) {
                form[0].reportValidity();
                return;
            }

            $.ajax({
                url: 'actualizar_stock.php',
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        modalReabastecer.hide();
                        Swal.fire({
                            icon: 'success',
                            title: '¡Stock Actualizado!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload(); // Recargar para ver cambios
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error de conexión', 'error');
                }
            });
        });
    });

    // --- Lógica de Gestión Rápida de Artículos ---
    
    // Función de búsqueda
    function buscarArticulos() {
        const query = $('#buscadorArticulos').val();
        if (query.length < 2) return;

        $('#tablaResultadosArticulos').html('<tr><td colspan="6" class="text-center py-3"><div class="spinner-border text-primary" role="status"></div></td></tr>');

        $.ajax({
            url: 'buscar_articulos.php',
            type: 'GET',
            data: { q: query },
            dataType: 'json',
            success: function(articulos) {
                let html = '';
                if (articulos.length === 0) {
                    html = '<tr><td colspan="6" class="text-center text-muted py-3">No se encontraron productos.</td></tr>';
                } else {
                    articulos.forEach(art => {
                        html += `
                            <tr>
                                <td class="small text-muted">${art.codigo}</td>
                                <td>${art.descripcion}</td>
                                <td><input type="number" class="form-control form-control-sm" value="${art.stock_actual}" id="stock_${art.id}"></td>
                                <td><input type="number" class="form-control form-control-sm" value="${art.precio_costo}" step="0.01" id="costo_${art.id}"></td>
                                <td><input type="number" class="form-control form-control-sm" value="${art.precio_venta}" step="0.01" id="precio_${art.id}"></td>
                                <td>
                                    <button class="btn btn-sm btn-success btn-guardar-rapido" data-id="${art.id}">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                }
                $('#tablaResultadosArticulos').html(html);
            }
        });
    }

    // Eventos Búsqueda
    $('#btnBuscar').click(buscarArticulos);
    $('#buscadorArticulos').keypress(function(e) {
        if(e.which == 13) buscarArticulos();
    });

    // Guardar Cambios Rápido
    $(document).on('click', '.btn-guardar-rapido', function() {
        const btn = $(this);
        const id = btn.data('id');
        const stock = $(`#stock_${id}`).val();
        const costo = $(`#costo_${id}`).val();
        const precio = $(`#precio_${id}`).val();

        // Feedback visual carga
        const originalIcon = btn.html();
        btn.html('<span class="spinner-border spinner-border-sm"></span>').prop('disabled', true);

        $.ajax({
            url: 'editar_articulo_rapido.php',
            type: 'POST',
            data: { id, stock, costo, precio },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Feedback éxito temporal
                    btn.removeClass('btn-success').addClass('btn-outline-success');
                    setTimeout(() => {
                        btn.html(originalIcon).prop('disabled', false).removeClass('btn-outline-success').addClass('btn-success');
                    }, 1000);
                    
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({ icon: 'success', title: 'Guardado' });

                } else {
                    Swal.fire('Error', response.message, 'error');
                    btn.html(originalIcon).prop('disabled', false);
                }
            },
            error: function() {
                Swal.fire('Error', 'Error de conexión', 'error');
                btn.html(originalIcon).prop('disabled', false);
            }
        });
    });
</script>