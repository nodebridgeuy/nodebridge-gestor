<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/archivos.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/portal.php';

// Alta (sin id) y edición (?id=N) de documentos y de guías generales. Solo Administrador.
$usuario = requerir_admin();
$pdo = db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$doc = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM documento WHERE id_documento = ?');
    $st->execute([$id]);
    $doc = $st->fetch();
    if (!$doc) {
        avisar('El documento no existe.', 'danger');
        redirigir('documentos/index.php');
    }
}

// Al editar, el tipo lo dice el propio registro; al crear, viene de la sección desde la que se entró.
$esGuia = $doc ? (bool) $doc['es_guia_general'] : (($_GET['tipo'] ?? $_POST['tipo'] ?? '') === 'guias');
$destino = 'documentos/index.php' . ($esGuia ? '?tipo=guias' : '');
$nombreTipo = $esGuia ? 'guía' : 'documento';

$categorias = $pdo->query('SELECT id_categoria, nombre FROM categoria ORDER BY nombre')->fetchAll();
$idsValidos = array_map('intval', array_column($categorias, 'id_categoria'));

$titulo = $doc['titulo_documento'] ?? '';
$categoriaSel = (int) ($doc['id_categoria'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_con_csrf();
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $categoriaSel = (int) ($_POST['categoria'] ?? 0);
    $hayArchivo = isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($titulo === '' || mb_strlen($titulo) > 150) {
        $error = 'Ingrese un título de hasta 150 caracteres.';
    } elseif (!in_array($categoriaSel, $idsValidos, true)) {
        $error = 'Seleccione una categoría.';
    } elseif (!$doc && !$hayArchivo) {
        $error = 'Debe seleccionar un archivo PDF.';
    } else {
        try {
            $nuevo = $hayArchivo ? guardar_pdf_subido($_FILES['archivo']) : null;

            if (!$doc) {
                $st = $pdo->prepare(
                    'INSERT INTO documento (titulo_documento, id_categoria, id_funcionario, ruta_archivo, nombre_original, tamano, es_guia_general)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $st->execute([$titulo, $categoriaSel, $usuario['id_funcionario'], $nuevo['ruta'], $nuevo['nombre_original'], $nuevo['tamano'], $esGuia ? 1 : 0]);
                $id = (int) $pdo->lastInsertId();
                $accionHecha = 'subida';
            } elseif ($nuevo) {
                // Se reemplazó el PDF: se actualiza todo y se borra el archivo viejo.
                $st = $pdo->prepare(
                    'UPDATE documento SET titulo_documento = ?, id_categoria = ?, ruta_archivo = ?, nombre_original = ?, tamano = ?
                      WHERE id_documento = ?'
                );
                $st->execute([$titulo, $categoriaSel, $nuevo['ruta'], $nuevo['nombre_original'], $nuevo['tamano'], $id]);
                borrar_pdf($doc['ruta_archivo']);
                $accionHecha = 'actualizada';
            } else {
                $st = $pdo->prepare('UPDATE documento SET titulo_documento = ?, id_categoria = ? WHERE id_documento = ?');
                $st->execute([$titulo, $categoriaSel, $id]);
                $accionHecha = 'actualizada';
            }

            if ($esGuia) {
                // Las guías generales se envían al portal apenas se guardan.
                $pub = publicar_guia($id);
                if ($pub['ok']) {
                    avisar('Guía ' . $accionHecha . ' y publicada en el portal.');
                } else {
                    avisar('La guía se guardó, pero no se pudo publicar en el portal: ' . $pub['mensaje']
                        . ' Quedó pendiente; puede reintentar desde esta lista con el botón "Publicar".', 'warning');
                }
            } else {
                avisar('Documento ' . ($accionHecha === 'subida' ? 'subido' : 'actualizado') . ' correctamente.');
            }
            redirigir($destino);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

encabezado(($doc ? 'Editar ' : 'Subir ') . ($esGuia ? 'guía general' : 'documento'), $esGuia ? 'guias' : 'documentos');
?>
<div class="card" style="max-width: 640px;">
    <div class="card-body">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($esGuia): ?>
            <div class="alert alert-info py-2 small">Esta guía se publicará en el portal informativo, en la página de la categoría que elija.</div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_campo() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <?php if ($esGuia): ?><input type="hidden" name="tipo" value="guias"><?php endif; ?>

            <div class="mb-3">
                <label class="form-label" for="titulo">Título</label>
                <input class="form-control" id="titulo" name="titulo" maxlength="150" required value="<?= h($titulo) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label" for="categoria">Categoría</label>
                <select class="form-select" id="categoria" name="categoria" required>
                    <option value="">-- Seleccione una categoría --</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int) $c['id_categoria'] ?>"<?= $categoriaSel === (int) $c['id_categoria'] ? ' selected' : '' ?>>
                            <?= h($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="archivo">Archivo PDF<?= $doc ? ' (opcional)' : '' ?></label>
                <input class="form-control" id="archivo" name="archivo" type="file" accept="application/pdf,.pdf"<?= $doc ? '' : ' required' ?>>
                <div class="form-text">
                    <?php if ($doc): ?>
                        Archivo actual: <?= h($doc['nombre_original']) ?>. Elija otro solo si quiere reemplazarlo.
                    <?php endif; ?>
                    Tamaño máximo: <?= limite_mb() ?> MB.
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $doc ? 'Guardar cambios' : ($esGuia ? 'Subir guía' : 'Subir documento') ?></button>
                <a class="btn btn-outline-secondary" href="<?= h(url($destino)) ?>">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php pie(); ?>
