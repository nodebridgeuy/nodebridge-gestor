<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/portal.php';

// Deja el portal al día con las encuestas. Solo Administrador, solo por POST.
//   accion=una (id)  ·  accion=pendientes (las que faltan publicar o retirar)  ·  accion=todas (vuelve a enviar todo)
requerir_admin();
exigir_post_con_csrf();
set_time_limit(300);

$accion = (string) ($_POST['accion'] ?? '');
$pdo = db();

if ($accion === 'una') {
    $ids = [(int) ($_POST['id'] ?? 0)];
} elseif ($accion === 'pendientes') {
    $ids = $pdo->query('SELECT id_encuesta FROM encuesta WHERE ' . SQL_ENCUESTAS_PENDIENTES_PORTAL . ' ORDER BY id_encuesta')->fetchAll(PDO::FETCH_COLUMN);
} elseif ($accion === 'todas') {
    $ids = $pdo->query('SELECT id_encuesta FROM encuesta WHERE activa = 1 OR estado_portal <> 0 ORDER BY id_encuesta')->fetchAll(PDO::FETCH_COLUMN);
} else {
    avisar('Acción inválida.', 'danger');
    redirigir('encuestas/index.php');
}
$ids = array_map('intval', $ids);

if (!$ids) {
    avisar('No hay encuestas para actualizar en el portal.', 'info');
    redirigir('encuestas/index.php');
}

$publicadas = 0;
$retiradas = 0;
$fallidas = 0;
$ultimoError = '';
$interrumpido = false;
foreach ($ids as $id) {
    $r = sincronizar_encuesta_con_portal($id);
    if ($r['ok']) {
        $publicadas += $r['accion'] === 'publicada' ? 1 : 0;
        $retiradas += $r['accion'] === 'retirada' ? 1 : 0;
        continue;
    }
    $fallidas++;
    $ultimoError = $r['mensaje'];
    if ($r['abortar']) {   // sin conexión o clave mal puesta: no tiene sentido seguir con el resto
        $interrumpido = true;
        break;
    }
}

if ($publicadas > 0) {
    avisar('Se publicaron ' . $publicadas . ' encuesta' . ($publicadas === 1 ? '' : 's') . ' en el portal.');
}
if ($retiradas > 0) {
    avisar('Se retiraron ' . $retiradas . ' encuesta' . ($retiradas === 1 ? '' : 's') . ' del portal.');
}
if ($fallidas > 0) {
    avisar(($interrumpido ? 'Se interrumpió el envío. ' : 'No se pudo actualizar ' . $fallidas . ' encuesta' . ($fallidas === 1 ? '' : 's') . '. ')
        . 'Motivo: ' . $ultimoError, 'warning');
}
if ($publicadas === 0 && $retiradas === 0 && $fallidas === 0) {
    avisar('El portal ya estaba al día.', 'info');
}
redirigir('encuestas/index.php');
