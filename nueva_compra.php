<?php 
require 'header.php'; 

// Cargar Proveedores para el <select>
// Usamos try-catch para manejar errores de conexión silenciosamente si es necesario
try {
    $stmt_proveedores = $pdo->query("SELECT id, nombre_proveedor 
                                    FROM proveedores 
                                    ORDER BY nombre_proveedor");
    $proveedores = $stmt_proveedores->fetchAll();
} catch (PDOException $e) {
    $proveedores = [];
}
?>

<!-- Contenido principal con el nuevo layout centrado -->
<div class="container mt-5 pt-5">
    
    <!-- 1. Cabecera de la Página -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Registrar Nueva Compra</h1>
        <a href="compras.php" class="btn btn-secondary">
            <i class="bi bi-x-circle me-2"></i>Cancelar y Volver
        </a>
    </div>

    <!-- 2. Layout del POS (2 columnas) -->
    <div class="row g-4">
        
        <!-- Columna Izquierda: Búsqueda y Detalle -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h5 class="card-title">1. Buscar Artículo a Ingresar</h5>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="busquedaArticulo" class="form-control" placeholder="Buscar por código o descripción...">
                    </div>
                    <!-- Resultados de búsqueda aparecerán aquí -->
                    <div id="resultadosBusqueda" class="list-group mt-2" style="max-height: 200px; overflow-y: auto;"></div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title">2. Detalle de Compra</h5>
                    <div class="table-responsive">
                        <table id="tablaCarrito" class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Descripción</th>
                                    <th style="width: 100px;">Cantidad</th>
                                    <th style="width: 130px;">P. Costo</th>
                                    <th style="width: 120px;">Subtotal</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Los artículos se añaden aquí con JS -->
                                <tr id="filaVaciaCarrito">
                                    <td colspan="5" class="text-center text-muted">El detalle está vacío</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Columna Derecha: Proveedor y Pago -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 sticky-top" style="top: 100px;">
                <div class="card-body p-4">
                    <h5 class="card-title">3. Proveedor y Total</h5>
                    <form id="formProcesarCompra">
                        
                        <div class="mb-3">
                            <label for="selectProveedor" class="form-label">Proveedor</label>
                            <select id="selectProveedor" name="id_proveedor" class="form-select" required>
                                <option value="">Seleccione un proveedor...</option>
                                <?php foreach ($proveedores as $p): ?>
                                    <option value="<?php echo $p['id']; ?>">
                                        <?php echo htmlspecialchars($p['nombre_proveedor']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <hr>
                        
                        <!-- Resumen de Total -->
                        <div class="d-flex justify-content-between align-items-center fs-4">
                            <strong class="text-dark">TOTAL COMPRA:</strong>
                            <strong class="text-danger" id="textoTotalCompra">$0.00</strong>
                        </div>
                        
                        <!-- Campos ocultos para el total y el carrito -->
                        <input type="hidden" id="inputTotalCompra" name="total" value="0">
                        <input type="hidden" id="inputCarritoJSON" name="carrito">
                        <input type="hidden" name="accion" value="procesar_compra">
                        
                        <div class="d-grid mt-4">
                            <button type="submit" id="btnFinalizarCompra" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Registrar Compra (Ingresar Stock)
                            </button>
                        </div>
                        <small class="d-block text-center text-muted mt-2">Esto registrará un Egreso en la Caja.</small>
                        
                    </form>
                </div>
            </div>
        </div>
        
    </div> <!-- fin .row -->
</div>

<!-- ================================================================== -->
<!--  ¡CORRECCIÓN IMPORTANTE! El Footer va AQUÍ, ANTES del script       -->
<!--  Esto asegura que jQuery esté cargado cuando el script se ejecute  -->
<!-- ================================================================== -->
<?php require 'footer.php'; ?>

<!-- 4. JavaScript Específico de la Página -->
<script>
$(document).ready(function() {
    
    let carrito = []; // Array para almacenar los artículos

    // --- 1. Lógica de Búsqueda de Artículos ---
    $('#busquedaArticulo').on('keyup', function() {
        let query = $(this).val();
        let resultadosDiv = $('#resultadosBusqueda');

        if (query.length < 2) {
            resultadosDiv.empty().hide();
            return;
        }

        $.ajax({
            url: 'gestionar_compra.php',
            type: 'GET',
            data: { accion: 'buscar_articulo', query: query },
            dataType: 'json',
            success: function(response) {
                resultadosDiv.empty();
                if (response.status === 'success' && response.data.length > 0) {
                    resultadosDiv.show();
                    response.data.forEach(function(item) {
                        let itemHtml = `
                            <a href="#" class="list-group-item list-group-item-action" 
                               data-id="${item.id}" 
                               data-descripcion="${item.descripcion}" 
                               data-costo-actual="${item.precio_costo}">
                                <strong>${item.descripcion}</strong> [${item.codigo}] - (Costo actual: $${item.precio_costo})
                            </a>`;
                        resultadosDiv.append(itemHtml);
                    });
                } else {
                    resultadosDiv.hide();
                }
            },
            error: function() {
                console.error("Error buscando artículos");
            }
        });
    });

    // --- 2. Añadir Artículo al Carrito ---
    $('#resultadosBusqueda').on('click', '.list-group-item', function(e) {
        e.preventDefault();
        
        let item = {
            id: $(this).data('id'),
            descripcion: $(this).data('descripcion'),
            precio_costo: parseFloat($(this).data('costo-actual')), // Usamos el costo actual como base
            cantidad: 1
        };

        // Limpiar búsqueda
        $('#busquedaArticulo').val('');
        $('#resultadosBusqueda').empty().hide();

        // Verificar si el artículo ya está en el carrito
        let itemExistente = carrito.find(i => i.id === item.id);
        
        if (itemExistente) {
            itemExistente.cantidad++;
        } else {
            carrito.push(item);
        }
        
        actualizarCarritoVisual();
    });

    // --- 3. Actualizar Carrito (Visual y Total) ---
    function actualizarCarritoVisual() {
        let carritoBody = $('#tablaCarrito tbody');
        carritoBody.empty();
        let totalCompra = 0;

        if (carrito.length === 0) {
            carritoBody.append('<tr id="filaVaciaCarrito"><td colspan="5" class="text-center text-muted">El detalle está vacío</td></tr>');
        } else {
            carrito.forEach(function(item, index) {
                let subtotal = item.cantidad * item.precio_costo;
                totalCompra += subtotal;
                
                let filaHtml = `
                    <tr>
                        <td>${item.descripcion}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm inputCantidad" 
                                   value="${item.cantidad}" min="1" data-index="${index}">
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control form-control-sm inputCosto" 
                                       value="${item.precio_costo.toFixed(2)}" step="0.01" min="0" 
                                       data-index="${index}">
                            </div>
                        </td>
                        <td>$${subtotal.toFixed(2)}</td>
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
        
        // Actualizar el TOTAL
        $('#textoTotalCompra').text('$' + totalCompra.toFixed(2));
        $('#inputTotalCompra').val(totalCompra.toFixed(2));
        $('#inputCarritoJSON').val(JSON.stringify(carrito));
    }

    // --- 4. Cambiar Cantidad o Costo en el Carrito ---
    $('#tablaCarrito').on('change', '.inputCantidad', function() {
        let index = $(this).data('index');
        let nuevaCantidad = parseInt($(this).val());
        if (nuevaCantidad < 1) {
            nuevaCantidad = 1;
            $(this).val(1);
        }
        carrito[index].cantidad = nuevaCantidad;
        actualizarCarritoVisual();
    });
    
    $('#tablaCarrito').on('change', '.inputCosto', function() {
        let index = $(this).data('index');
        let nuevoCosto = parseFloat($(this).val());
        if (nuevoCosto < 0) {
            nuevoCosto = 0;
            $(this).val(0);
        }
        carrito[index].precio_costo = nuevoCosto;
        actualizarCarritoVisual();
    });


    // --- 5. Eliminar Item del Carrito ---
    $('#tablaCarrito').on('click', '.btnEliminarItem', function() {
        let index = $(this).data('index');
        carrito.splice(index, 1);
        actualizarCarritoVisual();
    });

    // --- 6. Finalizar Compra (Submit) ---
    $('#formProcesarCompra').submit(function(e) {
        e.preventDefault();
        
        if (carrito.length === 0) {
            Swal.fire('Detalle Vacío', 'Debe añadir al menos un artículo.', 'error');
            return;
        }
        if ($('#selectProveedor').val() === "") {
             Swal.fire('Falta Proveedor', 'Debe seleccionar un proveedor.', 'error');
            return;
        }

        $('#btnFinalizarCompra').prop('disabled', true).text('Procesando...');
        var formData = $(this).serialize();

        $.ajax({
            url: 'gestionar_compra.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Compra Registrada!',
                        text: response.message,
                        allowOutsideClick: false,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'compras.php';
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                    $('#btnFinalizarCompra').prop('disabled', false).html('<i class="bi bi-check-circle me-2"></i>Registrar Compra');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
                $('#btnFinalizarCompra').prop('disabled', false).html('<i class="bi bi-check-circle me-2"></i>Registrar Compra');
            }
        });
    });

});
</script>