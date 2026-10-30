<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seguridad.php';

// "Olvidé mi contraseña": no se envía ningún correo. La persona deja una solicitud y un administrador
// la ve en Funcionarios y le restablece la contraseña. Pública (no hay sesión), por eso lleva captcha.
if (usuario_actual()) {
    redirigir('panel.php');
}

$error = '';
$enviada = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_con_csrf();
    $dato = trim((string) ($_POST['dato'] ?? ''));

    if ($dato === '' || mb_strlen($dato) > 70) {
        $error = 'Ingrese su usuario, su email o su cédula.';
    } elseif (captcha_disponible() && !captcha_correcto((string) ($_POST['captcha'] ?? ''))) {
        $error = 'El código de seguridad no coincide. Intente con la nueva imagen.';
    } else {
        $pdo = db();
        // Se puede pedir con el usuario, el email o la cédula (con o sin puntos y guion).
        $st = $pdo->prepare('SELECT id_funcionario FROM funcionario WHERE activo = 1 AND (nombre = ? OR email = ? OR cedula = ?)');
        $st->execute([$dato, $dato, normalizar_cedula($dato) ?? '-']);
        $idFunc = $st->fetchColumn();

        if ($idFunc !== false) {
            // Si ya hay una solicitud sin atender de esta persona, no se repite.
            $q = $pdo->prepare('SELECT 1 FROM solicitud_clave WHERE id_funcionario = ? AND atendida = 0');
            $q->execute([(int) $idFunc]);
            if (!$q->fetchColumn()) {
                $pdo->prepare('INSERT INTO solicitud_clave (id_funcionario, fecha) VALUES (?, ?)')
                    ->execute([(int) $idFunc, date('Y-m-d H:i:s')]);
            }
        }
        // Cuenten lo que cuenten los datos, la respuesta es la misma: así no se puede averiguar qué usuarios existen.
        $enviada = true;
    }
}

$fondo = null;
foreach (['login-fondo.jpg', 'login-fondo.jpeg', 'login-fondo.png', 'login-fondo.webp', 'login-fondo.svg'] as $n) {
    if (is_file(__DIR__ . '/assets/img/' . $n)) { $fondo = url('assets/img/' . $n); break; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña · S.I.G.S.M.</title>
    <link rel="stylesheet" href="<?= h(url('assets/css/login_sistema.css')) ?>">
</head>
<body>
<div class="login-wrapper">
    <div class="login-left"<?= $fondo ? ' style="background-image: url(\'' . h($fondo) . '\');"' : '' ?>>
        <div class="login-overlay">
            <div class="login-brand">
                <h1>S.I.G.S.M.</h1>
                <h3>Sistema de Gestión de Servicios Médicos</h3>
            </div>
        </div>
    </div>

    <div class="login-right">
        <div class="login-box">
            <h2>Recuperar contraseña</h2>

            <?php if ($enviada): ?>
                <p class="login-subtitulo">
                    Si los datos corresponden a un funcionario activo, la solicitud quedó registrada.
                    Un administrador la va a ver y le va a entregar una contraseña temporal; al entrar tendrá que elegir una nueva.
                </p>
                <p class="login-nota"><a href="<?= h(url('login.php')) ?>">Volver a iniciar sesión</a></p>
            <?php else: ?>
                <p class="login-subtitulo">Ingrese su usuario, su email o su cédula. No se envía ningún correo: la solicitud le llega a un administrador.</p>

                <?php if ($error !== ''): ?>
                    <div class="login-error" role="alert"><?= h($error) ?></div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <?= csrf_campo() ?>
                    <div class="login-campo">
                        <label for="dato">Usuario, email o cédula</label>
                        <input id="dato" name="dato" required autofocus maxlength="70" value="<?= h($_POST['dato'] ?? '') ?>">
                    </div>
                    <?= captcha_campo() ?>
                    <button type="submit">Solicitar restablecimiento</button>
                </form>
                <p class="login-nota"><a href="<?= h(url('login.php')) ?>">Volver a iniciar sesión</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
