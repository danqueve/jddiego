<?php
session_start();
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Ventas JD</title>
    <link rel="icon" type="image/png" href="img/logo_1.png">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── Fondo kraft paper ── */
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Arial, sans-serif;

            background-color: #c8a882;
            background-image:
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='400'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='400' height='400' filter='url(%23n)' opacity='0.18'/%3E%3C/svg%3E"),
                repeating-linear-gradient(
                    0deg,
                    transparent,
                    transparent 2px,
                    rgba(0,0,0,.015) 2px,
                    rgba(0,0,0,.015) 4px
                );
        }

        /* ── Contenedor con cinta adhesiva ── */
        .pin-wrapper {
            position: relative;
            width: 320px;
        }

        /* Cinta izquierda */
        .pin-wrapper::before {
            content: '';
            position: absolute;
            width: 58px;
            height: 22px;
            background: rgba(195, 165, 110, 0.55);
            border: 1px solid rgba(160, 130, 80, 0.25);
            top: -11px;
            left: 28px;
            transform: rotate(-4deg);
            z-index: 10;
            box-shadow: inset 0 1px 3px rgba(255,255,255,.25);
        }

        /* Cinta derecha */
        .pin-wrapper::after {
            content: '';
            position: absolute;
            width: 58px;
            height: 22px;
            background: rgba(195, 165, 110, 0.55);
            border: 1px solid rgba(160, 130, 80, 0.25);
            top: -11px;
            right: 28px;
            transform: rotate(4deg);
            z-index: 10;
            box-shadow: inset 0 1px 3px rgba(255,255,255,.25);
        }

        /* ── Tarjeta blanca ── */
        .login-card {
            background: #f7f7f5;
            border-radius: 3px;
            padding: 36px 36px 32px;
            box-shadow:
                0 2px 4px rgba(0,0,0,.18),
                0 8px 24px rgba(0,0,0,.14),
                inset 0 1px 0 rgba(255,255,255,.9);
            position: relative;
        }

        /* Doblez esquina inferior derecha */
        .login-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 0 18px 18px;
            border-color: transparent transparent #c8a882 transparent;
        }

        /* ── Título ── */
        .login-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }

        .login-title::before,
        .login-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #333;
        }

        .login-title span {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: .18em;
            color: #1a3a4a;
            white-space: nowrap;
            text-transform: uppercase;
        }

        /* ── Inputs ── */
        .field {
            margin-bottom: 14px;
        }

        .field input {
            width: 100%;
            padding: 9px 14px;
            border: 1px solid #b8d4e8;
            border-radius: 20px;
            font-size: .92rem;
            color: #333;
            background: #fff;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .field input::placeholder {
            color: #aab8c2;
        }

        .field input:focus {
            border-color: #6bb8d8;
            box-shadow: 0 0 0 3px rgba(107, 184, 216, .18);
        }

        /* ── Botón ── */
        .btn-login {
            width: 100%;
            margin-top: 8px;
            padding: 10px;
            border: none;
            border-radius: 20px;
            background: linear-gradient(180deg, #aaddf5 0%, #6db8e0 50%, #4da6d4 100%);
            color: #1a3a4a;
            font-size: .9rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,.18), inset 0 1px 0 rgba(255,255,255,.5);
            transition: filter .15s, transform .1s;
        }

        .btn-login:hover  { filter: brightness(1.06); }
        .btn-login:active { transform: translateY(1px); filter: brightness(.97); }
        .btn-login:disabled { opacity: .7; cursor: not-allowed; }

        /* ── Mensaje de error URL ── */
        .url-error {
            margin-top: 14px;
            font-size: .82rem;
            color: #c0392b;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="pin-wrapper">
        <div class="login-card">

            <div style="text-align:center; margin-bottom:18px;">
                <img src="img/logo_1.png" alt="Logo" style="max-height:72px; max-width:220px; object-fit:contain;">
            </div>

            <div class="login-title">
                <span>Iniciar Sesión</span>
            </div>

            <form id="formLogin" autocomplete="off">
                <div class="field">
                    <input type="text" name="nombre_usuario" id="nombre_usuario"
                        placeholder="Usuario" required autofocus>
                </div>
                <div class="field">
                    <input type="password" name="password" id="password"
                        placeholder="Contraseña" required>
                </div>
                <button type="submit" class="btn-login" id="btnLogin">Ingresar</button>
            </form>

            <?php if (isset($_GET['error'])): ?>
                <p class="url-error"><?= htmlspecialchars($_GET['error']) ?></p>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
    $(document).ready(function () {
        $('#formLogin').submit(function (e) {
            e.preventDefault();

            const btn = $('#btnLogin');
            btn.prop('disabled', true).text('Verificando...');

            $.ajax({
                url: 'procesar_login.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Bienvenido!',
                            text: 'Redirigiendo...',
                            timer: 1200,
                            showConfirmButton: false
                        }).then(() => window.location.href = res.redirect);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Acceso denegado', text: res.message });
                        btn.prop('disabled', false).text('Ingresar');
                    }
                },
                error: function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con el servidor.' });
                    btn.prop('disabled', false).text('Ingresar');
                }
            });
        });
    });
    </script>
</body>
</html>
