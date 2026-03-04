<?php
require 'header.php';

// ── Configuración del Dashboard ──────────────────────────
// Umbral de stock crítico: productos con stock por debajo
// de este valor aparecerán en el panel de alertas.
define('STOCK_MINIMO', 5);
// ─────────────────────────────────────────────────────────

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
$stmt = $pdo->prepare("SELECT SUM(monto) as total_ingresos FROM movimientos_caja WHERE tipo_movimiento = 'Ingreso' AND DATE(fecha) = ?");
$stmt->execute([$hoy]);
$ingresos_caja_hoy = $stmt->fetch()['total_ingresos'] ?? 0;

// --- E. Datos para Gráfico de Barras (Últimos 7 días) ---
$fecha_inicio = date('Y-m-d', strtotime('-6 days'));
$stmt = $pdo->prepare("
    SELECT DATE(fecha) as dia, SUM(total) as total_venta 
    FROM ventas 
    WHERE DATE(fecha) >= ? 
    GROUP BY DATE(fecha) 
    ORDER BY DATE(fecha) ASC
");
$stmt->execute([$fecha_inicio]);
$ventas_semana = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$datos_grafico_barras = [];
$labels_grafico_barras = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $labels_grafico_barras[] = date('d/m', strtotime($dia));
    $datos_grafico_barras[] = $ventas_semana[$dia] ?? 0;
}

// --- F. Datos para Gráfico de Dona (Top 5 Productos) ---
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
$alertas_por_pagina = 10;
$pagina_actual_alertas = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($pagina_actual_alertas < 1)
    $pagina_actual_alertas = 1;
$offset = ($pagina_actual_alertas - 1) * $alertas_por_pagina;

$stmt_count = $pdo->query("SELECT COUNT(*) FROM articulos WHERE stock_actual < " . STOCK_MINIMO);
$total_alertas = $stmt_count->fetchColumn();
$total_paginas = ceil($total_alertas / $alertas_por_pagina);

