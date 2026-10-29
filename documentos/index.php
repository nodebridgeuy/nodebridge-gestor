<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/portal.php';

requerir_login();
$pdo = db();

// Esta pantalla sirve para los dos tipos: documentos internos y guías generales (las que van al portal).
$esGuia = (($_GET['tipo'] ?? '') === 'guias');
$tipoUrl = $esGuia ? '?tipo=guias' : '';

// Filtros (por GET, así se puede compartir o recargar la búsqueda)
$q = trim((string) ($_GET['q'] ?? ''));
$categoriaSel = (int) ($_GET['categoria'] ?? 0);

$donde = ['d.es_guia_general = ?'];
$params = [$esGuia ? 1 : 0];
if ($q !== '') {
    $donde[] = 'd.titulo_documento LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($categoriaSel > 0) {
    $donde[] = 'd.id_categoria = ?';
    $params[] = $categoriaSel;
}
$sqlDonde = 'WHERE ' . implode(' AND ', $donde);

$st = $pdo->prepare("SELECT COUNT(*) FROM documento d $sqlDonde");
$st->execute($params);
$total = (int) $st->fetchColumn();

$porPagina = 15;
[$pagina, $paginas, $desde] = paginar($total, $porPagina);

$st = $pdo->prepare(
    "SELECT d.id_documento, d.titulo_documento, d.tamano, d.fecha_subida, d.estado_portal,
            c.nombre AS categoria, f.nombre AS funcionario
       FROM documento d
       JOIN categoria c ON c.id_categoria = d.id_categoria
       LEFT JOIN funcionario f ON f.id_funcionario = d.id_funcionario
       $sqlDonde
      ORDER BY d.fecha_subida DESC, d.id_documento DESC
      LIMIT $porPagina OFFSET $desde"
);
$st->execute($params);
$documentos = $st->fetchAll();

$categorias = $pdo->query('SELECT id_categoria, nombre FROM categoria ORDER BY nombre')->fetchAll();

$pendientes = 0;
if ($esGuia && es_admin()) {
    $pendientes = (int) $pdo->query('SELECT COUNT(*) FROM documento WHERE es_guia_general = 1 AND estado_portal <> 1')->fetchColumn();
}

$estadosPortal = [
    ESTADO_PORTAL_PENDIENTE => ['Pendiente', 'warning'],
    ESTADO_PORTAL_PUBLICADA => ['Publicada', 'success'],
    ESTADO_PORTAL_DESACTUALIZADA => ['Cambios sin publicar', 'warning'],
];

