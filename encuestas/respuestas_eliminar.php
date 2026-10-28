<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// Elimina una respuesta recibida del portal. Solo Administrador, solo por POST.
requerir_admin();
exigir_post_con_csrf();

$st = db()->prepare('DELETE FROM respuesta_encuesta WHERE id_respuesta = ?');
$st->execute([(int) ($_POST['id'] ?? 0)]);

avisar($st->rowCount() > 0 ? 'Respuesta eliminada.' : 'La respuesta no existe o ya fue eliminada.', $st->rowCount() > 0 ? 'success' : 'warning');

// Vuelve a la misma lista, con los mismos filtros y página (solo se aceptan estos tres valores).
$volver = ['q' => mb_substr(trim((string) ($_POST['q'] ?? '')), 0, 150)];
if ((int) ($_POST['sector'] ?? 0) > 0) {
    $volver['sector'] = (int) $_POST['sector'];
}
if ((int) ($_POST['pagina'] ?? 1) > 1) {
    $volver['pagina'] = (int) $_POST['pagina'];
}
$query = http_build_query(array_filter($volver, fn($v) => $v !== ''));
redirigir('encuestas/respuestas.php' . ($query !== '' ? '?' . $query : ''));
