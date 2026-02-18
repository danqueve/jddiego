<?php 
require 'header.php'; 
?>

<!-- Contenido principal -->
<div class="container mt-5 pt-5">


    <!-- 1. Cabecera de la Página -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Gestión de Proveedores</h1>
        <button id="btnCrearProveedor" class="btn btn-primary">
            <i class="bi bi-building-add me-2"></i>Crear Nuevo Proveedor
        </button>
    </div>

    <!-- 2. Tarjeta de Contenido (Tabla) -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaProveedores" class="table table-striped table-hover" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre Proveedor</th>
                            <th>Contacto</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT * FROM proveedores ORDER BY nombre_proveedor");
                        while ($row = $stmt->fetch()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['nombre_proveedor']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['contacto']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['telefono']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                            
                            // Botones de acción
                            echo "<td>
                                    <button class='btn btn-warning btn-sm btnEditar' data-id='" . $row['id'] . "' title='Editar'>
                                        <i class='bi bi-pencil'></i>
                                    </button>
                                    <button class='btn btn-danger btn-sm btnEliminar' data-id='" . $row['id'] . "' title='Eliminar'>
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

<!-- 3. Modal para Crear/Editar Proveedor -->
<div class="modal fade" id="modalProveedor" tabindex="-1" aria-labelledby="modalLabelProveedor" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabelProveedor">Crear Nuevo Proveedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formProveedor">
                    <!-- Campos ocultos -->
                    <input type="hidden" id="id_proveedor" name="id_proveedor" value="0">
                    <input type="hidden" id="accion" name="accion" value="crear">
                    
                    <div class="mb-3">
                        <label for="nombre_proveedor" class="form-label">Nombre del Proveedor</label>
                        <input type="text" class="form-control" id="nombre_proveedor" name="nombre_proveedor" required>
                    </div>
                    <div class="mb-3">
                        <label for="contacto" class="form-label">Nombre de Contacto</label>
                        <input type="text" class="form-control" id="contacto" name="contacto">
                    </div>
                     <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" form="formProveedor" id="btnGuardarProveedor">Guardar Proveedor</button>
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
    $('#tablaProveedores').DataTable({
        "language": {
            // CORRECCIÓN: Añadimos "https:"
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        }
    });

    var modalProveedor = new bootstrap.Modal($('#modalProveedor'));

    // 2. Abrir Modal para CREAR
    $('#btnCrearProveedor').click(function() {
        $('#formProveedor')[0].reset(); // Limpiar formulario
        $('#id_proveedor').val('0');    // Resetear ID
        $('#accion').val('crear');     // Poner acción 'crear'
        $('#modalLabelProveedor').text('Crear Nuevo Proveedor');
        $('#btnGuardarProveedor').text('Guardar Proveedor');
        modalProveedor.show();
    });

    // 3. Abrir Modal para EDITAR
    $('#tablaProveedores').on('click', '.btnEditar', function() {
        var idProveedor = $(this).data('id');
        
        // Solicitar datos del proveedor por AJAX (usando GET)
        $.ajax({
            url: 'gestionar_proveedor.php',
            type: 'GET',
            data: { accion: 'obtener', id: idProveedor },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Llenar el formulario con los datos
                    $('#id_proveedor').val(response.data.id);
                    $('#nombre_proveedor').val(response.data.nombre_proveedor);
                    $('#contacto').val(response.data.contacto);
                    $('#telefono').val(response.data.telefono);
                    $('#email').val(response.data.email);
                    
                    $('#accion').val('editar'); // Poner acción 'editar'
                    $('#modalLabelProveedor').text('Editar Proveedor');
                    $('#btnGuardarProveedor').text('Actualizar Proveedor');
                    modalProveedor.show();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error al conectar con el servidor.', 'error');
            }
        });
    });

    // 4. Lógica de Submit (CREAR y EDITAR)
    $('#formProveedor').submit(function(e) {
        e.preventDefault(); // Evitar envío normal
        var formData = $(this).serialize(); // Obtener datos del form
        
        // Enviar datos por AJAX (usando POST)
        $.ajax({
            url: 'gestionar_proveedor.php',
            type: 'POST',
            data: formData, // formData ya incluye la 'accion'
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    modalProveedor.hide();
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(function() {
                        location.reload(); // Recargar la página
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

    // 5. Lógica para ELIMINAR
    $('#tablaProveedores').on('click', '.btnEliminar', function() {
        var idProveedor = $(this).data('id');
        
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
                // Enviar solicitud de eliminación por AJAX (usando POST)
                $.ajax({
                    url: 'gestionar_proveedor.php',
                    type: 'POST',
                    data: { 
                        accion: 'eliminar', 
                        id_proveedor: idProveedor 
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