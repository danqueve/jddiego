<?php require 'header.php'; 

// Cargar Clientes para el Modal de Edición
$stmt_clientes = $pdo->query("SELECT id, CONCAT(apellido, ', ', nombre) AS nombre_completo FROM clientes WHERE id != 1 ORDER BY apellido, nombre");
$clientes = $stmt_clientes->fetchAll();
?>

<!-- Contenido principal -->
<div class="container mt-5 pt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Historial de Ventas</h1>
        <a href="nueva_venta.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Nueva Venta (POS)
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaVentas" class="table table-striped table-hover" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Vendedor</th>
                            <th>Tipo Pago</th>
                            <th>Saldo</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT v.id, v.fecha, v.tipo_pago, v.total, v.saldo_pendiente, u.nombre_usuario AS vendedor, IFNULL(CONCAT(c.nombre, ' ', c.apellido), 'Consumidor Final') AS cliente FROM ventas v JOIN usuarios u ON v.id_usuario = u.id LEFT JOIN clientes c ON v.id_cliente = c.id ORDER BY v.fecha DESC";
                        $stmt = $pdo->query($sql);
                        while ($row = $stmt->fetch()) {
                            echo "<tr>";
                            echo "<td>" . $row['id'] . "</td>";
                            echo "<td>" . date("d/m/Y H:i", strtotime($row['fecha'])) . "</td>";
                            echo "<td>" . htmlspecialchars($row['cliente']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['vendedor']) . "</td>";
                            echo "<td>" . $row['tipo_pago'] . "</td>";
                            
                            // Resaltar saldo pendiente
                            $saldo_class = $row['saldo_pendiente'] > 0 ? 'text-danger fw-bold' : 'text-success';
                            echo "<td class='" . $saldo_class . "'>$" . number_format($row['saldo_pendiente'], 2) . "</td>";
                            
                            echo "<td class='fw-bold'>$" . number_format($row['total'], 2) . "</td>";
                            echo "<td>
                                    <div class='btn-group' role='group'>
                                        <button class='btn btn-warning btn-sm btnEditarVenta' data-id='" . $row['id'] . "' title='Editar Venta'><i class='bi bi-pencil'></i></button>
                                        <button class='btn btn-info btn-sm btnVerRemito' data-id='" . $row['id'] . "' title='Ver Remito'><i class='bi bi-printer'></i></button>
                                        <!-- Botón Eliminar (NUEVO) -->
                                        <button class='btn btn-danger btn-sm btnEliminarVenta' data-id='" . $row['id'] . "' title='Eliminar Venta'><i class='bi bi-trash'></i></button>
                                    </div>
                                </td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para EDITAR VENTA (Se mantiene igual) -->
<div class="modal fade" id="modalEditarVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Editar Venta #<span id="lblIdVenta"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarVenta">
                    <input type="hidden" id="edit_id_venta" name="id_venta">
                    <input type="hidden" name="accion" value="editar_venta">
                    <input type="hidden" id="edit_total_hidden" name="total">
                    <input type="hidden" id="edit_carrito_json" name="carrito">

                    <div class="row">
                        <div class="col-md-8 border-end">
                            <h6 class="fw-bold mb-3">Artículos de la Venta</h6>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="busquedaEditar" class="form-control" placeholder="Buscar para agregar artículo...">
                            </div>
                            <div id="resultadosEditar" class="list-group mb-3 shadow" style="position: absolute; z-index: 1055; width: 60%; max-height: 200px; overflow-y: auto; display: none;"></div>

                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover" id="tablaEditCarrito">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Descripción</th>
                                            <th width="90">Cant.</th>
                                            <th width="100">Precio</th>
                                            <th width="100">Subtotal</th>
                                            <th width="40"></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <h6 class="fw-bold mb-3">Datos Generales</h6>
                            <div class="mb-3">
                                <label class="form-label">Cliente</label>
                                <select id="edit_id_cliente" class="form-select" required>
                                    <option value="1">(Consumidor Final)</option>
                                    <?php foreach ($clientes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre_completo']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Método de Pago</label>
                                <select id="edit_tipo_pago" class="form-select" required>
                                    <option value="Contado">Contado</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Cuenta Corriente">Cuenta Corriente</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descuento (%)</label>
                                <div class="input-group">
                                    <input type="number" id="edit_descuento" class="form-control" min="0" max="100" value="0">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-2"><span>Subtotal:</span><span id="lblSubtotal">$0.00</span></div>
                            <div class="d-flex justify-content-between mb-2 text-danger"><span>Desc.:</span><span id="lblDescuento">-$0.00</span></div>
                            <div class="d-flex justify-content-between fs-4 fw-bold border-top pt-2"><span>Total:</span><span id="lblTotal" class="text-primary">$0.00</span></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4" form="formEditarVenta">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>

<script>
$(document).ready(function() {
    $('#tablaVentas').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" },
        "order": [[1, "desc"]]
    });

    // Ver Remito
    $('#tablaVentas').on('click', '.btnVerRemito', function() {
        window.open('remito.php?id=' + $(this).data('id'), '_blank');
    });

    // --- LÓGICA DE ELIMINAR VENTA (NUEVO) ---
    $('#tablaVentas').on('click', '.btnEliminarVenta', function() {
        var idVenta = $(this).data('id');
        
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción eliminará la venta, devolverá el stock y anulará el ingreso en caja. ¡No se puede revertir!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar venta',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'gestionar_venta.php',
                    type: 'POST',
                    data: JSON.stringify({ accion: 'eliminar_venta', id: idVenta }),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Eliminado', response.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
                    }
                });
            }
        });
    });


    // --- LÓGICA DE EDICIÓN COMPLETA ---
    var editCarrito = [];
    var currentVentaId = 0;
    var modalEditar = new bootstrap.Modal(document.getElementById('modalEditarVenta'));

    // Abrir Modal y Cargar Datos
    $('#tablaVentas').on('click', '.btnEditarVenta', function() {
        currentVentaId = $(this).data('id');
        $('#busquedaEditar').val('');
        $('#resultadosEditar').hide();

        $.ajax({
            url: 'gestionar_venta.php',
            type: 'GET',
            data: { accion: 'obtener_venta', id: currentVentaId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    var v = response.data.venta;
                    var items = response.data.detalles;

                    $('#lblIdVenta').text(v.id);
                    $('#edit_id_venta').val(v.id);
                    $('#edit_id_cliente').val(v.id_cliente);
                    $('#edit_tipo_pago').val(v.tipo_pago);
                    $('#edit_descuento').val(v.descuento_porcentaje);
                    
                    editCarrito = items.map(function(item) {
                        return {
                            id: item.id,
                            descripcion: item.descripcion,
                            precio: parseFloat(item.precio),
                            cantidad: parseInt(item.cantidad),
                            stock_max: parseInt(item.stock_max) 
                        };
                    });
                    
                    renderEditCarrito();
                    modalEditar.show();
                } else {
                    Swal.fire('Error', response.message || 'No se pudieron cargar datos', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
                Swal.fire('Error', 'Error de conexión al obtener venta.', 'error');
            }
        });
    });

    // Renderizar Carrito en el Modal
    function renderEditCarrito() {
        var tbody = $('#tablaEditCarrito tbody');
        tbody.empty();
        var subtotal = 0;

        editCarrito.forEach(function(item, index) {
            var st = item.cantidad * item.precio;
            subtotal += st;
            tbody.append(`
                <tr>
                    <td><small>${item.descripcion}</small></td>
                    <td><input type="number" class="form-control form-control-sm edit-cant" data-index="${index}" value="${item.cantidad}" min="1" max="${item.stock_max}"></td>
                    <td>$${item.precio.toFixed(2)}</td>
                    <td class="fw-bold">$${st.toFixed(2)}</td>
                    <td><button type="button" class="btn btn-danger btn-sm edit-remove py-0 px-1" data-index="${index}"><i class="bi bi-x"></i></button></td>
                </tr>
            `);
        });

        var descPorc = parseFloat($('#edit_descuento').val()) || 0;
        if(descPorc < 0) descPorc = 0; if(descPorc > 100) descPorc = 100;
        var descMonto = subtotal * (descPorc / 100);
        var total = subtotal - descMonto;

        $('#lblSubtotal').text('$' + subtotal.toFixed(2));
        $('#lblDescuento').text('-$' + descMonto.toFixed(2));
        $('#lblTotal').text('$' + total.toFixed(2));
        
        $('#edit_total_hidden').val(total.toFixed(2));
        $('#edit_carrito_json').val(JSON.stringify(editCarrito));
    }

    // Eventos Carrito
    $('#tablaEditCarrito').on('change', '.edit-cant', function() {
        var idx = $(this).data('index');
        var val = parseInt($(this).val());
        var max = parseInt($(this).attr('max'));
        if (isNaN(val) || val < 1) val = 1;
        if (val > max) { val = max; Swal.fire({toast: true, position: 'top-end', icon: 'warning', title: 'Stock máximo: '+max, showConfirmButton: false, timer: 3000}); }
        editCarrito[idx].cantidad = val;
        renderEditCarrito();
    });

    $('#tablaEditCarrito').on('click', '.edit-remove', function() {
        var idx = $(this).data('index');
        editCarrito.splice(idx, 1);
        renderEditCarrito();
    });

    $('#edit_descuento').on('change keyup', function() { renderEditCarrito(); });

    // Buscador
    $('#busquedaEditar').on('keyup', function() {
        var q = $(this).val();
        if(q.length < 2) { $('#resultadosEditar').hide(); return; }
        $.ajax({
            url: 'gestionar_venta.php',
            type: 'GET',
            data: { accion: 'buscar_articulo', query: q },
            dataType: 'json',
            success: function(res) {
                var div = $('#resultadosEditar').empty();
                if(res.data && res.data.length > 0) {
                    div.show();
                    res.data.forEach(function(art) {
                        div.append(`<a href="#" class="list-group-item list-group-item-action item-add-edit" data-id="${art.id}" data-desc="${art.descripcion}" data-precio="${art.precio_venta}" data-stock="${art.stock_actual}">
                            ${art.descripcion} - $${art.precio_venta} (Stock: ${art.stock_actual})
                        </a>`);
                    });
                } else { div.hide(); }
            }
        });
    });

    $(document).on('click', '.item-add-edit', function(e) {
        e.preventDefault();
        var nuevo = {
            id: $(this).data('id'),
            descripcion: $(this).data('desc'),
            precio: parseFloat($(this).data('precio')),
            stock_max: parseInt($(this).data('stock')),
            cantidad: 1
        };
        var ext = editCarrito.find(i => i.id == nuevo.id);
        if (ext) {
            if (ext.cantidad < ext.stock_max) ext.cantidad++;
        } else {
            if(nuevo.stock_max > 0) editCarrito.push(nuevo);
        }
        $('#busquedaEditar').val('');
        $('#resultadosEditar').hide();
        renderEditCarrito();
    });

    $(document).click(function(e) {
        if (!$(e.target).closest('#busquedaEditar, #resultadosEditar').length) $('#resultadosEditar').hide();
    });

    // GUARDAR CAMBIOS (AJAX CON JSON PURO)
    $('#formEditarVenta').submit(function(e) {
        e.preventDefault();
        
        if (editCarrito.length === 0) { Swal.fire('Error', 'La venta debe tener artículos.', 'error'); return; }
        
        var tipo = $('#edit_tipo_pago').val();
        var cliente = $('#edit_id_cliente').val();
        if (tipo === 'Cuenta Corriente' && cliente == 1) {
            Swal.fire('Error', 'Consumidor Final no puede tener Cta. Corriente.', 'warning');
            return;
        }

        var datosEnvio = {
            accion: 'editar_venta',
            id_venta: currentVentaId,
            id_cliente: cliente,
            tipo_pago: tipo,
            descuento: $('#edit_descuento').val(),
            total: $('#edit_total_hidden').val(),
            carrito: editCarrito 
        };
        
        $.ajax({
            url: 'gestionar_venta.php',
            type: 'POST',
            data: JSON.stringify(datosEnvio), 
            contentType: 'application/json; charset=utf-8', 
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    modalEditar.hide();
                    Swal.fire({ icon: 'success', title: 'Actualizado', text: response.message, timer: 1500, showConfirmButton: false }).then(() => location.reload());
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function(xhr) { 
                console.error(xhr.responseText);
                Swal.fire('Error', 'Error de conexión.', 'error'); 
            }
        });
    });
});
</script>