$stmt = $pdo->prepare("SELECT * FROM articulos WHERE stock_actual < " . STOCK_MINIMO . " ORDER BY stock_actual ASC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $alertas_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$alertas_stock = $stmt->fetchAll();

?>

<style>
    /* ── KPI Cards ── */
    .kpi-card {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0, 0, 0, .12) !important;
    }

    .kpi-card .kpi-icon {
        font-size: 2.4rem;
        opacity: .85;
    }

    .kpi-card .kpi-value {
        font-size: 1.9rem;
        font-weight: 700;
        letter-spacing: -.5px;
    }

    .kpi-card .kpi-label {
        font-size: .72rem;
        letter-spacing: .08em;
        text-transform: uppercase;
        font-weight: 600;
    }

    /* Gradientes de KPI */
    .kpi-green {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    }

    .kpi-blue {
        background: linear-gradient(135deg, #cce5ff 0%, #b8daff 100%);
    }

    .kpi-cyan {
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    }

    .kpi-yellow {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
    }

    /* ── Section badge ── */
    .section-badge {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .07em;
        text-transform: uppercase;
        padding: .3rem .75rem;
        border-radius: 50px;
    }

    /* ── Chart cards ── */
    .chart-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
        transition: box-shadow .2s;
    }

    .chart-card:hover {
        box-shadow: 0 6px 22px rgba(0, 0, 0, .12);
    }

    /* ── Stock badge dinámico ── */
    .badge-stock-cero {
        background-color: #dc3545 !important;
    }

    .badge-stock-critico {
        background-color: #fd7e14 !important;
    }

    .badge-stock-bajo {
        background-color: #ffc107 !important;
        color: #333 !important;
    }

    /* ── Dark mode overrides ── */
    .dark-mode .kpi-green {
        background: linear-gradient(135deg, #1a3a22 0%, #16332a 100%);
        color: #d4edda;
    }

    .dark-mode .kpi-blue {
        background: linear-gradient(135deg, #1a2a3a 0%, #16283a 100%);
        color: #cce5ff;
    }

    .dark-mode .kpi-cyan {
        background: linear-gradient(135deg, #1a3136 0%, #162d31 100%);
        color: #d1ecf1;
    }

    .dark-mode .kpi-yellow {
        background: linear-gradient(135deg, #3a3000 0%, #2e2700 100%);
        color: #fff3cd;
    }

    .dark-mode .chart-card {
        background-color: #2c3034;
    }

    .dark-mode .chart-card .card-header {
        background-color: #2c3034 !important;
        color: #f8f9fa;
    }
</style>

<!-- Contenido Principal -->
<div class="container mt-4 pb-5">

    <!-- ── Encabezado del dashboard ── -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard Gerencial</h2>
            <small class="text-muted">Estado del negocio al <?php echo date('d/m/Y H:i'); ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="stock_print.php" target="_blank" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-pdf me-1"></i> Exportar Stock PDF
            </a>
            <a href="nueva_venta.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Nueva Venta
            </a>
        </div>
    </div>

    <!-- ── 1. Tarjetas KPI ── -->
    <div class="mb-2">
        <span class="section-badge bg-primary bg-opacity-10 text-primary"><i class="bi bi-bar-chart-fill"></i> Resumen
            del día</span>
    </div>
    <div class="row g-3 mb-4">

        <!-- Ventas Hoy -->
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-green shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="kpi-icon text-success"><i class="bi bi-graph-up-arrow"></i></div>
                    <div>
                        <div class="kpi-label text-success">Ventas Hoy</div>
                        <div class="kpi-value text-success">$<?php echo number_format($total_ventas_hoy, 2); ?></div>
                        <small class="text-muted"><i class="bi bi-calendar-event"></i>
                            <?php echo date('d/m/Y'); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ventas Mes -->
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-blue shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="kpi-icon text-primary"><i class="bi bi-calendar-month"></i></div>
                    <div>
                        <div class="kpi-label text-primary">Ventas del Mes</div>
                        <div class="kpi-value text-primary">$<?php echo number_format($total_ventas_mes, 2); ?></div>
                        <small class="text-muted"><i class="bi bi-calendar3"></i> Mes actual</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets Hoy -->
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-cyan shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="kpi-icon text-info"><i class="bi bi-receipt-cutoff"></i></div>
                    <div>
                        <div class="kpi-label text-info">Tickets Hoy</div>
                        <div class="kpi-value text-info"><?php echo $cantidad_ventas_hoy; ?></div>
                        <small class="text-muted"><i class="bi bi-receipt"></i> Operaciones</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ingresos Caja -->
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-yellow shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="kpi-icon text-warning"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="kpi-label text-warning">Ingresos Caja</div>
                        <div class="kpi-value text-warning">$<?php echo number_format($ingresos_caja_hoy, 2); ?></div>
                        <small class="text-muted"><i class="bi bi-cash-coin"></i> Movimientos</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 2. Gráficos ── -->
    <div class="mb-2">
        <span class="section-badge bg-info bg-opacity-10 text-info"><i class="bi bi-pie-chart-fill"></i> Análisis
            Visual</span>
    </div>
    <div class="row g-4 mb-4">
        <!-- Gráfico Barras -->
        <div class="col-md-8">
            <div class="card chart-card h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title fw-bold mb-0"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Ventas:
                        Últimos 7 Días</h5>
                </div>
                <div class="card-body">
                    <canvas id="ventasSemanaChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Gráfico Dona -->
        <div class="col-md-4">
            <div class="card chart-card h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title fw-bold mb-0"><i class="bi bi-pie-chart me-2 text-warning"></i>Top 5 Productos
                    </h5>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <div style="width: 100%; max-width: 250px;">
                        <canvas id="topProductosChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 3. Gestión Rápida de Artículos ── -->
    <div class="mb-2">
        <span class="section-badge bg-success bg-opacity-10 text-success"><i class="bi bi-pencil-square"></i> Gestión
            Rápida</span>
    </div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card chart-card">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title fw-bold mb-0 text-primary">
                        <i class="bi bi-pencil-square me-2"></i>Gestión Rápida de Artículos
                    </h5>
                    <small class="text-muted">Buscá por código o descripción y editá descripción, costo, precio o stock al instante.
                        <span class="text-primary">(mín. 2 caracteres)</span></small>
                </div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="buscadorArticulos"
                            placeholder="Buscar por código o descripción...">
                        <button class="btn btn-primary" type="button" id="btnBuscar">Buscar</button>
                    </div>

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
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="bi bi-search fs-4 d-block mb-2 opacity-25"></i>
                                        Ingresá un término para buscar artículos.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 4. Alerta de Stock Bajo ── -->
    <div class="mb-2">
        <span class="section-badge bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-triangle-fill"></i>
            Alertas de Stock</span>
    </div>
    <div class="row mb-5">
        <div class="col-12">
            <div class="card chart-card border-top border-danger border-3">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title fw-bold text-danger mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Stock Crítico
                        </h5>
                        <small class="text-muted">
                            Mostrando <strong><?php echo count($alertas_stock); ?></strong> de
                            <strong><?php echo $total_alertas; ?></strong> productos bajo el mínimo
                        </small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="stock_print.php" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                        </a>
                        <a href="index.php?view=stock_original" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-list-ul me-1"></i>Ver Inventario
                        </a>
                    </div>
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
                                    <?php foreach ($alertas_stock as $alerta):
                                        // Badge dinámico según nivel de stock
                                        if ($alerta['stock_actual'] == 0) {
                                            $badge_class = 'badge-stock-cero';
                                            $badge_label = 'Sin stock';
                                        } elseif ($alerta['stock_actual'] < 3) {
                                            $badge_class = 'badge-stock-critico';
                                            $badge_label = $alerta['stock_actual'] . ' Unid.';
                                        } else {
                                            $badge_class = 'badge-stock-bajo';
                                            $badge_label = $alerta['stock_actual'] . ' Unid.';
                                        }
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold"><?php echo htmlspecialchars($alerta['descripcion']); ?>
                                                </div>
                                                <small class="text-muted">Cód:
                                                    <?php echo htmlspecialchars($alerta['codigo']); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?php echo $badge_class; ?> rounded-pill px-3">
                                                    <?php echo $badge_label; ?>
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
                                            <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
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
                                <li class="page-item <?php echo ($pagina_actual_alertas <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $pagina_actual_alertas - 1; ?>"
                                        aria-label="Anterior">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <li class="page-item <?php echo ($i == $pagina_actual_alertas) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li
                                    class="page-item <?php echo ($pagina_actual_alertas >= $total_paginas) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $pagina_actual_alertas + 1; ?>"
                                        aria-label="Siguiente">
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
                <h5 class="modal-title" id="modalLabelReabastecer"><i class="bi bi-box-seam me-2"></i>Reabastecer
                    Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formReabastecer">
                    <input type="hidden" id="id_articulo_reabastecer" name="id_articulo">

                    <div class="mb-3">
                        <label for="nombre_producto_modal" class="form-label fw-bold">Producto</label>
                        <input type="text" class="form-control-plaintext fw-bold" id="nombre_producto_modal" readonly>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cantidad_reabastecer" class="form-label">Cantidad a Agregar</label>
                            <input type="number" class="form-control" id="cantidad_reabastecer" name="cantidad" min="1"
                                required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nuevo_costo" class="form-label">Nuevo Costo (Opcional)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="nuevo_costo" name="nuevo_costo"
                                    step="0.01" min="0">
                            </div>
                            <div class="form-text">Dejar vacío para mantener costo actual.</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarStock"><i
                        class="bi bi-save me-1"></i>Guardar Stock</button>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // --- Datos desde PHP ---
    const labelsBarras = <?php echo json_encode($labels_grafico_barras); ?>;
    const datosBarras = <?php echo json_encode($datos_grafico_barras); ?>;
    const labelsDona = <?php echo json_encode($labels_grafico_dona); ?>;
    const datosDona = <?php echo json_encode($datos_grafico_dona); ?>;

    // --- Gráfico de Barras ---
    const ctxBarras = document.getElementById('ventasSemanaChart').getContext('2d');
    new Chart(ctxBarras, {
        type: 'bar',
        data: {
            labels: labelsBarras,
            datasets: [{
                label: 'Ventas ($)',
                data: datosBarras,
                backgroundColor: 'rgba(54, 162, 235, 0.55)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: value => '$' + value }
                }
            }
        }
    });

    // --- Gráfico de Dona ---
    const ctxDona = document.getElementById('topProductosChart').getContext('2d');
    new Chart(ctxDona, {
        type: 'doughnut',
        data: {
            labels: labelsDona,
            datasets: [{
                data: datosDona,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 16, font: { size: 11 } }
                }
            }
        }
    });

    // --- Reabastecimiento Rápido ---
    $(document).ready(function () {
        const modalReabastecer = new bootstrap.Modal(document.getElementById('modalReabastecer'));

        $('.btn-reabastecer').click(function () {
            $('#id_articulo_reabastecer').val($(this).data('id'));
            $('#nombre_producto_modal').val($(this).data('nombre'));
            $('#nuevo_costo').val($(this).data('costo'));
            $('#cantidad_reabastecer').val('');
            modalReabastecer.show();
        });

        $('#btnGuardarStock').click(function () {
            const form = $('#formReabastecer');
            if (!form[0].checkValidity()) { form[0].reportValidity(); return; }

            $.ajax({
                url: 'actualizar_stock.php',
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        modalReabastecer.hide();
                        Swal.fire({ icon: 'success', title: '¡Stock Actualizado!', text: response.message, timer: 1500, showConfirmButton: false })
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: () => Swal.fire('Error', 'Error de conexión', 'error')
            });
        });
    });

    // --- Gestión Rápida de Artículos ---
    function buscarArticulos() {
        const query = $('#buscadorArticulos').val().trim();
        if (query.length < 2) {
            $('#tablaResultadosArticulos').html('<tr><td colspan="6" class="text-center text-warning py-3"><i class="bi bi-info-circle me-1"></i>Ingresá al menos 2 caracteres.</td></tr>');
            return;
        }

        $('#tablaResultadosArticulos').html('<tr><td colspan="6" class="text-center py-3"><div class="spinner-border text-primary" role="status"></div></td></tr>');

        $.ajax({
            url: 'buscar_articulos.php',
            type: 'GET',
            data: { q: query },
            dataType: 'json',
            success: function (articulos) {
                let html = '';
                if (articulos.length === 0) {
                    html = '<tr><td colspan="6" class="text-center text-muted py-3"><i class="bi bi-search me-1"></i>No se encontraron productos.</td></tr>';
                } else {
                    articulos.forEach(art => {
                        const desc = art.descripcion.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        const cod = art.codigo.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        html += `
                            <tr>
                                <td class="small text-muted">${cod}</td>
                                <td><input type="text" class="form-control form-control-sm" value="${desc}" id="desc_${art.id}" style="min-width:160px;"></td>
                                <td><input type="number" class="form-control form-control-sm" value="${art.stock_actual}" id="stock_${art.id}"></td>
                                <td><input type="number" class="form-control form-control-sm" value="${art.precio_costo}" step="0.01" id="costo_${art.id}"></td>
                                <td><input type="number" class="form-control form-control-sm" value="${art.precio_venta}" step="0.01" id="precio_${art.id}"></td>
                                <td>
                                    <button class="btn btn-sm btn-success btn-guardar-rapido" data-id="${art.id}" title="Guardar cambios">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                }
                $('#tablaResultadosArticulos').html(html);
            },
            error: () => {
                $('#tablaResultadosArticulos').html('<tr><td colspan="6" class="text-center text-danger py-3"><i class="bi bi-wifi-off me-1"></i>Error de conexión.</td></tr>');
            }
        });
    }

    $('#btnBuscar').click(buscarArticulos);
    $('#buscadorArticulos').on('keypress', function (e) {
        if (e.which == 13) buscarArticulos();
    });

    $(document).on('click', '.btn-guardar-rapido', function () {
        const btn = $(this);
        const id = btn.data('id');
        const descripcion = $(`#desc_${id}`).val().trim();
        const stock = $(`#stock_${id}`).val();
        const costo = $(`#costo_${id}`).val();
        const precio = $(`#precio_${id}`).val();

        if (!descripcion) {
            Swal.fire('Atención', 'La descripción no puede estar vacía.', 'warning');
            return;
        }

        const originalIcon = btn.html();
        btn.html('<span class="spinner-border spinner-border-sm"></span>').prop('disabled', true);

        $.ajax({
            url: 'editar_articulo_rapido.php',
            type: 'POST',
            data: { id, descripcion, stock, costo, precio },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success') {
                    btn.removeClass('btn-success').addClass('btn-outline-success');
                    setTimeout(() => {
                        btn.html(originalIcon).prop('disabled', false)
                            .removeClass('btn-outline-success').addClass('btn-success');
                    }, 1200);
                    Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true })
                        .fire({ icon: 'success', title: '¡Guardado!' });
                } else {
                    Swal.fire('Error', response.message, 'error');
                    btn.html(originalIcon).prop('disabled', false);
                }
            },
            error: () => {
                Swal.fire('Error', 'Error de conexión', 'error');
                btn.html(originalIcon).prop('disabled', false);
            }
        });
    });
</script>