<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/encuestas.php';
require_once __DIR__ . '/../includes/portal.php';

// Alta (sin id) y edición (?id=N) de una encuesta con sus preguntas. Solo Administrador.
$usuario = requerir_admin();
$pdo = db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$encuesta = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM encuesta WHERE id_encuesta = ?');
    $st->execute([$id]);
    $encuesta = $st->fetch() ?: null;
    if ($encuesta === null) {
        avisar('La encuesta no existe o ya fue eliminada.', 'danger');
        redirigir('encuestas/index.php');
    }
}

$sectores = listar_sectores();
$idsSectores = array_map('intval', array_column($sectores, 'id_sector'));

$titulo = (string) ($encuesta['titulo_encuesta'] ?? '');
$descripcion = (string) ($encuesta['descripcion'] ?? '');
$sectorSel = (int) ($encuesta['id_sector'] ?? 0);
$preguntas = $encuesta ? preguntas_de_encuesta($id) : [pregunta_vacia()];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_con_csrf();
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
    $sectorSel = (int) ($_POST['sector'] ?? 0);
    $preguntas = preguntas_desde_post($_POST);

    $error = validar_encuesta($titulo, $descripcion, $sectorSel, $idsSectores, $preguntas);

    if ($error === '') {
        try {
            $pdo->beginTransaction();

            if ($encuesta === null) {
                $st = $pdo->prepare(
                    'INSERT INTO encuesta (titulo_encuesta, descripcion, id_sector, id_funcionario, activa)
                     VALUES (?, ?, ?, ?, 0)'   // siempre se crea inactiva; se activa desde la lista
                );
                $st->execute([$titulo, $descripcion !== '' ? $descripcion : null, $sectorSel, $usuario['id_funcionario']]);
                $idGuardado = (int) $pdo->lastInsertId();
            } else {
                // fecha_modificacion se actualiza siempre, aunque solo hayan cambiado las preguntas.
                $st = $pdo->prepare(
                    'UPDATE encuesta
                        SET titulo_encuesta = ?, descripcion = ?, id_sector = ?, fecha_modificacion = NOW()
                      WHERE id_encuesta = ?'
                );
                $st->execute([$titulo, $descripcion !== '' ? $descripcion : null, $sectorSel, $id]);
                $idGuardado = $id;
            }

            guardar_preguntas($pdo, $idGuardado, $preguntas);
            $pdo->commit();

            avisar($encuesta === null ? 'Encuesta creada correctamente.' : 'Encuesta actualizada correctamente.');

            // Si la encuesta está activa (o ya estaba en el portal), se actualiza la copia del portal.
            $pub = sincronizar_encuesta_con_portal($idGuardado);
            if ($pub['ok'] && $pub['accion'] === 'publicada') {
                avisar('Los cambios ya están en el portal.', 'info');
            } elseif (!$pub['ok']) {
                avisar('La encuesta se guardó, pero no se pudo actualizar el portal: ' . $pub['mensaje']
                    . ' Quedó pendiente; puede reintentar desde la lista con el botón "Publicar".', 'warning');
            }
            redirigir('encuestas/index.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $error = 'Ya existe una encuesta con ese título en ese sector.';
            } else {
                error_log('[sigsm] No se pudo guardar la encuesta: ' . $e->getMessage());
                $error = 'No se pudo guardar la encuesta. Intente de nuevo.';
            }
        }
    }

    if (!$preguntas) {
        $preguntas = [pregunta_vacia()];   // para que el formulario vuelva a mostrar un renglón
    }
}

