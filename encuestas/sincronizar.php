<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/portal.php';

// Botón "Actualizar ahora". Solo Administrador, solo por POST.
// Tiene un enfriamiento de 2 minutos (compartido con la actualización automática) para no saturar el portal.
requerir_admin();
exigir_post_con_csrf();

$r = intentar_sincronizar_encuestas(120, 15);

if ($r['estado'] === 'sin_configurar') {
    avisar(MSG_PORTAL_SIN_CONFIGURAR, 'warning');
} elseif ($r['estado'] === 'espera') {
    $s = $r['espera'];
    avisar('Se actualizó hace muy poco. Podrá volver a actualizar en ' . ($s >= 60 ? ceil($s / 60) . ' min' : $s . ' s') . '.', 'info');
} elseif ($r['resultado']['ok']) {
    $n = $r['resultado']['nuevas'];
    avisar($n > 0 ? 'Se recibieron ' . $n . ' encuesta' . ($n === 1 ? '' : 's') . ' nueva' . ($n === 1 ? '' : 's') . '.' : 'No hay encuestas nuevas en el portal.');
} else {
    avisar('No se pudo actualizar: ' . $r['resultado']['mensaje'], 'danger');
}
redirigir('encuestas/respuestas.php');
