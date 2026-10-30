<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/seguridad.php';

// Atiende una solicitud de "Olvidé mi contraseña". Solo Administrador, solo por POST.
//   accion=restablecer: crea una contraseña temporal (se muestra una sola vez) y obliga a cambiarla al entrar.
//   accion=descartar:   cierra la solicitud sin tocar la contraseña.
requerir_admin();
exigir_post_con_csrf();

$id = (int) ($_POST['id'] ?? 0);
$accion = (string) ($_POST['accion'] ?? '');
$pdo = db();

$st = $pdo->prepare('SELECT nombre FROM funcionario WHERE id_funcionario = ? AND activo = 1');
$st->execute([$id]);
$nombre = $st->fetchColumn();

if ($nombre === false) {
    avisar('El funcionario no existe o está dado de baja.', 'danger');
} elseif ($accion === 'restablecer') {
    $temporal = '';
    for ($i = 0; $i < 10; $i++) {
        $temporal .= CAPTCHA_CARACTERES[random_int(0, strlen(CAPTCHA_CARACTERES) - 1)];
    }
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE funcionario SET contrasena = ?, debe_cambiar_clave = 1 WHERE id_funcionario = ?')
        ->execute([password_hash($temporal, PASSWORD_DEFAULT), $id]);
    $pdo->prepare('UPDATE solicitud_clave SET atendida = 1 WHERE id_funcionario = ?')->execute([$id]);
    $pdo->commit();
    login_limpiar_fallos((string) $nombre);   // por si además estaba bloqueado por intentos fallidos
    avisar('Contraseña temporal de ' . nombre_visible((string) $nombre) . ': ' . $temporal
        . ' — Entréguesela en persona; se muestra una sola vez y tendrá que cambiarla al iniciar sesión.', 'warning');
} elseif ($accion === 'descartar') {
    $pdo->prepare('UPDATE solicitud_clave SET atendida = 1 WHERE id_funcionario = ?')->execute([$id]);
    avisar('Solicitud descartada.', 'info');
} else {
    avisar('Acción inválida.', 'danger');
}
redirigir('funcionarios/index.php');
