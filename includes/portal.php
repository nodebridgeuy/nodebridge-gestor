<?php
declare(strict_types=1);

/**
 * Conexión con el portal informativo (API de portal-backend/api/).
 * Todas las llamadas las inicia el gestor: el portal nunca entra a la intranet.
 *
 * Configuración (archivo .env):
 *   PORTAL_URL      dirección del backend del portal (por defecto http://localhost/portal-backend)
 *   PORTAL_API_KEY  la misma API_KEY que tiene el portal
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/archivos.php';
require_once __DIR__ . '/encuestas.php';

const ESTADO_PORTAL_PENDIENTE = 0;       // nunca llegó al portal
const ESTADO_PORTAL_PUBLICADA = 1;       // publicada y al día
const ESTADO_PORTAL_DESACTUALIZADA = 2;  // está en el portal, pero faltan los últimos cambios

const MSG_PORTAL_SIN_CONFIGURAR = 'La conexión con el portal no está configurada (falta PORTAL_API_KEY en el archivo .env).';

function portal_configurado(): bool
{
    return env_o('PORTAL_API_KEY', '') !== '';
}

/**
 * Hace una petición a la API del portal.
 * $opciones: 'metodo' (GET|POST), 'query' (array), 'campos' (array, puede incluir CURLFile),
 *            'json' (array), 'timeout' (segundos).
 * Devuelve ['ok' => bool, 'codigo' => int, 'datos' => ?array, 'error' => string, 'sin_conexion' => bool]
 */
function portal_peticion(string $ruta, array $opciones = []): array
{
    $fallo = fn(string $error, int $codigo = 0) => [
        'ok' => false, 'codigo' => $codigo, 'datos' => null, 'error' => $error, 'sin_conexion' => $codigo === 0,
    ];

    if (!portal_configurado()) {
        return $fallo(MSG_PORTAL_SIN_CONFIGURAR);
    }
    if (!function_exists('curl_init')) {
        return $fallo('La extensión curl de PHP no está activada (en XAMPP: php.ini → extension=curl).');
    }

    $url = rtrim(env_o('PORTAL_URL', 'http://localhost/portal-backend'), '/') . '/api/' . $ruta;
    if (!empty($opciones['query'])) {
        $url .= '?' . http_build_query($opciones['query']);
    }

    $cabeceras = ['X-API-Key: ' . env_o('PORTAL_API_KEY', ''), 'Accept: application/json'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => (int) ($opciones['timeout'] ?? 10),
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if (($opciones['metodo'] ?? 'GET') === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (isset($opciones['json'])) {
            $cabeceras[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opciones['json']));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opciones['campos'] ?? []);
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $cabeceras);

    $cuerpo = curl_exec($ch);
    if ($cuerpo === false) {
        $motivo = curl_error($ch);
        error_log('[sigsm] portal: ' . $url . ' → ' . $motivo);
        return $fallo('No se pudo conectar con el portal (' . $motivo . ').');
    }
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $datos = json_decode((string) $cuerpo, true);
    $datos = is_array($datos) ? $datos : null;

    if ($codigo >= 200 && $codigo < 300 && $datos !== null) {
        return ['ok' => true, 'codigo' => $codigo, 'datos' => $datos, 'error' => '', 'sin_conexion' => false];
    }

    $mensaje = $datos['mensaje'] ?? '';
    if ($codigo === 401) {
        $mensaje = 'La clave del gestor (PORTAL_API_KEY) no coincide con la API_KEY del portal.';
    } elseif ($mensaje === '') {
        $mensaje = 'El portal respondió con un error (HTTP ' . $codigo . ').';
    }
    return ['ok' => false, 'codigo' => $codigo, 'datos' => $datos, 'error' => $mensaje, 'sin_conexion' => false];
}

/** Errores que no tiene sentido repetir guía por guía (sin conexión o clave mal configurada). */
function portal_error_global(array $r): bool
{
    return $r['sin_conexion'] || in_array($r['codigo'], [401, 503], true);
}

