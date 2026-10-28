<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/encuestas.php';

// Encuestas (plantillas) creadas: solo la lista, con buscador por nombre y filtro por sector.
// Solo Administrador. Las respuestas de los pacientes no se muestran acá.
requerir_admin();
$pdo = db();

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

$st = $pdo->prepare("SELECT COUNT(*) FROM encuesta e $sqlDonde");
$st->execute($params);
$total = (int) $st->fetchColumn();

$porPagina = 15;
[$pagina, $paginas, $desde] = paginar($total, $porPagina);

$st = $pdo->prepare(
    "SELECT e.id_encuesta, e.titulo_encuesta, e.activa, e.fecha_modificacion,
            s.nombre_sector,
            (SELECT COUNT(*) FROM pregunta p WHERE p.id_encuesta = e.id_encuesta) AS cantidad_preguntas
       FROM encuesta e
       JOIN sector s ON s.id_sector = e.id_sector
       $sqlDonde
      ORDER BY e.fecha_modificacion DESC, e.id_encuesta DESC
      LIMIT $porPagina OFFSET $desde"
);
$st->execute($params);
$encuestas = $st->fetchAll();

$sectores = listar_sectores();
$hayFiltros = ($q !== '' || $sectorSel > 0);
// Para elegir el mensaje cuando la lista sale vacía: ¿no hay ninguna encuesta o solo ninguna con esos filtros?
$hayEncuestas = $encuestas ? true : (bool) $pdo->query('SELECT 1 FROM encuesta LIMIT 1')->fetchColumn();

encabezado('Encuestas', 'encuestas');
?>
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
                <a class="btn btn-outline-secondary" href="<?= h(url('encuestas/index.php')) ?>" title="Quitar filtros">Limpiar</a>
            </div>
        <?php endif; ?>
    </form>

    <a class="btn btn-success" href="<?= h(url('encuestas/formulario.php')) ?>">Nueva encuesta</a>
</div>

<div class="card">
    <?php if (!$encuestas): ?>
        <div class="card-body text-secondary">
            <?= $hayEncuestas ? 'No se encontraron encuestas con esa búsqueda.' : 'Todavía no hay encuestas creadas.' ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tabla-acciones">
                <thead>
                    <tr>
                        <th>Encuesta</th><th>Sector</th><th>Preguntas</th><th>Estado</th><th>Modificada</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($encuestas as $e): $id = (int) $e['id_encuesta']; ?>
                    <tr>
                        <td><?= h($e['titulo_encuesta']) ?></td>
                        <td><?= h($e['nombre_sector']) ?></td>
                        <td><?= (int) $e['cantidad_preguntas'] ?></td>
                        <td>
                            <?= $e['activa']
                                ? '<span class="badge text-bg-success">Activa</span>'
                                : '<span class="badge text-bg-secondary">Inactiva</span>' ?>
                        </td>
                        <td class="text-nowrap"><?= h(formatear_fecha($e['fecha_modificacion'])) ?></td>
                        <td class="text-end text-nowrap">
                            <form method="POST" action="<?= h(url('encuestas/estado.php')) ?>"
                                  <?= $e['activa'] ? 'onsubmit="return confirm(\'¿Desactivar esta encuesta? Dejará de mostrarse a los pacientes.\');"' : '' ?>>
                                <?= csrf_campo() ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="activa" value="<?= $e['activa'] ? 0 : 1 ?>">
                                <button class="btn btn-sm btn-outline-<?= $e['activa'] ? 'secondary' : 'success' ?>" type="submit">
                                    <?= $e['activa'] ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                            <a class="btn btn-sm btn-outline-warning"
                               href="<?= h(url('encuestas/formulario.php?id=' . $id)) ?>">Editar</a>
                            <form method="POST" action="<?= h(url('encuestas/eliminar.php')) ?>"
                                  onsubmit="return confirm('¿Eliminar esta encuesta con todas sus preguntas? Esta acción no se puede deshacer.');">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-secondary"><?= $total ?> encuesta<?= $total === 1 ? '' : 's' ?></small>
            <?= html_paginacion($pagina, $paginas) ?>
        </div>
    <?php endif; ?>
</div>
<?php pie(); ?>
