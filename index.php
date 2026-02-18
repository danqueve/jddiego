<?php 
require 'header.php'; 

// --- Consultas para los <select> del modal ---

// 1. Obtener lista de Proveedores
$stmt_proveedores = $pdo->query("SELECT id, nombre_proveedor FROM proveedores ORDER BY nombre_proveedor");
$proveedores = $stmt_proveedores->fetchAll();

// 2. Obtener lista de Artículos (para el formulario de Compras)
$stmt_articulos = $pdo->query("SELECT id, descripcion, codigo FROM articulos ORDER BY descripcion");
$articulos = $stmt_articulos->fetchAll();
?>

<!-- Contenido principal -->
<div class="container mt-5 pt-5">

    <!-- 1. Cabecera de la Página -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0 fw-bold text-primary">
            <i class="bi bi-box-seam me-2"></i>Gestión de Stock
        </h1>
        <div>
            <!-- Botón Imprimir Reporte -->
            <a href="stock_print.php" target="_blank" class="btn btn-secondary me-2">
                <i class="bi bi-printer me-2"></i>Imprimir Reporte
            </a>
            
            <button id="btnCrearArticulo" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Crear Nuevo Artículo
            </button>
        </div>
    </div>

    <!-- 2. Tarjeta de Contenido (Tabla) -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            
            <!-- Buscador en Tiempo Real (NUEVO) -->
            <div class="input-group mb-3">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="buscadorStock" class="form-control border-start-0 ps-0" placeholder="Buscar artículo, código o proveedor...">
            </div>

            <div class="table-responsive">
                <table id="tablaArticulos" class="table table-striped table-hover align-middle" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Proveedor</th>
                            <th class="text-center">Stock</th>
                            <th class="text-end">P. Costo</th>
                            <th class="text-end">P. Venta</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Consulta con LEFT JOIN para obtener el nombre del proveedor
                        $sql = "SELECT a.*, p.nombre_proveedor 
                                FROM articulos a 
                                LEFT JOIN proveedores p ON a.id_proveedor = p.id
                                ORDER BY a.descripcion";
                        $stmt = $pdo->query($sql);

                        while ($row = $stmt->fetch()) {
                            echo "<tr>";
                            echo "<td><small class='text-muted'>" . htmlspecialchars($row['codigo']) . "</small></td>";
                            echo "<td class='fw-bold'>" . htmlspecialchars($row['descripcion']) . "</td>";
                            // Mostrar 'Sin Proveedor' si id_proveedor es NULL
                            echo "<td>" . htmlspecialchars($row['nombre_proveedor'] ?? '-') . "</td>";
                            
                            // Colorear el stock
                            $stock_class = 'bg-success';
                            if ($row['stock_actual'] <= 5) $stock_class = 'bg-warning text-dark';
                            if ($row['stock_actual'] == 0) $stock_class = 'bg-danger';
                            
                            echo "<td class='text-center'><span class='badge $stock_class rounded-pill'>" . $row['stock_actual'] . "</span></td>";
                            
                            echo "<td class='text-end'>$" . number_format($row['precio_costo'], 2) . "</td>";
                            echo "<td class='text-end fw-bold text-primary'>$" . number_format($row['precio_venta'], 2) . "</td>";
                            
                            echo "<td class='text-center'>
                                    <button class='btn btn-warning btn-sm btnEditar shadow-sm' data-id='" . $row['id'] . "' title='Editar'>
                                        <i class='bi bi-pencil'></i>
                                    </button>
                                    <button class='btn btn-danger btn-sm btnEliminar shadow-sm' data-id='" . $row['id'] . "' title='Eliminar'>
                                        <i class='bi bi-trash'></i>
                                    </button>
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

<!-- 3. Modal para Crear/Editar Artículo -->
<div class="modal fade" id="modalArticulo" tabindex="-1" aria-labelledby="modalLabelArticulo" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalLabelArticulo">Crear Nuevo Artículo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formArticulo">
                    <!-- Campos ocultos -->
                    <input type="hidden" id="id_articulo" name="id_articulo" value="0">
                    <input type="hidden" id="accion" name="accion" value="crear">
                    
                    <div class="mb-3">
                        <label for="codigo" class="form-label">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo">
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label fw-bold">Descripción</label>
                        <input type="text" class="form-control" id="descripcion" name="descripcion" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_proveedor" class="form-label">Proveedor</label>
                        <select class="form-select" id="id_proveedor" name="id_proveedor">
                            <option value="">(Sin Proveedor)</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['nombre_proveedor']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="stock_actual" class="form-label">Stock</label>
                            <input type="number" class="form-control" id="stock_actual" name="stock_actual" value="0" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="precio_costo" class="form-label">Costo</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="precio_costo" name="precio_costo" step="0.01" value="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="precio_venta" class="form-label">Venta</label>
                             <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control fw-bold" id="precio_venta" name="precio_venta" step="0.01" value="0.00" required>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4" form="formArticulo" id="btnGuardarArticulo">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<?php require 'footer.php'; ?>

<!-- 4. JavaScript -->
<script>
$(document).ready(function() {
    
    // 1. Inicializar DataTables con configuración personalizada para ocultar el buscador nativo
    var table = $('#tablaArticulos').DataTable({
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "order": [[1, "asc"]],
        // 'dom' personalizado:
        // l: length (selector de cantidad), r: processing, t: table, i: information, p: pagination
        // Quitamos la 'f' (filter) para ocultar el buscador nativo
        "dom": '<"row"<"col-sm-12"tr>><"row mt-3"<"col-sm-6"i><"col-sm-6"p>>',
        "pageLength": 10
    });

    // 2. Conectar nuestro Buscador Personalizado con DataTables
    $('#buscadorStock').on('keyup', function() {
        table.search(this.value).draw();
    });

    var modalArticulo = new bootstrap.Modal($('#modalArticulo'));

    // Abrir Crear
    $('#btnCrearArticulo').click(function() {
        $('#formArticulo')[0].reset(); 
        $('#id_articulo').val('0');    
        $('#accion').val('crear');     
        $('#modalLabelArticulo').text('Crear Nuevo Artículo');
        $('#btnGuardarArticulo').text('Guardar');
        modalArticulo.show();
    });

    // Abrir Editar
    $('#tablaArticulos').on('click', '.btnEditar', function() {
        var idArticulo = $(this).data('id');
        
        $.ajax({
            url: 'gestionar_articulo.php',
            type: 'GET',
            data: { accion: 'obtener', id: idArticulo },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#id_articulo').val(response.data.id);
                    $('#codigo').val(response.data.codigo);
                    $('#descripcion').val(response.data.descripcion);
                    $('#id_proveedor').val(response.data.id_proveedor);
                    $('#stock_actual').val(response.data.stock_actual);
                    $('#precio_costo').val(response.data.precio_costo);
                    $('#precio_venta').val(response.data.precio_venta);
                    
                    $('#accion').val('editar'); 
                    $('#modalLabelArticulo').text('Editar Artículo');
                    $('#btnGuardarArticulo').text('Actualizar');
                    modalArticulo.show();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error al conectar con el servidor.', 'error');
            }
        });
    });

    // Guardar (Crear/Editar)
    $('#formArticulo').submit(function(e) {
        e.preventDefault(); 
        var formData = $(this).serialize(); 
        
        $.ajax({
            url: 'gestionar_articulo.php',
            type: 'POST',
            data: formData, 
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    modalArticulo.hide();
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(function() {
                        location.reload(); 
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error al conectar con el servidor.', 'error');
            }
        });
    });

    // Eliminar
    $('#tablaArticulos').on('click', '.btnEliminar', function() {
        var idArticulo = $(this).data('id');
        
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¡No podrás revertir esto!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, ¡eliminar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'gestionar_articulo.php',
                    type: 'POST',
                    data: { 
                        accion: 'eliminar', 
                        id_articulo: idArticulo 
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Eliminado!',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Error al conectar con el servidor.', 'error');
                    }
                });
            }
        });
    });

});
</script>