<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

// Cada funcionario cambia su propia contraseña. Es obligatorio si entró con una contraseña temporal.
$u = requerir_login();
$obligatorio = (int) $u['debe_cambiar_clave'] === 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_con_csrf();
    $actual = (string) ($_POST['actual'] ?? '');
    $nueva = (string) ($_POST['nueva'] ?? '');
    $repetida = (string) ($_POST['repetida'] ?? '');

    $st = db()->prepare('SELECT contrasena FROM funcionario WHERE id_funcionario = ?');
    $st->execute([(int) $u['id_funcionario']]);
    $hash = (string) $st->fetchColumn();

    if (!password_verify($actual, $hash)) {
        $error = 'La contraseña actual no es correcta.';
    } elseif (strlen($nueva) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } elseif ($nueva !== $repetida) {
        $error = 'Las contraseñas nuevas no coinciden.';
    } elseif ($nueva === $actual) {
        $error = 'La nueva contraseña tiene que ser distinta de la actual.';
    } else {
        db()->prepare('UPDATE funcionario SET contrasena = ?, debe_cambiar_clave = 0 WHERE id_funcionario = ?')
            ->execute([password_hash($nueva, PASSWORD_DEFAULT), (int) $u['id_funcionario']]);
        avisar('Contraseña actualizada correctamente.');
        redirigir('panel.php');
    }
}

encabezado('Cambiar mi contraseña', '');
?>
<div class="card" style="max-width: 520px;">
    <div class="card-body">
        <?php if ($obligatorio): ?>
            <div class="alert alert-warning">Entró con una contraseña temporal. Elija una nueva para continuar.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <?= csrf_campo() ?>
            <div class="mb-3">
                <label class="form-label" for="actual"><?= $obligatorio ? 'Contraseña temporal' : 'Contraseña actual' ?></label>
                <input class="form-control" id="actual" name="actual" type="password" required autocomplete="current-password">
            </div>
            <div class="mb-3">
                <label class="form-label" for="nueva">Nueva contraseña</label>
                <input class="form-control" id="nueva" name="nueva" type="password" minlength="8" required autocomplete="new-password">
                <div class="form-text">Mínimo 8 caracteres.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="repetida">Repetir nueva contraseña</label>
                <input class="form-control" id="repetida" name="repetida" type="password" minlength="8" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Guardar contraseña</button>
        </form>
    </div>
</div>
<?php pie(); ?>
