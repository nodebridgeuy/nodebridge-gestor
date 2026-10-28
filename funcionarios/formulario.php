<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// Alta (sin id) y edición (?id=N) de funcionarios. Solo Administrador.
$yo = requerir_admin();
$pdo = db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$func = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT id_funcionario, nombre, email, id_rol FROM funcionario WHERE id_funcionario = ?');
    $st->execute([$id]);
    $func = $st->fetch();
    if (!$func) {
        avisar('El funcionario no existe.', 'danger');
        redirigir('funcionarios/index.php');
    }
}
$editandoMiCuenta = $func && (int) $func['id_funcionario'] === (int) $yo['id_funcionario'];

$roles = $pdo->query('SELECT id_rol, nombre_rol FROM rol ORDER BY id_rol')->fetchAll();
$nombre = $func['nombre'] ?? '';
$email = $func['email'] ?? '';
$idRol = (int) ($func['id_rol'] ?? ROL_FUNCIONARIO);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_con_csrf();
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $clave = (string) ($_POST['contrasena'] ?? '');
    $idRol = $editandoMiCuenta ? (int) $func['id_rol'] : (int) ($_POST['id_rol'] ?? 0);   // nadie cambia su propio rol

    if ($nombre === '' || mb_strlen($nombre) > 50) {
        $error = 'Ingrese un usuario de hasta 50 caracteres.';
    } elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 70)) {
        $error = 'El email no es válido.';
    } elseif (!in_array($idRol, array_map('intval', array_column($roles, 'id_rol')), true)) {
        $error = 'Seleccione un rol.';
    } elseif (!$func && $clave === '') {
        $error = 'Ingrese una contraseña.';
    } elseif ($clave !== '' && strlen($clave) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        try {
            $emailBd = $email !== '' ? $email : null;
            if (!$func) {
                $st = $pdo->prepare('INSERT INTO funcionario (nombre, contrasena, id_rol, email, activo) VALUES (?, ?, ?, ?, 1)');
                $st->execute([$nombre, password_hash($clave, PASSWORD_DEFAULT), $idRol, $emailBd]);
                avisar('Funcionario registrado correctamente.');
            } else {
                $st = $pdo->prepare('UPDATE funcionario SET nombre = ?, email = ?, id_rol = ? WHERE id_funcionario = ?');
                $st->execute([$nombre, $emailBd, $idRol, $id]);
                if ($clave !== '') {   // solo se cambia la contraseña si se escribió una nueva
                    $pdo->prepare('UPDATE funcionario SET contrasena = ? WHERE id_funcionario = ?')
                        ->execute([password_hash($clave, PASSWORD_DEFAULT), $id]);
                }
                avisar('Funcionario actualizado correctamente.');
            }
            redirigir('funcionarios/index.php');
        } catch (PDOException $e) {
            // 23000 = valor repetido en una columna UNIQUE (usuario o email)
            if ($e->getCode() === '23000') {
                $error = 'Ya existe un funcionario con ese usuario o ese email.';
            } else {
                error_log('[sigsm] ' . $e->getMessage());
                $error = 'No se pudo guardar. Intente de nuevo.';
            }
        }
    }
}

encabezado($func ? 'Editar funcionario' : 'Registrar funcionario', 'funcionarios');
?>
<div class="card" style="max-width: 560px;">
    <div class="card-body">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrf_campo() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="mb-3">
                <label class="form-label" for="nombre">Usuario</label>
                <input class="form-control" id="nombre" name="nombre" maxlength="50" required value="<?= h($nombre) ?>">
                <div class="form-text">Es el nombre con el que inicia sesión.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email (opcional)</label>
                <input class="form-control" id="email" name="email" type="email" maxlength="70" value="<?= h($email) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label" for="id_rol">Rol</label>
                <select class="form-select" id="id_rol" name="id_rol"<?= $editandoMiCuenta ? ' disabled' : '' ?>>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id_rol'] ?>"<?= $idRol === (int) $r['id_rol'] ? ' selected' : '' ?>><?= h($r['nombre_rol']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($editandoMiCuenta): ?>
                    <div class="form-text">No puede cambiar su propio rol.</div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="contrasena"><?= $func ? 'Nueva contraseña (dejar vacío para no cambiarla)' : 'Contraseña' ?></label>
                <input class="form-control" id="contrasena" name="contrasena" type="password" minlength="8"
                       autocomplete="new-password"<?= $func ? '' : ' required' ?>>
                <div class="form-text">Mínimo 8 caracteres.</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $func ? 'Guardar cambios' : 'Registrar' ?></button>
                <a class="btn btn-outline-secondary" href="<?= h(url('funcionarios/index.php')) ?>">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php pie(); ?>
