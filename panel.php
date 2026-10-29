<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/portal.php';

$u = requerir_login();
$pdo = db();

// Al entrar, el administrador trae las encuestas nuevas del portal (como máximo cada 5 minutos).
if (es_admin()) {
    $sync = sincronizar_encuestas_al_entrar();
    if ($sync['estado'] === 'ejecutada' && $sync['resultado']['ok'] && $sync['resultado']['nuevas'] > 0) {
        $n = $sync['resultado']['nuevas'];
        avisar('Se recibieron ' . $n . ' respuesta' . ($n === 1 ? '' : 's') . ' nueva' . ($n === 1 ? '' : 's') . ' a encuestas.', 'info');
    }
}

// Cifras reales (nada inventado): salen de la base de datos.
$totalDocs = (int) $pdo->query('SELECT COUNT(*) FROM documento')->fetchColumn();
$totalGuias = (int) $pdo->query('SELECT COUNT(*) FROM documento WHERE es_guia_general = 1')->fetchColumn();

$st = $pdo->prepare('SELECT COUNT(*) FROM documento WHERE fecha_subida >= ?');
$st->execute([date('Y-m-d H:i:s', strtotime('-7 days'))]);
$docsSemana = (int) $st->fetchColumn();

// Encuestas disponibles = las activas que ya están publicadas en el portal (las que los pacientes pueden responder).
$encuestasActivas = (int) $pdo->query('SELECT COUNT(*) FROM encuesta WHERE activa = 1 AND estado_portal = 1')->fetchColumn();
$encuestasTotal = (int) $pdo->query('SELECT COUNT(*) FROM encuesta')->fetchColumn();

// Ambulancias: estados posibles (clave guardada => [texto, color]). Cuando exista el seguimiento
// en tiempo real, solo hay que actualizar la columna `estado`: esta vista ya sabe mostrarla.
const ESTADOS_AMBULANCIA = [
    'disponible'     => ['Disponible', 'success'],
    'en_camino'      => ['En camino', 'warning'],
    'llego_destino'  => ['Llegó a destino', 'primary'],
    'volviendo'      => ['Volviendo', 'info'],
    'fuera_servicio' => ['Fuera de servicio', 'secondary'],
];
$ambulancias = $pdo->query(
    'SELECT id_ambulancia, nombre, conductor, estado, fecha_estado FROM ambulancia ORDER BY nombre, id_ambulancia'
)->fetchAll();
$ambDisponibles = count(array_filter($ambulancias, fn(array $a): bool => $a['estado'] === 'disponible'));

// Los últimos PDF que se subieron
$ultimos = $pdo->query(
    'SELECT d.id_documento, d.titulo_documento, d.fecha_subida, d.es_guia_general, c.nombre AS categoria, f.nombre AS funcionario
       FROM documento d
       JOIN categoria c ON c.id_categoria = d.id_categoria
       LEFT JOIN funcionario f ON f.id_funcionario = d.id_funcionario
      ORDER BY d.fecha_subida DESC, d.id_documento DESC
      LIMIT 6'
)->fetchAll();

// [etiqueta, valor, detalle, enlace (null = sin enlace)]
$tarjetas = [
    ['Ambulancias disponibles', $ambDisponibles, 'de ' . count($ambulancias) . ' registrada' . (count($ambulancias) === 1 ? '' : 's'), 'ambulancias/index.php'],
    ['Encuestas disponibles', $encuestasActivas, 'en el portal, de ' . $encuestasTotal . ' creada' . ($encuestasTotal === 1 ? '' : 's'), es_admin() ? 'encuestas/index.php' : null],
    ['Documentos en el sistema', $totalDocs, 'incluye ' . $totalGuias . ' guía' . ($totalGuias === 1 ? '' : 's') . ' general' . ($totalGuias === 1 ? '' : 'es'), 'documentos/index.php'],
    ['Subidos en los últimos 7 días', $docsSemana, 'documentos y guías', null],
];

encabezado('Inicio', 'panel');
?>
<p class="text-secondary">Bienvenido/a, <strong><?= h(nombre_visible($u['nombre'])) ?></strong>.</p>

<section class="row g-3 mb-4">
    <?php foreach ($tarjetas as [$etiqueta, $valor, $detalle, $enlace]): ?>
        <div class="col-6 col-xl-3">
            <div class="card tarjeta-dato h-100 text-center">
                <div class="card-body">
                    <div class="valor"><?= (int) $valor ?></div>
                    <div class="fw-semibold small mt-1">
                        <?php if ($enlace): ?>
                            <a class="text-reset text-decoration-none stretched-link" href="<?= h(url($enlace)) ?>"><?= h($etiqueta) ?></a>
                        <?php else: ?>
                            <?= h($etiqueta) ?>
                        <?php endif; ?>
                    </div>
                    <div class="text-secondary small"><?= h($detalle) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<div class="row g-4">
    <!-- Sección 1: estado de las ambulancias -->
    <section class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Estado de las ambulancias</h2>
                <a href="<?= h(url('ambulancias/index.php')) ?>" class="small">Ir a ambulancias</a>
            </div>
            <?php if (!$ambulancias): ?>
                <div class="card-body text-secondary">Todavía no hay ambulancias registradas.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Ambulancia</th><th>Conductor del turno</th><th>Estado</th><th>Desde</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ambulancias as $a):
                            [$textoEstado, $colorEstado] = ESTADOS_AMBULANCIA[$a['estado']] ?? [$a['estado'], 'secondary']; ?>
                            <tr>
                                <td class="fw-semibold"><?= h($a['nombre']) ?></td>
                                <td><?= $a['conductor'] !== null && $a['conductor'] !== '' ? h($a['conductor']) : '<span class="text-secondary">Sin asignar</span>' ?></td>
                                <td><span class="badge text-bg-<?= h($colorEstado) ?>"><?= h($textoEstado) ?></span></td>
                                <td class="text-nowrap text-secondary small"><?= h(formatear_fecha($a['fecha_estado'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Sección 2: últimos documentos -->
    <section class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Últimos documentos subidos</h2>
                <a href="<?= h(url('documentos/index.php')) ?>" class="small">Ver todos</a>
            </div>
            <?php if (!$ultimos): ?>
                <div class="card-body text-secondary">Todavía no hay documentos cargados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Título</th><th>Fecha</th><th class="text-end">Acciones</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ultimos as $d): $id = (int) $d['id_documento']; ?>
                            <tr>
                                <td>
                                    <?= h($d['titulo_documento']) ?>
                                    <?php if ($d['es_guia_general']): ?><span class="badge text-bg-info ms-1">Guía general</span><?php endif; ?>
                                    <div class="small text-secondary"><?= h($d['categoria']) ?> · <?= h(nombre_visible($d['funcionario'] ?? 'Desconocido')) ?></div>
                                </td>
                                <td class="text-nowrap small"><?= h(formatear_fecha($d['fecha_subida'])) ?></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"
                                       href="<?= h(url('documentos/ver.php?id=' . $id)) ?>">Ver</a>
                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="<?= h(url('documentos/ver.php?id=' . $id . '&descargar=1')) ?>">Descargar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php pie(); ?>
