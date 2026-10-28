<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/archivos.php';
require_once __DIR__ . '/../includes/portal.php';

// Elimina un documento o una guía y su PDF. Solo Administrador, solo por POST.
requerir_admin();
exigir_post_con_csrf();

$id = (int) ($_POST['id'] ?? 0);
$pdo = db();

$st = $pdo->prepare('SELECT ruta_archivo, es_guia_general, estado_portal FROM documento WHERE id_documento = ?');
$st->execute([$id]);
$doc = $st->fetch();

if (!$doc) {
    avisar('El documento no existe o ya fue eliminado.', 'warning');
    redirigir('documentos/index.php');
}

$esGuia = (bool) $doc['es_guia_general'];
$destino = 'documentos/index.php' . ($esGuia ? '?tipo=guias' : '');

// Si la guía ya está en el portal, primero se quita de allí: si no se pudiera, no se borra,
// para no dejar una copia pública que nadie puede administrar.
if ($esGuia && (int) $doc['estado_portal'] !== ESTADO_PORTAL_PENDIENTE) {
    $r = despublicar_guia($id);
    if (!$r['ok']) {
        avisar('No se eliminó: no se pudo quitar la guía del portal (' . $r['mensaje'] . '). Intente de nuevo cuando haya conexión.', 'danger');
        redirigir($destino);
    }
}

$pdo->prepare('DELETE FROM documento WHERE id_documento = ?')->execute([$id]);
borrar_pdf($doc['ruta_archivo']);
avisar($esGuia ? 'Guía eliminada (también del portal).' : 'Documento eliminado.');
redirigir($destino);
