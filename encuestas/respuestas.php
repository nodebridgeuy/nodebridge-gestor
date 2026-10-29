<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/encuestas.php';
require_once __DIR__ . '/../includes/portal.php';

// Respuestas a encuestas: lo que contestaron los pacientes en el portal, con buscador por nombre
// de la encuesta y filtro por sector. Solo Administrador.
requerir_admin();
$pdo = db();

// Al entrar se traen las respuestas nuevas del portal (como máximo cada 5 minutos, para no sobrecargarlo).
$sync = sincronizar_encuestas_al_entrar();
if ($sync['estado'] === 'ejecutada' && $sync['resultado']['ok'] && $sync['resultado']['nuevas'] > 0) {
    $n = $sync['resultado']['nuevas'];
    avisar('Se recibieron ' . $n . ' respuesta' . ($n === 1 ? '' : 's') . ' nueva' . ($n === 1 ? '' : 's') . ' del portal.', 'info');
}
$estadoSync = estado_sincronizacion();

// Valor numérico y color de cada calificación (1 = muy insatisfecho, 5 = muy satisfecho)
const CALIFICACIONES = [
    'pesima'    => [1, 'Muy mala', 'danger'],
    'mala'      => [2, 'Mala', 'warning'],
    'regular'   => [3, 'Regular', 'secondary'],
    'buena'     => [4, 'Buena', 'info'],
    'excelente' => [5, 'Excelente', 'success'],
];

// Filtros (por GET, así se puede recargar o compartir la búsqueda)
$q = trim((string) ($_GET['q'] ?? ''));
$sectorSel = (int) ($_GET['sector'] ?? 0);

$donde = [];
$params = [];
if ($q !== '') {
    $donde[] = 'e.titulo_encuesta LIKE ?';
    $params[] = '%' . addcslashes($q, '%_\\') . '%';   // % y _ escritos por el usuario se buscan tal cual
}
if ($sectorSel > 0) {
    $donde[] = 'e.id_sector = ?';
    $params[] = $sectorSel;
}
$sqlDonde = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';

// LEFT JOIN: las respuestas sin encuesta asignada también se listan (solo se ocultan al filtrar).
$st = $pdo->prepare(
    "SELECT COUNT(*) FROM respuesta_encuesta r
       LEFT JOIN encuesta e ON e.id_encuesta = r.id_encuesta $sqlDonde"
);
$st->execute($params);
$total = (int) $st->fetchColumn();

$porPagina = 15;
[$pagina, $paginas, $desde] = paginar($total, $porPagina);

$st = $pdo->prepare(
    "SELECT r.id_respuesta, r.clasificacion, r.lugar, r.profesional, r.comentario, r.fecha_portal,
            e.titulo_encuesta, s.nombre_sector
       FROM respuesta_encuesta r
       LEFT JOIN encuesta e ON e.id_encuesta = r.id_encuesta
       LEFT JOIN sector s ON s.id_sector = e.id_sector
       $sqlDonde
      ORDER BY r.fecha_portal DESC, r.id_respuesta DESC
      LIMIT $porPagina OFFSET $desde"
);
$st->execute($params);
$respuestas = $st->fetchAll();

// Respuestas pregunta por pregunta de las filas que se muestran (una sola consulta para toda la página).
$detalles = [];
if ($respuestas) {
    $ids = array_map(fn(array $r): int => (int) $r['id_respuesta'], $respuestas);
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id_respuesta, texto_pregunta, tipo, valor FROM respuesta_detalle WHERE id_respuesta IN ($marcas) ORDER BY id_detalle");
    $st->execute($ids);
    foreach ($st->fetchAll() as $d) {
        $detalles[(int) $d['id_respuesta']][] = $d;
    }
}
// Color de cada valor de la escala de 1 a 5
const COLORES_ESCALA = [1 => 'danger', 2 => 'warning', 3 => 'secondary', 4 => 'info', 5 => 'success'];

$sectores = listar_sectores();
$hayFiltros = ($q !== '' || $sectorSel > 0);
$hayRespuestas = $respuestas ? true : (bool) $pdo->query('SELECT 1 FROM respuesta_encuesta LIMIT 1')->fetchColumn();

encabezado('Respuestas a encuestas', 'respuestas');
?>
<?php if (!portal_configurado()): ?>
    <div class="alert alert-warning"><?= h(MSG_PORTAL_SIN_CONFIGURAR) ?> Mientras tanto no se reciben respuestas nuevas.</div>