/* ---------------------------------------------------------------------------------------
 * Guías generales: se publican en el portal con id_externo = id del documento en el gestor.
 * ------------------------------------------------------------------------------------- */

/** Envía (crea o actualiza) una guía general al portal. Devuelve ['ok', 'mensaje', 'abortar']. */
function publicar_guia(int $idDocumento): array
{
    $pdo = db();
    $st = $pdo->prepare(
        'SELECT d.titulo_documento, d.ruta_archivo, d.nombre_original, d.estado_portal, c.nombre AS categoria
           FROM documento d JOIN categoria c ON c.id_categoria = d.id_categoria
          WHERE d.id_documento = ? AND d.es_guia_general = 1'
    );
    $st->execute([$idDocumento]);
    $guia = $st->fetch();
    if (!$guia) {
        return ['ok' => false, 'mensaje' => 'La guía no existe.', 'abortar' => false];
    }

    $ruta = ruta_pdf_segura($guia['ruta_archivo']);
    if ($ruta === null) {
        return ['ok' => false, 'mensaje' => 'No se encuentra el archivo PDF en el servidor.', 'abortar' => false];
    }

    $r = portal_peticion('publicar_documento.php', [
        'metodo' => 'POST',
        'timeout' => 60,
        'campos' => [
            'id_externo' => $idDocumento,
            'titulo' => $guia['titulo_documento'],
            'categoria' => $guia['categoria'],
            'archivo' => new CURLFile($ruta, 'application/pdf', $guia['nombre_original']),
        ],
    ]);

    // Si falla: una guía que nunca llegó sigue pendiente; una que ya estaba queda desactualizada.
    $estado = $r['ok']
        ? ESTADO_PORTAL_PUBLICADA
        : ((int) $guia['estado_portal'] === ESTADO_PORTAL_PENDIENTE ? ESTADO_PORTAL_PENDIENTE : ESTADO_PORTAL_DESACTUALIZADA);
    $pdo->prepare('UPDATE documento SET estado_portal = ? WHERE id_documento = ?')->execute([$estado, $idDocumento]);

    return ['ok' => $r['ok'], 'mensaje' => $r['ok'] ? 'Publicada en el portal.' : $r['error'], 'abortar' => !$r['ok'] && portal_error_global($r)];
}

/** Quita una guía del portal. Si el portal no la tiene (404) se considera hecho. */
function despublicar_guia(int $idDocumento): array
{
    $r = portal_peticion('eliminar_documento.php', [
        'metodo' => 'POST',
        'campos' => ['id_externo' => $idDocumento],
    ]);
    if ($r['ok'] || $r['codigo'] === 404) {
        return ['ok' => true, 'mensaje' => ''];
    }
    return ['ok' => false, 'mensaje' => $r['error']];
}

/* ---------------------------------------------------------------------------------------
 * Encuestas (plantillas): el gestor las publica en el portal mientras estén Activas
 * y las retira cuando se desactivan o se borran. id_externo = id de la encuesta en el gestor.
 * ------------------------------------------------------------------------------------- */

/** Condición SQL de las encuestas cuya copia en el portal no está como corresponde. */
const SQL_ENCUESTAS_PENDIENTES_PORTAL = '((activa = 1 AND estado_portal <> 1) OR (activa = 0 AND estado_portal <> 0))';

/** Texto y color para mostrar el estado de una encuesta respecto del portal. */
function etiqueta_portal_encuesta(int $activa, int $estado): array
{
    if ($activa === 1) {
        return match ($estado) {
            ESTADO_PORTAL_PUBLICADA => ['En el portal', 'success'],
            ESTADO_PORTAL_DESACTUALIZADA => ['Cambios sin publicar', 'warning'],
            default => ['Pendiente de publicar', 'warning'],
        };
    }
    return $estado === ESTADO_PORTAL_PENDIENTE ? ['No visible', 'secondary'] : ['Pendiente de retirar', 'warning'];
}

