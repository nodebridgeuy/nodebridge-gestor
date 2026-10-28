<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// Listado de funcionarios. Solo Administrador.
$yo = requerir_admin();

$funcionarios = db()->query(
    'SELECT f.id_funcionario, f.nombre, f.email, f.activo, r.nombre_rol
       FROM funcionario f
       JOIN rol r ON r.id_rol = f.id_rol
      ORDER BY f.activo DESC, f.nombre'
)->fetchAll();

encabezado('Funcionarios', 'funcionarios');
?>
<div class="mb-3">
    <a class="btn btn-success" href="<?= h(url('funcionarios/formulario.php')) ?>">Registrar funcionario</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 tabla-acciones">
            <thead>
                <tr><th>Usuario</th><th>Email</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($funcionarios as $f):
                $id = (int) $f['id_funcionario'];
                $esYo = $id === (int) $yo['id_funcionario']; ?>
                <tr<?= $f['activo'] ? '' : ' class="table-secondary text-secondary"' ?>>
                    <td><?= h(nombre_visible($f['nombre'])) ?><?= $esYo ? ' <span class="badge text-bg-light border">Usted</span>' : '' ?></td>
                    <td><?= $f['email'] ? h($f['email']) : '-' ?></td>
                    <td><?= h($f['nombre_rol']) ?></td>
                    <td><?= $f['activo'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">De baja</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-warning" href="<?= h(url('funcionarios/formulario.php?id=' . $id)) ?>">Editar</a>
                        <?php if (!$esYo): ?>
                            <form method="POST" action="<?= h(url('funcionarios/estado.php')) ?>"
                                  onsubmit="return confirm('<?= $f['activo'] ? '¿Dar de baja a este funcionario? Ya no podrá iniciar sesión.' : '¿Reactivar a este funcionario?' ?>');">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="activo" value="<?= $f['activo'] ? 0 : 1 ?>">
                                <button class="btn btn-sm btn-outline-<?= $f['activo'] ? 'danger' : 'success' ?>" type="submit">
                                    <?= $f['activo'] ? 'Dar de baja' : 'Reactivar' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php pie(); ?>
