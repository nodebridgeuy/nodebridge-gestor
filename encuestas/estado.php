<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// Activa o desactiva una encuesta desde la lista. Solo Administrador, solo por POST.
// Las encuestas se crean inactivas; "activa" significa que se muestra a los pacientes.
requerir_admin();
exigir_post_con_csrf();

$id = (int) ($_POST['id'] ?? 0);
$activa = ($_POST['activa'] ?? '') === '1' ? 1 : 0;

try {
    // Solo cambia el estado: la fecha de modificación se conserva (es la del último cambio de contenido).
    $st = db()->prepare('UPDATE encuesta SET activa = ?, fecha_modificacion = fecha_modificacion WHERE id_encuesta = ?');
    $st->execute([$activa, $id]);
    avisar($st->rowCount() > 0
        ? ($activa ? 'Encuesta activada.' : 'Encuesta desactivada.')
        : 'No hubo cambios (la encuesta no existe o ya tenía ese estado).', 'success');
} catch (PDOException $e) {
    error_log('[sigsm] No se pudo cambiar el estado de la encuesta ' . $id . ': ' . $e->getMessage());
    avisar('No se pudo cambiar el estado de la encuesta. Intente de nuevo.', 'danger');
}
redirigir('encuestas/index.php');