/** Quita una encuesta del portal. Si el portal no la tiene (404) se considera hecho. */
function despublicar_encuesta(int $idEncuesta): array
{
    $r = portal_peticion('eliminar_encuesta.php', [
        'metodo' => 'POST',
        'campos' => ['id_externo' => $idEncuesta],
    ]);
    if ($r['ok'] || $r['codigo'] === 404) {
        return ['ok' => true, 'mensaje' => ''];
    }
    return ['ok' => false, 'mensaje' => $r['error']];
}

/**
 * Deja la copia del portal como corresponde al estado de la encuesta:
 *   Activa   -> la publica (o actualiza)        Inactiva -> la retira (si estaba)
 * Devuelve ['ok', 'accion' => publicada|retirada|nada|publicar|retirar, 'mensaje', 'abortar'].
 * Si falla, la encuesta queda "pendiente" y se puede reintentar (botón Publicar).
 */
function sincronizar_encuesta_con_portal(int $idEncuesta): array
{
    $pdo = db();
    $st = $pdo->prepare('SELECT activa, estado_portal FROM encuesta WHERE id_encuesta = ?');
    $st->execute([$idEncuesta]);
    $fila = $st->fetch();
    if (!$fila) {
        return ['ok' => true, 'accion' => 'nada', 'mensaje' => '', 'abortar' => false];
    }
    $activa = (int) $fila['activa'];
    $estado = (int) $fila['estado_portal'];

    // Cambiar el estado del portal no cuenta como modificar la encuesta: se conserva fecha_modificacion.
    $guardar = function (int $nuevo) use ($pdo, $idEncuesta): void {
        $pdo->prepare('UPDATE encuesta SET estado_portal = ?, fecha_modificacion = fecha_modificacion WHERE id_encuesta = ?')
            ->execute([$nuevo, $idEncuesta]);
    };
    $sinCambioSiNuncaLlego = $estado === ESTADO_PORTAL_PENDIENTE ? ESTADO_PORTAL_PENDIENTE : ESTADO_PORTAL_DESACTUALIZADA;

    if ($activa === 1) {
        $payload = payload_encuesta($idEncuesta);
        $r = portal_peticion('publicar_encuesta.php', ['metodo' => 'POST', 'json' => $payload, 'timeout' => 20]);
        if ($r['ok']) {
            $guardar(ESTADO_PORTAL_PUBLICADA);
            return ['ok' => true, 'accion' => 'publicada', 'mensaje' => '', 'abortar' => false];
        }
        $guardar($sinCambioSiNuncaLlego);
        return ['ok' => false, 'accion' => 'publicar', 'mensaje' => $r['error'], 'abortar' => portal_error_global($r)];
    }

    if ($estado === ESTADO_PORTAL_PENDIENTE) {
        return ['ok' => true, 'accion' => 'nada', 'mensaje' => '', 'abortar' => false];
    }
    $r = portal_peticion('eliminar_encuesta.php', ['metodo' => 'POST', 'campos' => ['id_externo' => $idEncuesta]]);
    if ($r['ok'] || $r['codigo'] === 404) {
        $guardar(ESTADO_PORTAL_PENDIENTE);
        return ['ok' => true, 'accion' => 'retirada', 'mensaje' => '', 'abortar' => false];
    }
    $guardar(ESTADO_PORTAL_DESACTUALIZADA);
    return ['ok' => false, 'accion' => 'retirar', 'mensaje' => $r['error'], 'abortar' => portal_error_global($r)];
}

/* ---------------------------------------------------------------------------------------
 * Encuestas: el gestor las pide al portal, las guarda y las confirma.
 * ------------------------------------------------------------------------------------- */

const CLASIFICACIONES_VALIDAS = ['pesima', 'mala', 'regular', 'buena', 'excelente'];

