<?php
require 'header.php';

$usuarios = $pdo->query("SELECT id, nombre_usuario, fecha_registro FROM usuarios ORDER BY id ASC")->fetchAll();
?>

<div class="container mt-4 pb-5">

    <!-- Encabezado -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Gestión de Usuarios</h2>
            <small class="text-muted"><?= count($usuarios) ?> usuario(s) registrado(s) en el sistema</small>
        </div>
        <button class="btn btn-primary" id="btnNuevoUsuario">
            <i class="bi bi-person-plus me-1"></i>Nuevo Usuario
        </button>
    </div>

    <!-- Tabla -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaUsuarios" class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:60px;">#</th>
                            <th>Usuario</th>
                            <th>Fecha de Registro</th>
                            <th class="text-center" style="width:120px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td class="text-muted"><?= $u['id'] ?></td>
                                <td>
                                    <i class="bi bi-person-circle me-2 text-primary"></i>
                                    <strong><?= htmlspecialchars($u['nombre_usuario']) ?></strong>
                                    <?php if ($u['id'] == $_SESSION['usuario_id']): ?>
                                        <span class="badge bg-success ms-2">Vos</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted">
                                    <?= $u['fecha_registro'] ? date('d/m/Y H:i', strtotime($u['fecha_registro'])) : '—' ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-warning btn-cambiar-password me-1"
                                        title="Cambiar contraseña"
                                        data-id="<?= $u['id'] ?>"
                                        data-nombre="<?= htmlspecialchars($u['nombre_usuario'], ENT_QUOTES) ?>">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
                                        <button class="btn btn-sm btn-outline-danger btn-eliminar"
                                            title="Eliminar usuario"
                                            data-id="<?= $u['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($u['nombre_usuario'], ENT_QUOTES) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled title="No podés eliminar tu propia cuenta">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ── Modal Nuevo Usuario ── -->
<div class="modal fade" id="modalNuevoUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNuevoUsuario" novalidate>
                    <input type="hidden" name="accion" value="crear">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de usuario <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre_usuario" id="nuevoNombreUsuario" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" id="nuevaPassword" required autocomplete="new-password">
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Confirmar contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirmar_password" id="confirmarPassword" required autocomplete="new-password">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarUsuario">
                    <i class="bi bi-save me-1"></i>Crear Usuario
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Cambiar Contraseña ── -->
<div class="modal fade" id="modalCambiarPassword" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-key me-2"></i>Cambiar Contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formCambiarPassword" novalidate>
                    <input type="hidden" name="accion" value="cambiar_password">
                    <input type="hidden" name="id" id="cpIdUsuario">
                    <p class="text-muted small mb-3">Usuario: <strong id="cpNombreUsuario"></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" id="cpPassword" required autocomplete="new-password">
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Confirmar <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirmar_password" id="cpConfirmar" required autocomplete="new-password">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnGuardarPassword">
                    <i class="bi bi-save me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>

<script>
$(document).ready(function () {

    $('#tablaUsuarios').DataTable({
        pageLength: 25,
        language: {
            search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_', zeroRecords: 'Sin resultados',
            paginate: { next: 'Siguiente', previous: 'Anterior' }
        }
    });

    const modalNuevo    = new bootstrap.Modal(document.getElementById('modalNuevoUsuario'));
    const modalPassword = new bootstrap.Modal(document.getElementById('modalCambiarPassword'));

    // ── Abrir modal nuevo ──
    $('#btnNuevoUsuario').click(function () {
        $('#formNuevoUsuario')[0].reset();
        modalNuevo.show();
        setTimeout(() => $('#nuevoNombreUsuario').focus(), 400);
    });

    // ── Crear usuario ──
    $('#btnGuardarUsuario').click(function () {
        if (!$('#formNuevoUsuario')[0].checkValidity()) { $('#formNuevoUsuario')[0].reportValidity(); return; }
        if ($('#nuevaPassword').val() !== $('#confirmarPassword').val()) {
            Swal.fire('Error', 'Las contraseñas no coinciden.', 'error'); return;
        }
        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Creando...');
        $.ajax({
            url: 'gestionar_usuario.php', type: 'POST',
            data: $('#formNuevoUsuario').serialize(), dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    modalNuevo.hide();
                    Swal.fire({ icon: 'success', title: '¡Creado!', text: res.message, timer: 1500, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                    btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Crear Usuario');
                }
            },
            error: () => { Swal.fire('Error', 'Error de conexión', 'error'); btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Crear Usuario'); }
        });
    });

    // ── Abrir modal cambiar contraseña ──
    $(document).on('click', '.btn-cambiar-password', function () {
        $('#cpIdUsuario').val($(this).data('id'));
        $('#cpNombreUsuario').text($(this).data('nombre'));
        $('#formCambiarPassword')[0].reset();
        $('#cpIdUsuario').val($(this).data('id'));
        modalPassword.show();
    });

    // ── Guardar nueva contraseña ──
    $('#btnGuardarPassword').click(function () {
        if (!$('#formCambiarPassword')[0].checkValidity()) { $('#formCambiarPassword')[0].reportValidity(); return; }
        if ($('#cpPassword').val() !== $('#cpConfirmar').val()) {
            Swal.fire('Error', 'Las contraseñas no coinciden.', 'error'); return;
        }
        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Guardando...');
        $.ajax({
            url: 'gestionar_usuario.php', type: 'POST',
            data: $('#formCambiarPassword').serialize(), dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    modalPassword.hide();
                    Swal.fire({ icon: 'success', title: '¡Contraseña actualizada!', timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
                btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Guardar');
            },
            error: () => { Swal.fire('Error', 'Error de conexión', 'error'); btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Guardar'); }
        });
    });

    // ── Eliminar usuario ──
    $(document).on('click', '.btn-eliminar', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        Swal.fire({
            title: '¿Eliminar usuario?',
            html: `<strong>${nombre}</strong><br><small class="text-muted">Esta acción no se puede deshacer.</small>`,
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#dc3545', cancelButtonText: 'Cancelar', confirmButtonText: 'Sí, eliminar'
        }).then(result => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: 'gestionar_usuario.php', type: 'POST',
                data: { accion: 'eliminar', id: id }, dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1200, showConfirmButton: false })
                            .then(() => location.reload());
                    } else { Swal.fire('Error', res.message, 'error'); }
                },
                error: () => Swal.fire('Error', 'Error de conexión', 'error')
            });
        });
    });

});
</script>
