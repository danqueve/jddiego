<?php
require 'header.php';
?>

<!-- Contenido principal -->
<div class="container mt-5 pt-5">


    <!-- 1. Cabecera de la Página -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Gestión de Clientes</h1>
        <button id="btnCrearCliente" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>Crear Nuevo Cliente
        </button>
    </div>

    <!-- 2. Tarjeta de Contenido (Tabla) -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaClientes" class="table table-striped table-hover" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>DNI</th>
                            <th>Apellido</th>
                            <th>Nombre</th>
                            <th>Celular</th>
                            <th>Dirección</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Excluimos al cliente 'Consumidor Final' (ID 1) de esta lista
                        $stmt = $pdo->query("SELECT * FROM clientes WHERE id != 1 ORDER BY apellido, nombre");
                        while ($row = $stmt->fetch()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['dni']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['apellido']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['celular']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['direccion']) . "</td>";

                            // Botones de acción
                            echo "<td>
                                    <a href='ficha_cliente.php?id=" . $row['id'] . "' class='btn btn-secondary btn-sm' title='Ver Ficha'>
                                        <i class='bi bi-person-lines-fill'></i>
                                    </a>
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

<!-- 3. Modal para Crear/Editar Cliente -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-labelledby="modalLabelCliente" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabelCliente">Crear Nuevo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formCliente">
                    <!-- Campos ocultos -->
                    <input type="hidden" id="id_cliente" name="id_cliente" value="0">
                    <input type="hidden" id="accion" name="accion" value="crear">

                    <div class="mb-3">
                        <label for="dni" class="form-label">DNI</label>
                        <input type="text" class="form-control" id="dni" name="dni" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label for="apellido" class="form-label">Apellido</label>
                            <input type="text" class="form-control" id="apellido" name="apellido" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="celular" class="form-label">Celular</label>
                        <input type="text" class="form-control" id="celular" name="celular">
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" form="formCliente" id="btnGuardarCliente">Guardar
                    Cliente</button>
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
    $(document).ready(function () {

        // 1. Inicializar DataTables
        // (Ahora $ y DataTable están definidos gracias al footer de arriba)
        $('#tablaClientes').DataTable({
            "language": {
                // ---- ¡AQUÍ ESTÁ LA CORRECCIÓN! ----
                // Añadimos "https:" para asegurar que la URL se resuelva correctamente
                "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
            }
        });

        var modalCliente = new bootstrap.Modal($('#modalCliente'));

        // 2. Abrir Modal para CREAR
        $('#btnCrearCliente').click(function () {
            $('#formCliente')[0].reset(); // Limpiar formulario
            $('#id_cliente').val('0');    // Resetear ID
            $('#accion').val('crear');     // Poner acción 'crear'
            $('#modalLabelCliente').text('Crear Nuevo Cliente');
            $('#btnGuardarCliente').text('Guardar Cliente');
            modalCliente.show();
        });

        // 3. Abrir Modal para EDITAR
        $('#tablaClientes').on('click', '.btnEditar', function () {
            var idCliente = $(this).data('id');

            // Solicitar datos del cliente por AJAX (usando GET)
            $.ajax({
                url: 'gestionar_cliente.php',
                type: 'GET',
                data: { accion: 'obtener', id: idCliente },
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        // Llenar el formulario con los datos
                        $('#id_cliente').val(response.data.id);
                        $('#dni').val(response.data.dni);
                        $('#apellido').val(response.data.apellido);
                        $('#nombre').val(response.data.nombre);
                        $('#celular').val(response.data.celular);
                        $('#direccion').val(response.data.direccion);

                        $('#accion').val('editar'); // Poner acción 'editar'
                        $('#modalLabelCliente').text('Editar Cliente');
                        $('#btnGuardarCliente').text('Actualizar Cliente');
                        modalCliente.show();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Error al conectar con el servidor.', 'error');
                }
            });
        });

        // 4. Lógica de Submit (CREAR y EDITAR)
        $('#formCliente').submit(function (e) {
            e.preventDefault(); // Evitar envío normal
            var formData = $(this).serialize(); // Obtener datos del form

            // Enviar datos por AJAX (usando POST)
            $.ajax({
                url: 'gestionar_cliente.php',
                type: 'POST',
                data: formData, // formData ya incluye la 'accion'
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        modalCliente.hide();
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(function () {
                            location.reload(); // Recargar la página
                        });
                    } else {
                        // Mostrar error (ej: DNI duplicado)
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Error al conectar con el servidor.', 'error');
                }
            });
        });

        // 5. Lógica para ELIMINAR
        $('#tablaClientes').on('click', '.btnEliminar', function () {
            var idCliente = $(this).data('id');

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
                        url: 'gestionar_cliente.php',
                        type: 'POST',
                        data: {
                            accion: 'eliminar',
                            id_cliente: idCliente
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Eliminado!',
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(function () {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function () {
                            Swal.fire('Error', 'Error al conectar con el servidor.', 'error');
                        }
                    });
                }
            });
        });

    });
</script>