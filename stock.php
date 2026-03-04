<?php
require 'header.php';

// Cargar proveedores para el modal
$proveedores = $pdo->query("SELECT id, nombre_proveedor FROM proveedores ORDER BY nombre_proveedor")->fetchAll();

// Cargar todos los artículos con proveedor
$articulos = $pdo->query("
    SELECT a.*, IFNULL(p.nombre_proveedor, '-') AS nombre_proveedor
    FROM articulos a
    LEFT JOIN proveedores p ON a.id_proveedor = p.id
    ORDER BY a.descripcion ASC
")->fetchAll();

$total_articulos = count($articulos);
$total_unidades  = array_sum(array_column($articulos, 'stock_actual'));
$sin_stock       = count(array_filter($articulos, fn($a) => $a['stock_actual'] == 0));
$stock_bajo      = count(array_filter($articulos, fn($a) => $a['stock_actual'] > 0 && $a['stock_actual'] < 5));
?>

<div class="container mt-4 pb-5">

    <!-- Encabezado -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-0"><i class="bi bi-box-seam me-2 text-success"></i>Gestión de Artículos</h2>
            <small class="text-muted">
                <strong><?= $total_articulos ?></strong> artículos &middot;
                <strong><?= number_format($total_unidades) ?></strong> unidades en stock &middot;
                <span class="text-danger fw-bold"><?= $sin_stock ?> sin stock</span>
                <?php if ($stock_bajo > 0): ?>
                    &middot; <span class="text-warning fw-bold"><?= $stock_bajo ?> con stock bajo</span>
                <?php endif; ?>
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="stock_print.php" target="_blank" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-pdf me-1"></i>Exportar PDF
            </a>
            <button class="btn btn-primary" id="btnNuevoArticulo">
                <i class="bi bi-plus-circle me-1"></i>Nuevo Artículo
            </button>
        </div>
    </div>

    <!-- Tabla principal -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0 p-md-3">
            <div class="table-responsive">
                <table id="tablaArticulos" class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th class="text-center">Stock</th>
                            <th class="text-end">Costo</th>
                            <th class="text-end">Precio Venta</th>
                            <th>Proveedor</th>
                            <th class="text-center" style="width:100px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articulos as $art):
                            if ($art['stock_actual'] == 0) {
                                $badge = 'bg-danger';
                                $label = 'Sin stock';
                            } elseif ($art['stock_actual'] < 5) {
                                $badge = 'bg-warning text-dark';
                                $label = $art['stock_actual'] . ' unid.';
                            } else {
                                $badge = 'bg-success';
                                $label = $art['stock_actual'] . ' unid.';
                            }
                        ?>
                            <tr>
                                <td class="text-muted small fw-semibold"><?= htmlspecialchars($art['codigo']) ?></td>
                                <td><?= htmlspecialchars($art['descripcion']) ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $badge ?> rounded-pill px-3"><?= $label ?></span>
                                </td>
                                <td class="text-end text-muted">$<?= number_format($art['precio_costo'], 2) ?></td>
                                <td class="text-end fw-bold text-primary">$<?= number_format($art['precio_venta'], 2) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($art['nombre_proveedor']) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary btn-editar me-1"
                                        title="Editar"
                                        data-id="<?= $art['id'] ?>"
                                        data-codigo="<?= htmlspecialchars($art['codigo'], ENT_QUOTES) ?>"
                                        data-descripcion="<?= htmlspecialchars($art['descripcion'], ENT_QUOTES) ?>"
                                        data-stock="<?= $art['stock_actual'] ?>"
                                        data-costo="<?= $art['precio_costo'] ?>"
                                        data-precio="<?= $art['precio_venta'] ?>"
                                        data-proveedor="<?= $art['id_proveedor'] ?? '' ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-eliminar"
                                        title="Eliminar"
                                        data-id="<?= $art['id'] ?>"
                                        data-nombre="<?= htmlspecialchars($art['descripcion'], ENT_QUOTES) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ── Modal Crear / Editar Artículo ── -->
<div class="modal fade" id="modalArticulo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalArticuloLabel">Artículo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formArticulo" novalidate>
                    <input type="hidden" name="accion"      id="inputAccion"     value="crear">
                    <input type="hidden" name="id_articulo" id="inputIdArticulo" value="">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="codigo" id="inputCodigo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="descripcion" id="inputDescripcion" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <label class="form-label fw-semibold">Stock</label>
                            <input type="number" class="form-control" name="stock_actual" id="inputStock" min="0" value="0" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Costo ($)</label>
                            <input type="number" class="form-control" name="precio_costo" id="inputCosto" step="0.01" min="0" value="0" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Precio Venta ($)</label>
                            <input type="number" class="form-control" name="precio_venta" id="inputPrecio" step="0.01" min="0" value="0" required>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Proveedor</label>
                        <select class="form-select" name="id_proveedor" id="selectProveedor">
                            <option value="">— Sin proveedor —</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre_proveedor']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarArticulo">
                    <i class="bi bi-save me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>

<script>
$(document).ready(function () {

    // ── DataTable ──
    $('#tablaArticulos').DataTable({
        pageLength: 25,
        order: [[1, 'asc']],
        language: {
            search:           'Buscar:',
            lengthMenu:       'Mostrar _MENU_ registros',
            info:             'Mostrando _START_ a _END_ de _TOTAL_ artículos',
            infoEmpty:        'Sin artículos',
            infoFiltered:     '(filtrado de _MAX_ totales)',
            zeroRecords:      'No se encontraron artículos',
            paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' }
        }
    });

    const modal = new bootstrap.Modal(document.getElementById('modalArticulo'));

    // ── Abrir modal para CREAR ──
    $('#btnNuevoArticulo').click(function () {
        $('#modalArticuloLabel').text('Nuevo Artículo');
        $('#inputAccion').val('crear');
        $('#formArticulo')[0].reset();
        $('#inputIdArticulo').val('');
        $('#inputStock').val(0);
        $('#inputCosto').val(0);
        $('#inputPrecio').val(0);
        modal.show();
    });

    // ── Abrir modal para EDITAR ──
    $(document).on('click', '.btn-editar', function () {
        const btn = $(this);
        $('#modalArticuloLabel').text('Editar Artículo');
        $('#inputAccion').val('editar');
        $('#inputIdArticulo').val(btn.data('id'));
        $('#inputCodigo').val(btn.data('codigo'));
        $('#inputDescripcion').val(btn.data('descripcion'));
        $('#inputStock').val(btn.data('stock'));
        $('#inputCosto').val(btn.data('costo'));
        $('#inputPrecio').val(btn.data('precio'));
        $('#selectProveedor').val(btn.data('proveedor') || '');
        modal.show();
    });

    // ── Guardar (crear o editar) ──
    $('#btnGuardarArticulo').click(function () {
        if (!$('#formArticulo')[0].checkValidity()) {
            $('#formArticulo')[0].reportValidity();
            return;
        }
        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Guardando...');

        $.ajax({
            url: 'gestionar_articulo.php',
            type: 'POST',
            data: $('#formArticulo').serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    modal.hide();
                    Swal.fire({ icon: 'success', title: '¡Listo!', text: res.message, timer: 1500, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                    btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Guardar');
                }
            },
            error: () => {
                Swal.fire('Error', 'Error de conexión', 'error');
                btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Guardar');
            }
        });
    });

    // ── Eliminar ──
    $(document).on('click', '.btn-eliminar', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');
        Swal.fire({
            title: '¿Eliminar artículo?',
            html: `<strong>${nombre}</strong><br><small class="text-muted">Esta acción no se puede deshacer.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonText: 'Cancelar',
            confirmButtonText: 'Sí, eliminar'
        }).then(result => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: 'gestionar_articulo.php',
                type: 'POST',
                data: { accion: 'eliminar', id_articulo: id },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1200, showConfirmButton: false })
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: () => Swal.fire('Error', 'Error de conexión', 'error')
            });
        });
    });

});
</script>
