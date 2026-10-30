<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

const ROL_ADMIN = 1;
const ROL_FUNCIONARIO = 2;

/**
 * Datos del funcionario que inició sesión, o null.
 * Se vuelve a consultar en cada página: si lo dan de baja, pierde el acceso enseguida.
 */
function usuario_actual(): ?array
{
    static $usuario = false;
    if ($usuario !== false) {
        return $usuario;
    }
    $usuario = null;
    $id = (int) ($_SESSION['id_funcionario'] ?? 0);
    if ($id > 0) {
        $st = db()->prepare(
            'SELECT f.id_funcionario, f.nombre, f.email, f.id_rol, f.debe_cambiar_clave, r.nombre_rol
               FROM funcionario f
               JOIN rol r ON r.id_rol = f.id_rol
              WHERE f.id_funcionario = ? AND f.activo = 1'
        );
        $st->execute([$id]);
        $usuario = $st->fetch() ?: null;
        if ($usuario === null) {
            unset($_SESSION['id_funcionario']);
        }
    }
    return $usuario;
}

function es_admin(): bool
{
    $u = usuario_actual();
    return $u !== null && (int) $u['id_rol'] === ROL_ADMIN;
}

/** Páginas que puede ver cualquier funcionario con sesión iniciada. */
function requerir_login(): array
{
    $u = usuario_actual();
    if ($u === null) {
        redirigir('login.php');
    }
    // Con una contraseña temporal solo se puede entrar a elegir la nueva.
    if ((int) $u['debe_cambiar_clave'] === 1 && !in_array(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), ['cambiar_clave.php', 'logout.php'], true)) {
        redirigir('cambiar_clave.php');
    }
    return $u;
}

/** Páginas exclusivas del Administrador. */
function requerir_admin(): array
{
    $u = requerir_login();
    if ((int) $u['id_rol'] !== ROL_ADMIN) {
        http_response_code(403);
        require_once __DIR__ . '/layout.php';
        encabezado('Acceso denegado', '');
        echo '<div class="alert alert-danger">Esta sección es solo para administradores.</div>';
        echo '<a class="btn btn-primary" href="' . h(url('panel.php')) . '">Volver al inicio</a>';
        pie();
        exit;
    }
    return $u;
}