/** Trae las respuestas de pacientes que esperan en el portal. Devuelve ['ok', 'nuevas', 'mensaje']. */
function sincronizar_encuestas(int $timeout = 10): array
{
    $pdo = db();
    $nuevas = 0;
    $uuidValido = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    for ($vuelta = 0; $vuelta < 10; $vuelta++) {
        $r = portal_peticion('encuestas_pendientes.php', ['query' => ['limite' => 200], 'timeout' => $timeout]);
        if (!$r['ok']) {
            return ['ok' => false, 'nuevas' => $nuevas, 'mensaje' => $r['error']];
        }
        $lista = is_array($r['datos']['encuestas'] ?? null) ? $r['datos']['encuestas'] : [];
        if (!$lista) {
            break;
        }

        // INSERT IGNORE + uuid único: si una confirmación falló antes, no se duplica nada.
        $insNueva = $pdo->prepare('INSERT IGNORE INTO respuesta_encuesta (uuid, fecha_portal, id_encuesta) VALUES (?, ?, ?)');
        $insDetalle = $pdo->prepare(
            'INSERT INTO respuesta_detalle (id_respuesta, id_pregunta, texto_pregunta, tipo, valor) VALUES (?, ?, ?, ?, ?)'
        );
        $insAnterior = $pdo->prepare(
            'INSERT IGNORE INTO respuesta_encuesta (uuid, clasificacion, lugar, profesional, comentario, fecha_portal, id_encuesta)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        // Si el portal informa de qué encuesta es la respuesta, se guarda el vínculo
        // (solo si esa encuesta existe en el gestor; si no, queda sin asignar). Lo mismo con cada pregunta.
        $encuestasValidas = array_map('intval', $pdo->query('SELECT id_encuesta FROM encuesta')->fetchAll(PDO::FETCH_COLUMN));
        $preguntasDe = [];
        foreach ($pdo->query('SELECT id_pregunta, id_encuesta FROM pregunta')->fetchAll() as $f) {
            $preguntasDe[(int) $f['id_encuesta']][] = (int) $f['id_pregunta'];
        }

        $uuids = [];
        $pdo->beginTransaction();
        try {
            foreach ($lista as $e) {
                $uuid = (string) ($e['uuid'] ?? '');
                if (!preg_match($uuidValido, $uuid)) {
                    continue;
                }
                $uuids[] = $uuid;
                $fecha = (string) ($e['fecha'] ?? date('Y-m-d H:i:s'));
                $idEnc = in_array((int) ($e['id_encuesta'] ?? 0), $encuestasValidas, true) ? (int) $e['id_encuesta'] : null;

                if (is_array($e['respuestas'] ?? null)) {
                    // Formato actual: una respuesta por pregunta contestada.
                    $insNueva->execute([$uuid, $fecha, $idEnc]);
                    if ($insNueva->rowCount() === 0) {
                        continue;   // ya estaba guardada
                    }
                    $idRespuesta = (int) $pdo->lastInsertId();
                    $guardadas = 0;
                    foreach ($e['respuestas'] as $d) {
                        $tipo = (string) ($d['tipo'] ?? '');
                        $valor = trim((string) ($d['valor'] ?? ''));
                        $texto = trim((string) ($d['pregunta'] ?? ''));
                        if (!isset(TIPOS_PREGUNTA[$tipo]) || $valor === '' || $texto === '') {
                            continue;
                        }
                        $idPregunta = (int) ($d['pregunta_id'] ?? 0);
                        $insDetalle->execute([
                            $idRespuesta,
                            ($idEnc !== null && in_array($idPregunta, $preguntasDe[$idEnc] ?? [], true)) ? $idPregunta : null,
                            mb_substr($texto, 0, 255),
                            $tipo,
                            mb_substr($valor, 0, 500),
                        ]);
                        $guardadas++;
                    }
                    if ($guardadas === 0) {   // respuesta vacía o dañada: no se guarda, pero se confirma igual
                        $pdo->prepare('DELETE FROM respuesta_encuesta WHERE id_respuesta = ?')->execute([$idRespuesta]);
                        continue;
                    }
                    $nuevas++;
                    continue;
                }

                // Formato anterior (formulario fijo), por si quedaron respuestas viejas esperando en el portal.
                if (!in_array($e['clasificacion'] ?? '', CLASIFICACIONES_VALIDAS, true)) {
                    continue;   // dato inválido: se confirma igual para que no vuelva a llegar
                }
                $insAnterior->execute([
                    $uuid,
                    $e['clasificacion'],
                    mb_substr((string) ($e['lugar'] ?? ''), 0, 50),
                    mb_substr((string) ($e['profesional'] ?? ''), 0, 50),
                    isset($e['comentario']) && $e['comentario'] !== '' ? mb_substr((string) $e['comentario'], 0, 255) : null,
                    $fecha,
                    $idEnc,
                ]);
                $nuevas += $insAnterior->rowCount();
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[sigsm] sincronizar_encuestas: ' . $e->getMessage());
            return ['ok' => false, 'nuevas' => $nuevas, 'mensaje' => 'No se pudieron guardar las respuestas recibidas.'];
        }

        if ($uuids) {
            $c = portal_peticion('confirmar_encuestas.php', ['metodo' => 'POST', 'json' => ['uuids' => $uuids], 'timeout' => $timeout]);
            if (!$c['ok']) {
                return ['ok' => false, 'nuevas' => $nuevas, 'mensaje' => 'Las respuestas se guardaron, pero no se pudo confirmarlas en el portal: ' . $c['error']];
            }
        }
        if (count($lista) < 200) {
            break;
        }
    }
    return ['ok' => true, 'nuevas' => $nuevas, 'mensaje' => ''];
}

/** Cuándo se actualizó por última vez (usa el reloj de la base, igual que el resto de las fechas). */
function estado_sincronizacion(): array
{
    $st = db()->query(
        "SELECT ultimo_intento, ultimo_exito, ultimo_error FROM sincronizacion WHERE clave = 'encuestas'"
    );
    return $st->fetch() ?: ['ultimo_intento' => null, 'ultimo_exito' => null, 'ultimo_error' => null];
}

/**
 * Sincroniza solo si pasó el tiempo de espera desde el último intento (de cualquier usuario).
 * Automático al entrar: 300 s. Botón manual: 120 s.
 * Devuelve ['estado' => 'ejecutada'|'espera'|'sin_configurar', 'espera' => seg. que faltan, 'resultado' => ?array]
 */
function intentar_sincronizar_encuestas(int $esperaSegundos, int $timeout): array
{
    if (!portal_configurado()) {
        return ['estado' => 'sin_configurar', 'espera' => 0, 'resultado' => null];
    }
    $pdo = db();
    $pdo->exec("INSERT IGNORE INTO sincronizacion (clave) VALUES ('encuestas')");

    // "Reservar" el intento de forma atómica: si dos administradores entran a la vez, solo uno sincroniza.
    $st = $pdo->prepare(
        "UPDATE sincronizacion SET ultimo_intento = NOW()
          WHERE clave = 'encuestas'
            AND (ultimo_intento IS NULL OR ultimo_intento <= NOW() - INTERVAL ? SECOND)"
    );
    $st->execute([$esperaSegundos]);
    if ($st->rowCount() === 0) {
        $q = $pdo->query("SELECT TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) FROM sincronizacion WHERE clave = 'encuestas'");
        $pasaron = (int) $q->fetchColumn();
        return ['estado' => 'espera', 'espera' => max(1, $esperaSegundos - $pasaron), 'resultado' => null];
    }

    $res = sincronizar_encuestas($timeout);
    if ($res['ok']) {
        $pdo->exec("UPDATE sincronizacion SET ultimo_exito = NOW(), ultimo_error = NULL WHERE clave = 'encuestas'");
    } else {
        $pdo->prepare("UPDATE sincronizacion SET ultimo_error = ? WHERE clave = 'encuestas'")
            ->execute([mb_substr($res['mensaje'], 0, 255)]);
    }
    return ['estado' => 'ejecutada', 'espera' => 0, 'resultado' => $res];
}

/** Para usar al entrar a Inicio o Encuestas: cada 5 minutos, con tiempo máximo corto para no demorar la página. */
function sincronizar_encuestas_al_entrar(): array
{
    return intentar_sincronizar_encuestas(300, 6);
}
