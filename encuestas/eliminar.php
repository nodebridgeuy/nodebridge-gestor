<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// Elimina una encuesta (plantilla) junto con sus preguntas. Solo Administrador, solo por POST.
// Las preguntas se borran solas (ON DELETE CASCADE).
requerir_admin();
exigir_post_con_csrf();

$id = (int) ($_POST['id'] ?? 0);

try {
    $st = db()->prepare('DELETE FROM encuesta WHERE id_encuesta = ?');
    $st->execute([$id]);
    if ($st->rowCount() > 0) {
        avisar('Encuesta eliminada.');
    } else {
        avisar('La encuesta no existe o ya fue eliminada.', 'warning');
    }
} catch (PDOException $e) {
    error_log('[sigsm] No se pudo eliminar la encuesta ' . $id . ': ' . $e->getMessage());
    avisar('No se pudo eliminar la encuesta. Intente de nuevo.', 'danger');
}
redirigir('encuestas/index.php');
