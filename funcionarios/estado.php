<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// Da de baja o reactiva a un funcionario (no se borra, para conservar quién subió cada documento).
$yo = requerir_admin();
exigir_post_con_csrf();

$id = (int) ($_POST['id'] ?? 0);
$activo = ($_POST['activo'] ?? '') === '1' ? 1 : 0;

if ($id === (int) $yo['id_funcionario']) {
    avisar('No puede darse de baja a sí mismo.', 'danger');
} else {
    $st = db()->prepare('UPDATE funcionario SET activo = ? WHERE id_funcionario = ?');
    $st->execute([$activo, $id]);
    avisar($st->rowCount() > 0
        ? ($activo ? 'Funcionario reactivado.' : 'Funcionario dado de baja.')
        : 'No hubo cambios (el funcionario no existe o ya tenía ese estado).', 'success');
}
redirigir('funcionarios/index.php');