/** HTML de un renglón de pregunta. $i es su posición (0, 1, 2...); JS la vuelve a numerar al agregar, quitar o mover. */
function fila_pregunta(int $i, array $p): string
{
    ob_start();
    ?>
    <div class="pregunta border rounded p-3 mb-3 bg-light">
        <input type="hidden" name="preguntas[<?= $i ?>][id]" value="<?= (int) $p['id'] ?>">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="numero-pregunta">Pregunta <?= $i + 1 ?></strong>
            <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de la pregunta">
                <button type="button" class="btn btn-outline-secondary" data-accion="subir" title="Subir" aria-label="Subir pregunta">&uarr;</button>
                <button type="button" class="btn btn-outline-secondary" data-accion="bajar" title="Bajar" aria-label="Bajar pregunta">&darr;</button>
                <button type="button" class="btn btn-outline-danger" data-accion="quitar">Quitar</button>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-12 col-md-8">
                <input type="text" class="form-control" name="preguntas[<?= $i ?>][texto]" maxlength="255" required
                       placeholder="Texto de la pregunta" aria-label="Texto de la pregunta" value="<?= h($p['texto']) ?>">
            </div>
            <div class="col-12 col-md-4">
                <select class="form-select tipo-pregunta" name="preguntas[<?= $i ?>][tipo]" aria-label="Tipo de respuesta">
                    <?php foreach (TIPOS_PREGUNTA as $valor => $texto): ?>
                        <option value="<?= h($valor) ?>"<?= $p['tipo'] === $valor ? ' selected' : '' ?>><?= h($texto) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 bloque-opciones"<?= $p['tipo'] === 'opcion' ? '' : ' hidden' ?>>
                <textarea class="form-control" rows="3" name="preguntas[<?= $i ?>][opciones]"
                          placeholder="Una opción por línea (mínimo 2)" aria-label="Opciones de respuesta"><?= h($p['opciones']) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-check-label d-inline-flex align-items-center gap-2">
                    <input class="form-check-input mt-0" type="checkbox" name="preguntas[<?= $i ?>][obligatoria]" value="1"<?= $p['obligatoria'] ? ' checked' : '' ?>>
                    Respuesta obligatoria
                </label>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

encabezado($encuesta ? 'Editar encuesta' : 'Nueva encuesta', 'encuestas');
?>
<div class="card" style="max-width: 860px;">
    <div class="card-body">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="form-encuesta">
            <?= csrf_campo() ?>
            <input type="hidden" name="id" value="<?= $encuesta ? (int) $encuesta['id_encuesta'] : 0 ?>">

            <div class="mb-3">
                <label class="form-label" for="titulo">Nombre de la encuesta</label>
                <input class="form-control" id="titulo" name="titulo" maxlength="150" required value="<?= h($titulo) ?>">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="sector">Sector</label>
                    <select class="form-select" id="sector" name="sector" required>
                        <option value="">-- Seleccione un sector --</option>
                        <?php foreach ($sectores as $s): ?>
                            <option value="<?= (int) $s['id_sector'] ?>"<?= $sectorSel === (int) $s['id_sector'] ? ' selected' : '' ?>>
                                <?= h($s['nombre_sector']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 d-flex align-items-end">
                    <p class="text-secondary small mb-2">
                        <?php if ($encuesta === null): ?>
                            La encuesta se crea <strong>inactiva</strong>; se activa desde la lista de encuestas.
                        <?php else: ?>
                            Estado actual: <strong><?= $encuesta['activa'] ? 'Activa' : 'Inactiva' ?></strong>
                            (se cambia desde la lista de encuestas).
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="descripcion">Descripción <span class="text-secondary">(opcional)</span></label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="2" maxlength="500"><?= h($descripcion) ?></textarea>
            </div>

            <h2 class="h6 mb-3">Preguntas</h2>
            <p id="sin-preguntas" class="text-secondary small" hidden>Todavía no hay preguntas. Agregue la primera.</p>
            <div id="preguntas">
                <?php foreach (array_values($preguntas) as $i => $p): ?>
                    <?= fila_pregunta($i, $p) ?>
                <?php endforeach; ?>
            </div>
            <template id="plantilla-pregunta"><?= fila_pregunta(0, pregunta_vacia()) ?></template>

            <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="agregar-pregunta">+ Agregar pregunta</button>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $encuesta ? 'Guardar cambios' : 'Crear encuesta' ?></button>
                <a class="btn btn-outline-secondary" href="<?= h(url('encuestas/index.php')) ?>">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
// Agregar, quitar y mover preguntas sin recargar la página.
// Cada vez que cambia algo se vuelven a numerar los campos (preguntas[0], preguntas[1]...) para que el orden guardado sea el que se ve.
(function () {
    var contenedor = document.getElementById('preguntas');
    var plantilla = document.getElementById('plantilla-pregunta');
    var aviso = document.getElementById('sin-preguntas');
    var botonAgregar = document.getElementById('agregar-pregunta');
    var MAXIMO = <?= (int) MAX_PREGUNTAS ?>;

    function filas() {
        return contenedor.querySelectorAll('.pregunta');
    }

    function renumerar() {
        var lista = filas();
        lista.forEach(function (fila, i) {
            fila.querySelectorAll('[name^="preguntas["]').forEach(function (campo) {
                campo.name = campo.name.replace(/^preguntas\[\d+\]/, 'preguntas[' + i + ']');
            });
            fila.querySelector('.numero-pregunta').textContent = 'Pregunta ' + (i + 1);
        });
        aviso.hidden = lista.length > 0;
        botonAgregar.disabled = lista.length >= MAXIMO;
    }

    function mostrarOpciones(fila) {
        var esOpcion = fila.querySelector('.tipo-pregunta').value === 'opcion';
        fila.querySelector('.bloque-opciones').hidden = !esOpcion;
    }

    botonAgregar.addEventListener('click', function () {
        if (filas().length >= MAXIMO) {
            return;
        }
        var nueva = plantilla.content.firstElementChild.cloneNode(true);
        contenedor.appendChild(nueva);
        mostrarOpciones(nueva);
        renumerar();
        nueva.querySelector('input[type="text"]').focus();
    });

    contenedor.addEventListener('click', function (e) {
        var boton = e.target.closest('button[data-accion]');
        if (!boton) {
            return;
        }
        var fila = boton.closest('.pregunta');
        var accion = boton.getAttribute('data-accion');
        if (accion === 'quitar') {
            fila.remove();
        } else if (accion === 'subir' && fila.previousElementSibling) {
            contenedor.insertBefore(fila, fila.previousElementSibling);
        } else if (accion === 'bajar' && fila.nextElementSibling) {
            contenedor.insertBefore(fila.nextElementSibling, fila);
        }
        renumerar();
    });

    contenedor.addEventListener('change', function (e) {
        if (e.target.classList.contains('tipo-pregunta')) {
            mostrarOpciones(e.target.closest('.pregunta'));
        }
    });

    filas().forEach(mostrarOpciones);
    renumerar();
})();
</script>
<?php pie(); ?>
