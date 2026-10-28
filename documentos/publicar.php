<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/portal.php';

// Envía guías generales al portal. Solo Administrador, solo por POST.
//   accion=una (id)  ·  accion=pendientes (las que faltan)  ·  accion=todas (vuelve a enviar todo)
requerir_admin();
exigir_post_con_csrf();
set_time_limit(300);

$accion = (string) ($_POST['accion'] ?? '');
$pdo = db();

if ($accion === 'una') {
    $ids = [(int) ($_POST['id'] ?? 0)];
} elseif ($accion === 'pendientes') {
    $ids = array_map('intval', $pdo->query('SELECT id_documento FROM documento WHERE es_guia_general = 1 AND estado_portal <> 1 ORDER BY id_documento')->fetchAll(PDO::FETCH_COLUMN));
} elseif ($accion === 'todas') {
    $ids = array_map('intval', $pdo->query('SELECT id_documento FROM documento WHERE es_guia_general = 1 ORDER BY id_documento')->fetchAll(PDO::FETCH_COLUMN));
} else {
    avisar('Acción inválida.', 'danger');
    redirigir('documentos/index.php?tipo=guias');
}

if (!$ids) {
    avisar('No hay guías para publicar.', 'info');
    redirigir('documentos/index.php?tipo=guias');
}

$publicadas = 0;
$fallidas = 0;
$ultimoError = '';
$interrumpido = false;
foreach ($ids as $id) {
    $r = publicar_guia($id);
    if ($r['ok']) {
        $publicadas++;
        continue;
    }
    $fallidas++;
    $ultimoError = $r['mensaje'];
    if ($r['abortar']) {   // sin conexión o clave mal puesta: no tiene sentido seguir intentando con el resto
        $interrumpido = true;
        break;
    }
}

if ($publicadas > 0) {
    avisar('Se publicaron ' . $publicadas . ' guía' . ($publicadas === 1 ? '' : 's') . ' en el portal.');
}
if ($fallidas > 0) {
    avisar(($interrumpido ? 'Se interrumpió el envío. ' : 'No se pudo publicar ' . $fallidas . ' guía' . ($fallidas === 1 ? '' : 's') . '. ')
        . 'Motivo: ' . $ultimoError, 'warning');
}
redirigir('documentos/index.php?tipo=guias');
