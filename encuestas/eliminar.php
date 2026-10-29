<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/portal.php';

// Elimina una encuesta (plantilla) junto con sus preguntas. Solo Administrador, solo por POST.
// Las preguntas se borran solas (ON DELETE CASCADE).
requerir_admin();
exigir_post_con_csrf();

$id = (int) ($_POST['id'] ?? 0);

try {
    // Si la encuesta está en el portal, primero se retira de allí: si no se pudiera, no se borra,
    // para no dejar publicada una encuesta que ya nadie puede administrar.
    $q = db()->prepare('SELECT estado_portal FROM encuesta WHERE id_encuesta = ?');
    $q->execute([$id]);
    $enPortal = $q->fetchColumn();
    if ($enPortal !== false && (int) $enPortal !== ESTADO_PORTAL_PENDIENTE) {
        $r = despublicar_encuesta($id);
        if (!$r['ok']) {
            avisar('No se eliminó: no se pudo retirar la encuesta del portal (' . $r['mensaje'] . '). Intente de nuevo cuando haya conexión.', 'danger');
            redirigir('encuestas/index.php');
        }
    }

    $st = db()->prepare('DELETE FROM encuesta WHERE id_encuesta = ?');
    $st->execute([$id]);
    if ($st->rowCount() > 0) {
        avisar('Encuesta eliminada.' . ($enPortal !== false && (int) $enPortal !== ESTADO_PORTAL_PENDIENTE ? ' También se retiró del portal.' : ''));
    } else {
        avisar('La encuesta no existe o ya fue eliminada.', 'warning');
    }
} catch (PDOException $e) {
    error_log('[sigsm] No se pudo eliminar la encuesta ' . $id . ': ' . $e->getMessage());
    avisar('No se pudo eliminar la encuesta. Intente de nuevo.', 'danger');
}
redirigir('encuestas/index.php');