encabezado($esGuia ? 'Guías generales' : 'Documentos', $esGuia ? 'guias' : 'documentos');
?>
<?php if ($esGuia): ?>
    <p class="text-secondary">
        Las guías generales se publican en el portal informativo, en la página de su categoría.
        <?= es_admin()
            ? 'Los funcionarios pueden subirlas, pero quedan pendientes hasta que un administrador las publique.'
            : 'Puede subir guías: quedan pendientes hasta que un administrador las publique en el portal.' ?>
    </p>
    <?php if (es_admin() && !portal_configurado()): ?>
        <div class="alert alert-warning"><?= h(MSG_PORTAL_SIN_CONFIGURAR) ?> Las guías se guardan, pero quedan pendientes de publicar.</div>
    <?php endif; ?>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
    <form method="GET" class="row g-2 flex-grow-1" style="max-width: 760px;">
        <?php if ($esGuia): ?><input type="hidden" name="tipo" value="guias"><?php endif; ?>
        <div class="col-12 col-md-5">
            <input type="search" class="form-control" name="q" placeholder="Buscar por título..."
                   value="<?= h($q) ?>" aria-label="Buscar por título">
        </div>
        <div class="col-8 col-md-5">
            <select class="form-select" name="categoria" aria-label="Categoría">
                <option value="0">Todas las categorías</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id_categoria'] ?>"<?= $categoriaSel === (int) $c['id_categoria'] ? ' selected' : '' ?>>
                        <?= h($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4 col-md-2 d-grid">
            <button class="btn btn-primary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="d-flex flex-wrap gap-2">
        <?php if (es_admin()): ?>
            <?php if ($esGuia && $pendientes > 0): ?>
                <form method="POST" action="<?= h(url('documentos/publicar.php')) ?>">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="pendientes">
                    <button class="btn btn-outline-primary" type="submit">Publicar pendientes (<?= $pendientes ?>)</button>
                </form>
            <?php endif; ?>
            <?php if ($esGuia): ?>
                <form method="POST" action="<?= h(url('documentos/publicar.php')) ?>"
                      onsubmit="return confirm('Se volverán a enviar TODAS las guías al portal. ¿Continuar?');">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="todas">
                    <button class="btn btn-outline-secondary" type="submit">Republicar todas</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <a class="btn btn-success" href="<?= h(url('documentos/formulario.php' . $tipoUrl)) ?>">
            <?= $esGuia ? 'Subir guía general' : 'Subir documento' ?>
        </a>
    </div>
</div>

<div class="card">
    <?php if (!$documentos): ?>
        <div class="card-body text-secondary">
            <?php if ($q !== '' || $categoriaSel > 0): ?>
                No se encontraron <?= $esGuia ? 'guías' : 'documentos' ?> con esa búsqueda.
            <?php else: ?>
                Todavía no hay <?= $esGuia ? 'guías generales cargadas' : 'documentos cargados' ?>.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tabla-acciones">
                <thead>
                    <tr>
                        <th>Título</th><th>Categoría</th><th>Subido por</th><th>Fecha</th><th>Tamaño</th>
                        <?php if ($esGuia && es_admin()): ?><th>Portal</th><?php endif; ?>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($documentos as $d): $id = (int) $d['id_documento']; ?>
                    <tr>
                        <td><?= h($d['titulo_documento']) ?></td>
                        <td><?= h($d['categoria']) ?></td>
                        <td><?= h(nombre_visible($d['funcionario'] ?? 'Desconocido')) ?></td>
                        <td class="text-nowrap"><?= h(formatear_fecha($d['fecha_subida'])) ?></td>
                        <td class="text-nowrap"><?= h(formatear_tamano((int) $d['tamano'])) ?></td>
                        <?php if ($esGuia && es_admin()):
                            [$textoEstado, $colorEstado] = $estadosPortal[(int) $d['estado_portal']] ?? ['?', 'secondary']; ?>
                            <td><span class="badge text-bg-<?= h($colorEstado) ?>"><?= h($textoEstado) ?></span></td>
                        <?php endif; ?>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"
                               href="<?= h(url('documentos/ver.php?id=' . $id)) ?>">Ver</a>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= h(url('documentos/ver.php?id=' . $id . '&descargar=1')) ?>">Descargar</a>
                            <?php if (es_admin()): ?>
                                <?php if ($esGuia && (int) $d['estado_portal'] !== ESTADO_PORTAL_PUBLICADA): ?>
                                    <form method="POST" action="<?= h(url('documentos/publicar.php')) ?>">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="accion" value="una">
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <button class="btn btn-sm btn-outline-success" type="submit">Publicar</button>
                                    </form>
                                <?php endif; ?>
                                <a class="btn btn-sm btn-outline-warning"
                                   href="<?= h(url('documentos/formulario.php?id=' . $id)) ?>">Editar</a>
                                <form method="POST" action="<?= h(url('documentos/eliminar.php')) ?>"
                                      onsubmit="return confirm('<?= $esGuia ? '¿Eliminar esta guía? También se quitará del portal. Esta acción no se puede deshacer.' : '¿Eliminar este documento? Esta acción no se puede deshacer.' ?>');">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-secondary"><?= $total ?> <?= $esGuia ? ($total === 1 ? 'guía' : 'guías') : ($total === 1 ? 'documento' : 'documentos') ?></small>
            <?= html_paginacion($pagina, $paginas) ?>
        </div>
    <?php endif; ?>
</div>
<?php pie(); ?>
