<?php
declare(strict_types=1);

/**
 * Estructura común de todas las páginas internas: encabezado, barra lateral y pie.
 * Uso:   encabezado('Título', 'documentos');  ...contenido...  pie();
 * El segundo parámetro marca cuál opción del menú queda activa.
 */

/*
 * Bootstrap: si existe la copia local en assets/vendor/bootstrap/ se usa esa (sirve sin internet,
 * como en la intranet del hospital). Si no, se carga desde el CDN.
 */
function bootstrap_css(): string
{
    if (is_file(__DIR__ . '/../assets/vendor/bootstrap/bootstrap.min.css')) {
        return url('assets/vendor/bootstrap/bootstrap.min.css');
    }
    return 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
}

function bootstrap_js(): string
{
    if (is_file(__DIR__ . '/../assets/vendor/bootstrap/bootstrap.bundle.min.js')) {
        return url('assets/vendor/bootstrap/bootstrap.bundle.min.js');
    }
    return 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
}

/** Opciones del menú. 'admin' => true significa que solo las ve el Administrador. */
function opciones_menu(): array
{
    return [
        'panel'        => ['Inicio',        'panel.php',                false],
        'documentos'   => ['Documentos',    'documentos/index.php',     false],
        'guias'        => ['Guías generales', 'documentos/index.php?tipo=guias', false],
        'encuestas'    => ['Encuestas',     'encuestas/index.php',      true],
        'respuestas'   => ['Respuestas',    'encuestas/respuestas.php', true],
        'ambulancias'  => ['Ambulancias',   'ambulancias/index.php',    false],
        'funcionarios' => ['Funcionarios',  'funcionarios/index.php',   true],
    ];
}

function encabezado(string $titulo, string $activo): void
{
    $u = usuario_actual();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · S.I.G.S.M.</title>
    <link href="<?= h(bootstrap_css()) ?>" rel="stylesheet">
    <link href="<?= h(url('assets/css/estilos.css')) ?>" rel="stylesheet">
</head>
<body>
<header class="navbar navbar-dark bg-primary px-3 py-2">
    <div class="d-flex align-items-center gap-2">
        <?php if ($u): ?>
            <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse"
                    data-bs-target="#menu-lateral" aria-controls="menu-lateral" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
        <?php endif; ?>
        <a class="navbar-brand mb-0 h1 fs-5" href="<?= h(url('panel.php')) ?>">Hospital de Clínicas</a>
        <span class="text-white-50 small d-none d-sm-inline">S.I.G.S.M.</span>
    </div>
    <?php if ($u): ?>
        <div class="text-white small text-end">
            <?= h(nombre_visible($u['nombre'])) ?>
            <span class="badge text-bg-light ms-1"><?= h($u['nombre_rol']) ?></span>
        </div>
    <?php endif; ?>
</header>

<div class="container-fluid">
    <div class="row">
        <?php if ($u): ?>
        <nav id="menu-lateral" class="col-md-3 col-lg-2 bg-dark collapse d-md-block p-3 menu-lateral">
            <div class="list-group list-group-flush">
                <?php foreach (opciones_menu() as $clave => [$texto, $ruta, $soloAdmin]): ?>
                    <?php if ($soloAdmin && !es_admin()) { continue; } ?>
                    <a href="<?= h(url($ruta)) ?>"
                       class="list-group-item list-group-item-action bg-dark text-white border-secondary<?= $clave === $activo ? ' active' : '' ?>">
                        <?= h($texto) ?>
                    </a>
                <?php endforeach; ?>
                <a href="<?= h(url('logout.php')) ?>"
                   class="list-group-item list-group-item-action bg-dark text-danger border-secondary mt-3">Cerrar sesión</a>
            </div>
        </nav>
        <?php endif; ?>

        <main class="<?= $u ? 'col-md-9 col-lg-10' : 'col-12' ?> p-4">
            <?php foreach (avisos_pendientes() as [$tipo, $mensaje]): ?>
                <div class="alert alert-<?= h($tipo) ?> alert-dismissible fade show" role="alert">
                    <?= h($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endforeach; ?>
            <h1 class="h4 mb-4"><?= h($titulo) ?></h1>
<?php
}

function pie(): void
{
    ?>
        </main>
    </div>
</div>

<footer class="text-center text-secondary small py-3 border-top">
    © 2026 Hospital de Clínicas · Sistema de Gestión de Servicios Médicos · NodeBridge
</footer>

<script src="<?= h(bootstrap_js()) ?>"></script>
</body>
</html>
<?php
}