<?php elseif ($estadoSync['ultimo_error']): ?>
    <div class="alert alert-warning">
        No se pudo actualizar desde el portal: <?= h($estadoSync['ultimo_error']) ?>
        Se volverá a intentar al entrar de nuevo o con el botón "Actualizar ahora".
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
    <form method="GET" class="row g-2 flex-grow-1" style="max-width: 760px;">
        <div class="col-12 col-md-5">
            <input type="search" class="form-control" name="q" placeholder="Buscar por nombre de la encuesta..."
                   value="<?= h($q) ?>" aria-label="Buscar por nombre de la encuesta">
        </div>
        <div class="col-12 col-md-4">
            <select class="form-select" name="sector" aria-label="Sector">
                <option value="0">Todos los sectores</option>
                <?php foreach ($sectores as $s): ?>
                    <option value="<?= (int) $s['id_sector'] ?>"<?= $sectorSel === (int) $s['id_sector'] ? ' selected' : '' ?>>
                        <?= h($s['nombre_sector']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2 d-grid">
            <button class="btn btn-primary" type="submit">Buscar</button>
        </div>
        <?php if ($hayFiltros): ?>
            <div class="col-6 col-md-1 d-grid">
                <a class="btn btn-outline-secondary" href="<?= h(url('encuestas/respuestas.php')) ?>" title="Quitar filtros">Limpiar</a>
            </div>
        <?php endif; ?>
    </form>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="small text-secondary">
            Última actualización del portal:
            <strong><?= $estadoSync['ultimo_exito'] ? h(formatear_fecha($estadoSync['ultimo_exito'])) : 'todavía no se actualizó' ?></strong>
        </span>
        <?php if (portal_configurado()): ?>
            <form method="POST" action="<?= h(url('encuestas/sincronizar.php')) ?>">
                <?= csrf_campo() ?>
                <button class="btn btn-outline-primary btn-sm" type="submit">Actualizar ahora</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <?php if (!$respuestas): ?>
        <div class="card-body text-secondary">
            <?= $hayRespuestas ? 'No se encontraron respuestas con esa búsqueda.' : 'Todavía no se recibió ninguna respuesta del portal.' ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tabla-acciones">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Encuesta</th><th>Sector</th><th>Respuestas</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($respuestas as $r):
                    $lista = $detalles[(int) $r['id_respuesta']] ?? [];
                    $primeraEscala = null;
                    foreach ($lista as $d) {
                        if ($d['tipo'] === 'escala') { $primeraEscala = (int) $d['valor']; break; }
                    } ?>
                    <tr class="fila-respuesta" style="cursor: pointer;">
                        <td class="text-nowrap"><?= h(formatear_fecha($r['fecha_portal'])) ?></td>
                        <td><?= $r['titulo_encuesta'] !== null ? h($r['titulo_encuesta']) : '<span class="text-secondary">Sin asignar</span>' ?></td>
                        <td><?= $r['nombre_sector'] !== null ? h($r['nombre_sector']) : '<span class="text-secondary">-</span>' ?></td>
                        <td>
                            <details>
                                <summary style="cursor: pointer;">
                                    <?php if ($primeraEscala !== null): ?>
                                        <span class="badge text-bg-<?= h(COLORES_ESCALA[$primeraEscala] ?? 'secondary') ?>"><?= $primeraEscala ?> / 5</span>
                                    <?php endif; ?>
                                    Ver respuestas<?= $lista ? ' (' . count($lista) . ')' : '' ?>
                                </summary>
                                <dl class="mb-0 mt-2 small">
                                    <?php if ($lista): ?>
                                        <?php foreach ($lista as $d): ?>
                                            <dt class="fw-semibold"><?= h($d['texto_pregunta']) ?></dt>
                                            <dd class="mb-2" style="white-space: pre-line;">
                                                <?php if ($d['tipo'] === 'escala'): ?>
                                                    <span class="badge text-bg-<?= h(COLORES_ESCALA[(int) $d['valor']] ?? 'secondary') ?>"><?= (int) $d['valor'] ?> / 5</span>
                                                <?php else: ?>
                                                    <?= h($d['valor']) ?>
                                                <?php endif; ?>
                                            </dd>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php // Respuesta del formulario anterior (campos fijos) ?>
                                        <?php [$valorAnt, $textoAnt, $colorAnt] = CALIFICACIONES[$r['clasificacion'] ?? ''] ?? [0, (string) ($r['clasificacion'] ?? '-'), 'secondary']; ?>
                                        <dt class="fw-semibold">Calificación</dt>
                                        <dd class="mb-2"><span class="badge text-bg-<?= h($colorAnt) ?>"><?= $valorAnt ?> · <?= h($textoAnt) ?></span></dd>
                                        <dt class="fw-semibold">Lugar / Profesional</dt>
                                        <dd class="mb-2"><?= h((string) $r['lugar']) ?> / <?= h((string) $r['profesional']) ?></dd>
                                        <dt class="fw-semibold">Comentario</dt>
                                        <dd class="mb-0"><?= $r['comentario'] !== null && $r['comentario'] !== '' ? h($r['comentario']) : '<span class="text-secondary">Sin comentario</span>' ?></dd>
                                    <?php endif; ?>
                                </dl>
                            </details>
                        </td>
                        <td class="text-end">
                            <form method="POST" action="<?= h(url('encuestas/respuestas_eliminar.php')) ?>"
                                  onsubmit="return confirm('¿Eliminar esta respuesta?');">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="id" value="<?= (int) $r['id_respuesta'] ?>">
                                <input type="hidden" name="q" value="<?= h($q) ?>">
                                <input type="hidden" name="sector" value="<?= $sectorSel ?>">
                                <input type="hidden" name="pagina" value="<?= $pagina ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-secondary"><?= $total ?> respuesta<?= $total === 1 ? '' : 's' ?></small>
            <?= html_paginacion($pagina, $paginas) ?>
        </div>
    <?php endif; ?>
</div>
<script>
// Toda la fila abre o cierra las respuestas (menos el botón Eliminar y el propio "Ver respuestas",
// que ya lo hace solo).
document.querySelectorAll('tr.fila-respuesta').forEach(function (fila) {
    fila.addEventListener('click', function (e) {
        if (e.target.closest('button, a, form, summary, dl')) { return; }
        var d = fila.querySelector('details');
        if (d) { d.open = !d.open; }
    });
});
</script>
<?php pie(); ?>
