<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

/**
 * Funciones compartidas del módulo de encuestas (las plantillas que administra el Administrador):
 * sectores, lectura y validación del formulario y guardado de las preguntas.
 * Las respuestas de los pacientes NO se manejan acá (ver includes/portal.php).
 */

/** Tipos de pregunta: valor guardado en la base => texto que se muestra. */
const TIPOS_PREGUNTA = [
    'escala' => 'Escala de 1 a 5',
    'opcion' => 'Opción única',
    'texto'  => 'Texto libre',
];

const MAX_PREGUNTAS = 30;   // por encuesta
const MAX_OPCIONES = 15;    // por pregunta de tipo "opción única"

/** Sectores disponibles, ordenados por nombre. */
function listar_sectores(): array
{
    return db()->query('SELECT id_sector, nombre_sector FROM sector ORDER BY nombre_sector')->fetchAll();
}

/** Una pregunta vacía, para el primer renglón de una encuesta nueva o para el botón "Agregar pregunta". */
function pregunta_vacia(): array
{
    return ['id' => 0, 'texto' => '', 'tipo' => 'escala', 'opciones' => '', 'obligatoria' => 1];
}

/** Preguntas guardadas de una encuesta, en su orden, con la misma forma que usa el formulario. */
function preguntas_de_encuesta(int $idEncuesta): array
{
    $st = db()->prepare(
        'SELECT id_pregunta, texto_pregunta, tipo, opciones, obligatoria
           FROM pregunta WHERE id_encuesta = ? ORDER BY orden, id_pregunta'
    );
    $st->execute([$idEncuesta]);

    $preguntas = [];
    foreach ($st->fetchAll() as $f) {
        $preguntas[] = [
            'id' => (int) $f['id_pregunta'],
            'texto' => (string) $f['texto_pregunta'],
            'tipo' => (string) $f['tipo'],
            'opciones' => (string) ($f['opciones'] ?? ''),
            'obligatoria' => (int) $f['obligatoria'],
        ];
    }
    return $preguntas;
}

/**
 * Lee las preguntas que llegaron por POST (campos preguntas[N][id|texto|tipo|opciones|obligatoria]).
 * Respeta el orden en que llegaron y deja las opciones limpias: una por línea, sin vacías ni repetidas.
 */
function preguntas_desde_post(array $post): array
{
    $crudas = $post['preguntas'] ?? [];
    if (!is_array($crudas)) {
        return [];
    }

    $preguntas = [];
    foreach ($crudas as $p) {
        if (!is_array($p)) {
            continue;
        }
        $tipo = (string) ($p['tipo'] ?? 'escala');

        $opciones = '';
        if ($tipo === 'opcion') {
            $lineas = preg_split('/\R/u', (string) ($p['opciones'] ?? '')) ?: [];
            $lineas = array_map('trim', $lineas);
            $lineas = array_values(array_unique(array_filter($lineas, fn(string $l): bool => $l !== '')));
            $opciones = implode("\n", $lineas);
        }

        $preguntas[] = [
            'id' => (int) ($p['id'] ?? 0),
            'texto' => trim((string) ($p['texto'] ?? '')),
            'tipo' => $tipo,
            'opciones' => $opciones,
            'obligatoria' => !empty($p['obligatoria']) ? 1 : 0,
        ];
    }
    return $preguntas;
}

/** Valida el formulario. Devuelve el mensaje de error, o '' si está todo bien. */
function validar_encuesta(string $titulo, string $descripcion, int $idSector, array $idsSectoresValidos, array $preguntas): string
{
    if ($titulo === '' || mb_strlen($titulo) > 150) {
        return 'Ingrese un título de hasta 150 caracteres.';
    }
    if (mb_strlen($descripcion) > 500) {
        return 'La descripción no puede superar los 500 caracteres.';
    }
    if (!in_array($idSector, $idsSectoresValidos, true)) {
        return 'Seleccione un sector.';
    }
    if (count($preguntas) === 0) {
        return 'Agregue al menos una pregunta.';
    }
    if (count($preguntas) > MAX_PREGUNTAS) {
        return 'Una encuesta puede tener hasta ' . MAX_PREGUNTAS . ' preguntas.';
    }

    foreach ($preguntas as $i => $p) {
        $n = $i + 1;
        if ($p['texto'] === '' || mb_strlen($p['texto']) > 255) {
            return "La pregunta $n debe tener un texto de hasta 255 caracteres.";
        }
        if (!isset(TIPOS_PREGUNTA[$p['tipo']])) {
            return "La pregunta $n tiene un tipo de respuesta no válido.";
        }
        if ($p['tipo'] === 'opcion') {
            $opciones = $p['opciones'] === '' ? [] : explode("\n", $p['opciones']);
            if (count($opciones) < 2) {
                return "La pregunta $n es de opción única: escriba al menos 2 opciones (una por línea).";
            }
            if (count($opciones) > MAX_OPCIONES) {
                return "La pregunta $n puede tener hasta " . MAX_OPCIONES . ' opciones.';
            }
            foreach ($opciones as $o) {
                if (mb_strlen($o) > 100) {
                    return "Las opciones de la pregunta $n no pueden superar los 100 caracteres.";
                }
            }
        }
    }
    return '';
}

/**
 * Guarda las preguntas de una encuesta en el orden recibido.
 * Las que ya existían (llegan con su id) se actualizan, las nuevas se agregan y las que ya no vienen se borran.
 * Así cada pregunta conserva su id: más adelante las respuestas de los pacientes van a apuntar a él.
 * Llamar dentro de una transacción.
 */
function guardar_preguntas(PDO $pdo, int $idEncuesta, array $preguntas): void
{
    $st = $pdo->prepare('SELECT id_pregunta FROM pregunta WHERE id_encuesta = ?');
    $st->execute([$idEncuesta]);
    $existentes = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    $actualizar = $pdo->prepare(
        'UPDATE pregunta SET texto_pregunta = ?, tipo = ?, opciones = ?, obligatoria = ?, orden = ?
          WHERE id_pregunta = ? AND id_encuesta = ?'
    );
    $insertar = $pdo->prepare(
        'INSERT INTO pregunta (id_encuesta, texto_pregunta, tipo, opciones, obligatoria, orden)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $conservadas = [];
    foreach (array_values($preguntas) as $i => $p) {
        $opciones = $p['tipo'] === 'opcion' ? $p['opciones'] : null;
        $orden = $i + 1;

        // Un id que no pertenece a esta encuesta (o repetido) se trata como pregunta nueva.
        if ($p['id'] > 0 && in_array($p['id'], $existentes, true) && !in_array($p['id'], $conservadas, true)) {
            $actualizar->execute([$p['texto'], $p['tipo'], $opciones, $p['obligatoria'], $orden, $p['id'], $idEncuesta]);
            $conservadas[] = $p['id'];
        } else {
            $insertar->execute([$idEncuesta, $p['texto'], $p['tipo'], $opciones, $p['obligatoria'], $orden]);
        }
    }

    $borrar = $pdo->prepare('DELETE FROM pregunta WHERE id_pregunta = ? AND id_encuesta = ?');
    foreach (array_diff($existentes, $conservadas) as $idBorrar) {
        $borrar->execute([$idBorrar, $idEncuesta]);
    }
}
