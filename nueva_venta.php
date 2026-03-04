<?php
require 'header.php';

// Cargar Clientes para el <select>
$stmt_clientes = $pdo->query("SELECT id, CONCAT(apellido, ', ', nombre) AS nombre_completo, dni 
                             FROM clientes 
                             WHERE id != 1 -- Excluimos al Consumidor Final de la lista Cta Cte
                             ORDER BY apellido, nombre");
$clientes = $stmt_clientes->fetchAll();

// Cargamos TODOS los artículos con stock para el modal
$stmt_articulos = $pdo->query("
    SELECT id, codigo, descripcion, precio_venta, stock_actual 
    FROM articulos 
    WHERE stock_actual > 0 
    ORDER BY descripcion
");
$articulos_para_modal = $stmt_articulos->fetchAll();
?>

<!-- Contenido principal -->
<div class="container mt-5 pt-5">

    <!-- 1. Cabecera de la Página -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Nueva Venta (POS)</h1>
        <a href="ventas.php" class="btn btn-secondary">
            <i class="bi bi-x-circle me-2"></i>Cancelar y Volver
        </a>
    </div>

    <!-- 2. Layout del POS (2 columnas) -->
    <div class="row g-4">

        <!-- Columna Izquierda: Búsqueda y Carrito -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h5 class="card-title">1. Buscar Artículo</h5>

                    <!-- Botón que abre el modal de búsqueda -->
                    <button type="button" class="btn btn-primary btn-lg w-100" id="btnAbrirModalBuscar">
                        <i class="bi bi-search me-2"></i>Buscar Artículo...
                    </button>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title">2. Carrito de Venta</h5>
                    <div class="table-responsive" style="min-height: 300px;">
                        <table id="tablaCarrito" class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Descripción</th>
                                    <th style="width: 100px;">Cantidad</th>
                                    <th style="width: 120px;">Precio</th>
                                    <th style="width: 120px;">Subtotal</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Los artículos se añaden aquí con JS -->
                                <tr id="filaVaciaCarrito">
                                    <td colspan="5" class="text-center text-muted pt-5">El carrito está vacío</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Cliente y Pago -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 sticky-top" style="top: 100px;">
                <div class="card-body p-4">
                    <h5 class="card-title">3. Cliente y Pago</h5>
                    <form id="formProcesarVenta">

                        <div class="mb-3">
                            <label for="selectCliente" class="form-label">Cliente</label>
                            <select id="selectCliente" name="id_cliente" class="form-select" required>
                                <option value="1" selected>(Consumidor Final)</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>">
                                        <?php echo htmlspecialchars($cliente['nombre_completo']) . " (DNI: " . htmlspecialchars($cliente['dni']) . ")"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo de Pago</label>
                            <select id="selectTipoPago" name="tipo_pago" class="form-select" required>
                                <option value="Contado">Contado</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Cuenta Corriente" selected>Cuenta Corriente</option>
                            </select>
                        </div>

                        <!-- ==== NUEVO CAMPO DE DESCUENTO ==== -->
                        <div class="mb-3">
                            <label for="inputDescuento" class="form-label">Descuento (%)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="inputDescuento" name="descuento" value="0"
                                    min="0" max="100" step="1">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>

                        <hr>

                        <!-- Resumen de Total -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Subtotal:</span>
                            <span class="fw-bold" id="textoSubtotal">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Descuento:</span>
                            <span class="fw-bold text-danger" id="textoMontoDescuento">-$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center fs-4 border-top pt-2">
                            <strong class="text-dark">TOTAL:</strong>
                            <strong class="text-primary" id="textoTotalVenta">$0.00</strong>
                        </div>

                        <!-- Campos ocultos para el total y el carrito -->
                        <input type="hidden" id="inputTotalVenta" name="total" value="0">
                        <input type="hidden" id="inputCarritoJSON" name="carrito">
                        <input type="hidden" name="accion" value="procesar_venta">

                        <div class="d-grid mt-4">
                            <button type="submit" id="btnFinalizarVenta" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Finalizar Venta
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

    </div> <!-- fin .row -->
</div>


<!-- ========================================================== -->
<!--     Modal para Buscar Artículos                             -->
<!-- ========================================================== -->
<div class="modal fade" id="modalBuscarArticulo" tabindex="-1" aria-labelledby="modalLabelBuscar" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabelBuscar">Buscar Artículo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="input-group input-group-lg mb-3">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="inputBusquedaModal" class="form-control"
                        placeholder="Filtrar por código o descripción...">
                </div>

                <div id="resultadosBusquedaModal" class="list-group" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($articulos_para_modal as $item): ?>
                        <a href="#" class="list-group-item list-group-item-action item-busqueda"
                            data-id="<?php echo $item['id']; ?>"
                            data-descripcion="<?php echo htmlspecialchars($item['descripcion']); ?>"
                            data-precio="<?php echo $item['precio_venta']; ?>"
                            data-stock="<?php echo $item['stock_actual']; ?>"
                            data-texto-busqueda="<?php echo strtolower(htmlspecialchars($item['descripcion'] . ' ' . $item['codigo'])); ?>">

                            <strong><?php echo htmlspecialchars($item['descripcion']); ?></strong>
                            [<?php echo htmlspecialchars($item['codigo']); ?>] -
                            <span
                                class="text-success fw-bold">$<?php echo number_format($item['precio_venta'], 2); ?></span>
                            (Stock: <?php echo $item['stock_actual']; ?>)
                        </a>
                    <?php endforeach; ?>

                    <?php if (empty($articulos_para_modal)): ?>
                        <p class="text-center text-muted">No hay artículos con stock disponibles.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>


<!-- ================================================================== -->
<!--          El Footer DEBE ir ANTES del script 
<!-- ================================================================== -->
<?php require 'footer.php'; ?>


<!-- 4. JavaScript Específico de la Página -->
<script>
    $(document).ready(function () {

        let carrito = [];
        var modalBuscar = new bootstrap.Modal($('#modalBuscarArticulo'));

        // 1. Abrir el modal de búsqueda
        $('#btnAbrirModalBuscar').click(function () {
            modalBuscar.show();
        });

        // 2. Cuando el modal se muestre, limpiar y enfocar
        $('#modalBuscarArticulo').on('shown.bs.modal', function () {
            $('#inputBusquedaModal').val('').trigger('focus');
            $('#resultadosBusquedaModal .item-busqueda').show();
            $('#mensajeNoResultados').remove();
        });

        // 3. Lógica de Filtro Local
        $('#inputBusquedaModal').on('keyup', function () {
            let query = $(this).val().toLowerCase().trim();
            let $items = $('#resultadosBusquedaModal .item-busqueda');
            let $noResultados = $('#mensajeNoResultados');
            let count = 0;

            $items.each(function () {
                let textoItem = $(this).data('texto-busqueda');
                if (textoItem.includes(query)) {
                    $(this).show();
                    count++;
                } else {
                    $(this).hide();
                }
            });

            if (count === 0 && query.length > 0) {
                if ($noResultados.length === 0) {
                    $('#resultadosBusquedaModal').append('<p id="mensajeNoResultados" class="text-center text-muted p-3">No se encontraron artículos.</p>');
                }
            } else {
                $noResultados.remove();
            }
        });

        // 4. Añadir Artículo al Carrito
        $('#resultadosBusquedaModal').on('click', '.item-busqueda', function (e) {
            e.preventDefault();

            let item = {
                id: $(this).data('id'),
                descripcion: $(this).data('descripcion'),
                precio: parseFloat($(this).data('precio')),
                stock_max: parseInt($(this).data('stock')),
                cantidad: 1
            };

            let itemExistente = carrito.find(i => i.id === item.id);

            if (itemExistente) {
                if (itemExistente.cantidad < item.stock_max) {
                    itemExistente.cantidad++;
                } else {
                    Swal.fire('Stock Límite', 'No puedes añadir más de este artículo (Stock disponible: ' + item.stock_max + ')', 'warning');
                }
            } else {
                if (item.stock_max > 0) {
                    carrito.push(item);
                } else {
                    Swal.fire('Sin Stock', 'Este artículo ya no tiene stock disponible.', 'error');
                }
            }

            modalBuscar.hide();
            actualizarCarritoVisual();
        });

        // --- 5. Actualizar Carrito (Visual y Cálculos) ---
        function actualizarCarritoVisual() {
            let carritoBody = $('#tablaCarrito tbody');
            carritoBody.empty();
            let subtotalVenta = 0; // Suma de items sin descuento

            if (carrito.length === 0) {
                carritoBody.append('<tr id="filaVaciaCarrito"><td colspan="5" class="text-center text-muted pt-5">El carrito está vacío</td></tr>');
            } else {
                carrito.forEach(function (item, index) {
                    let subtotalItem = item.cantidad * item.precio;
                    subtotalVenta += subtotalItem;

                    // Escapar XSS antes de insertar en el DOM
                    const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                    let filaHtml = `
                    <tr>
                        <td>${esc(item.descripcion)}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm inputCantidad" 
                                   value="${item.cantidad}" min="1" max="${item.stock_max}" 
                                   data-index="${index}">
                        </td>
                        <td>$${item.precio.toFixed(2)}</td>
                        <td class="fw-bold">$${subtotalItem.toFixed(2)}</td>
                        <td>
                            <button class="btn btn-danger btn-sm btnEliminarItem" data-index="${index}" title="Quitar">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </td>
                    </tr>
                `;
                    carritoBody.append(filaHtml);
                });
            }

            // --- CÁLCULO DEL DESCUENTO ---
            let porcentajeDescuento = parseFloat($('#inputDescuento').val()) || 0;

            // Validar que no sea negativo o mayor a 100
            if (porcentajeDescuento < 0) porcentajeDescuento = 0;
            if (porcentajeDescuento > 100) porcentajeDescuento = 100;

            let montoDescuento = subtotalVenta * (porcentajeDescuento / 100);
            let totalFinal = subtotalVenta - montoDescuento;

            // Actualizar Textos en Pantalla
            $('#textoSubtotal').text('$' + subtotalVenta.toFixed(2));
            $('#textoMontoDescuento').text('-$' + montoDescuento.toFixed(2));
            $('#textoTotalVenta').text('$' + totalFinal.toFixed(2));

            // Actualizar Inputs Ocultos (Esto es lo que se envía al backend)
            $('#inputTotalVenta').val(totalFinal.toFixed(2)); // Se envía el total con descuento aplicado
            $('#inputCarritoJSON').val(JSON.stringify(carrito));
        }

        // --- 6. Eventos de cambio en Cantidad y Descuento ---

        // Cuando cambian la cantidad
        $('#tablaCarrito').on('change', '.inputCantidad', function () {
            let index = $(this).data('index');
            let nuevaCantidad = parseInt($(this).val());
            let stockMax = parseInt($(this).attr('max'));

            if (nuevaCantidad > stockMax) {
                nuevaCantidad = stockMax;
                $(this).val(stockMax);
                Swal.fire('Stock Límite', 'Stock disponible: ' + stockMax, 'warning');
            }

            if (nuevaCantidad < 1) {
                nuevaCantidad = 1;
                $(this).val(1);
            }

            carrito[index].cantidad = nuevaCantidad;
            actualizarCarritoVisual();
        });

        // Cuando cambian el descuento
        $('#inputDescuento').on('input change', function () {
            actualizarCarritoVisual();
        });

        // --- 7. Eliminar Item ---
        $('#tablaCarrito').on('click', '.btnEliminarItem', function () {
            let index = $(this).data('index');
            carrito.splice(index, 1);
            actualizarCarritoVisual();
        });

        // --- 8. Validar Cta Corriente ---
        $('#selectTipoPago').on('change', function () {
            let tipoPago = $(this).val();
            let idCliente = $('#selectCliente').val();

            if (tipoPago === 'Cuenta Corriente' && idCliente === '1') {
                Swal.fire('Acción no permitida', 'Consumidor Final no puede usar Cuenta Corriente.', 'error');
                $(this).val('Contado');
            }
        });
        $('#selectCliente').on('change', function () {
            let tipoPago = $('#selectTipoPago').val();
            let idCliente = $(this).val();
            if (tipoPago === 'Cuenta Corriente' && idCliente === '1') {
                Swal.fire('Cliente no válido', 'Cambiando a Contado...', 'warning');
                $('#selectTipoPago').val('Contado');
            }
        });

        // --- 9. Finalizar Venta ---
        $('#formProcesarVenta').submit(function (e) {
            e.preventDefault();

            if (carrito.length === 0) {
                Swal.fire('Carrito Vacío', 'Añade artículos antes de finalizar.', 'error');
                return;
            }

            if ($('#selectTipoPago').val() === 'Cuenta Corriente' && $('#selectCliente').val() === '1') {
                Swal.fire('Cliente requerido', 'Seleccioná un cliente para ventas en Cuenta Corriente.', 'warning');
                $('#selectCliente').focus();
                return;
            }

            $('#btnFinalizarVenta').prop('disabled', true).text('Procesando...');
            var formData = $(this).serialize();

            $.ajax({
                url: 'gestionar_venta.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Venta Registrada!',
                            text: response.message,
                            allowOutsideClick: false,
                            confirmButtonText: 'Ver Remito'
                        }).then(() => {
                            // Usar el campo id_venta del JSON (no regex sobre el texto)
                            if (response.id_venta) {
                                window.open('remito.php?id=' + response.id_venta, '_blank');
                            }
                            window.location.href = 'ventas.php';
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                        $('#btnFinalizarVenta').prop('disabled', false).html('<i class="bi bi-check-circle me-2"></i>Finalizar Venta');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Error de conexión.', 'error');
                    $('#btnFinalizarVenta').prop('disabled', false).html('<i class="bi bi-check-circle me-2"></i>Finalizar Venta');
                }
            });
        });

    });
</script>