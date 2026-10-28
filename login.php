<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (usuario_actual()) {
    redirigir('panel.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_con_csrf();
    $nombre = trim((string) ($_POST['usuario'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');

    // Solo pueden entrar funcionarios activos (los dados de baja no).
    $st = db()->prepare('SELECT id_funcionario, contrasena FROM funcionario WHERE nombre = ? AND activo = 1');
    $st->execute([$nombre]);
    $fila = $st->fetch();

    if ($fila && password_verify($clave, $fila['contrasena'])) {
        session_regenerate_id(true);   // evita que se reutilice una sesión anterior
        $_SESSION['id_funcionario'] = (int) $fila['id_funcionario'];
        redirigir('panel.php');
    }
    $error = 'Usuario o contraseña incorrectos.';
}

/**
 * Imagen de fondo y logo: si existe assets/img/login-fondo.(jpg|jpeg|png|webp) o
 * assets/img/logo.(png|svg|webp|jpg) se usan solos; si no, se usan los que vienen por defecto.
 */
function archivo_img(array $nombres): ?string
{
    foreach ($nombres as $n) {
        if (is_file(__DIR__ . '/assets/img/' . $n)) {
            return url('assets/img/' . $n);
        }
    }
    return null;
}
$fondo = archivo_img(['login-fondo.jpg', 'login-fondo.jpeg', 'login-fondo.png', 'login-fondo.webp', 'login-fondo.svg']);
$logo = archivo_img(['logo.png', 'logo.svg', 'logo.webp', 'logo.jpg']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · S.I.G.S.M.</title>
    <link rel="stylesheet" href="<?= h(url('assets/css/login_sistema.css')) ?>">
</head>
<body>
<div class="login-wrapper">

    <div class="login-left"<?= $fondo ? ' style="background-image: url(\'' . h($fondo) . '\');"' : '' ?>>
        <div class="login-overlay">
            <div class="login-brand">
                <h1>S.I.G.S.M.</h1>
                <h3>Sistema de Gestión de Servicios Médicos</h3>
                <p>Documentos, guías generales, encuestas de satisfacción y funcionarios del Hospital de Clínicas, en un solo lugar.</p>
            </div>
        </div>
    </div>

    <div class="login-right">
        <div class="login-box">
            <?php if ($logo): ?>
                <img class="login-logo" src="<?= h($logo) ?>" alt="Logo">
            <?php endif; ?>
            <h2>Iniciar sesión</h2>
            <p class="login-subtitulo">Ingrese con su usuario de funcionario</p>

            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <?= csrf_campo() ?>
                <div class="login-campo">
                    <label for="usuario">Usuario</label>
                    <input id="usuario" name="usuario" required autofocus value="<?= h($_POST['usuario'] ?? '') ?>">
                </div>
                <div class="login-campo">
                    <label for="clave">Contraseña</label>
                    <input id="clave" name="clave" type="password" required>
                </div>
                <button type="submit">Iniciar sesión</button>
            </form>

            <p class="login-nota">Acceso exclusivo para funcionarios del hospital.</p>
        </div>
    </div>

</div>
</body>
</html>
