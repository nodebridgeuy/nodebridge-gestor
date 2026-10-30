<?php
declare(strict_types=1);

/** Ruta base del proyecto en el servidor (ej. "/nodebridge-gestor" o "" si está en la raíz). */
function base_url(): string
{
    $manual = getenv('BASE_URL');
    if ($manual !== false && $manual !== '') {
        return rtrim($manual, '/');
    }
    // Se compara la página que se está ejecutando con la URL por la que se pidió:
    //   archivo: .../htdocs/nodebridge-gestor/documentos/index.php   URL: /nodebridge-gestor/documentos/index.php
    // Lo que sobra de la URL, después de quitar "documentos/index.php", es la ruta base.
    $raiz = str_replace('\\', '/', (string) realpath(__DIR__ . '/..')) . '/';
    $script = str_replace('\\', '/', (string) realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
    $urlScript = (string) parse_url((string) ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH);
    if ($script !== '' && strpos($script, $raiz) === 0) {
        $relativa = '/' . substr($script, strlen($raiz));
        if (substr($urlScript, -strlen($relativa)) === $relativa) {
            return rtrim(substr($urlScript, 0, -strlen($relativa)), '/');
        }
    }
    return '';
}

/** Arma una URL interna: url('documentos/index.php') */
function url(string $ruta = ''): string
{
    return base_url() . '/' . ltrim($ruta, '/');
}

/** Escapa texto antes de mostrarlo en HTML (evita XSS). */
function h(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Nombre para mostrar en pantalla, con la primera letra en mayúscula (el usuario guardado no cambia). */
function nombre_visible(?string $nombre): string
{
    $n = trim((string) $nombre);
    if ($n === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_strtoupper(mb_substr($n, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($n, 1, null, 'UTF-8');
    }
    return ucfirst($n);
}

function redirigir(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}

/* ---------- Mensajes que se muestran una sola vez (después de guardar, etc.) ---------- */

function avisar(string $mensaje, string $tipo = 'success'): void
{
    $_SESSION['avisos'][] = [$tipo, $mensaje];
}

function avisos_pendientes(): array
{
    $avisos = $_SESSION['avisos'] ?? [];
    unset($_SESSION['avisos']);
    return $avisos;
}

/* ---------- Protección CSRF: todo formulario POST lleva un token secreto ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

/** Corta la petición si no es POST o si el token no coincide. */
function exigir_post_con_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Método no permitido');
    }
    $recibido = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals(csrf_token(), $recibido)) {
        http_response_code(400);
        exit('Solicitud inválida (token de seguridad incorrecto). Vuelva atrás y recargue la página.');
    }
}

/* ---------- Cédula de identidad (Uruguay) ---------- */

/**
 * Deja la cédula como 8 dígitos (con ceros adelante) si es válida, o null si no lo es.
 * Acepta "1.234.567-2", "12345672", "1234567-2", etc. El último dígito es el verificador.
 */
function normalizar_cedula(string $texto): ?string
{
    $d = preg_replace('/\D/', '', $texto) ?? '';
    if (strlen($d) < 7 || strlen($d) > 8) {
        return null;
    }
    $d = str_pad($d, 8, '0', STR_PAD_LEFT);
    $pesos = [2, 9, 8, 7, 6, 3, 4];
    $suma = 0;
    for ($i = 0; $i < 7; $i++) {
        $suma += (int) $d[$i] * $pesos[$i];
    }
    return (int) $d[7] === (10 - $suma % 10) % 10 ? $d : null;
}

/** "12345672" → "1.234.567-2" (las de 6 dígitos, que se guardan con un cero adelante, salen como "123.456-1"). */
function formatear_cedula(?string $cedula): string
{
    if ($cedula === null || !preg_match('/^\d{8}$/', $cedula)) {
        return '-';
    }
    $cuerpo = ltrim(substr($cedula, 0, 7), '0');
    $cuerpo = strrev(implode('.', str_split(strrev($cuerpo), 3)));
    return $cuerpo . '-' . $cedula[7];
}

/* ---------- Formatos ---------- */

function formatear_fecha(?string $fecha): string
{
    $t = $fecha ? strtotime($fecha) : false;
    return $t ? date('d/m/Y H:i', $t) : '-';
}

function formatear_tamano(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    return max(1, (int) round($bytes / 1024)) . ' KB';
}

/* ---------- Paginación ---------- */

/** Devuelve [pagina_actual, total_paginas, offset] */
function paginar(int $total, int $porPagina): array
{
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min(max(1, (int) ($_GET['pagina'] ?? 1)), $paginas);
    return [$pagina, $paginas, ($pagina - 1) * $porPagina];
}

/** Botones de paginación que conservan los filtros de la búsqueda. */
function html_paginacion(int $pagina, int $paginas): string
{
    if ($paginas <= 1) {
        return '';
    }
    $enlace = function (int $p, string $texto, bool $activo = false, bool $deshabilitado = false): string {
        $query = http_build_query(array_merge($_GET, ['pagina' => $p]));
        $clase = 'page-item' . ($activo ? ' active' : '') . ($deshabilitado ? ' disabled' : '');
        return '<li class="' . $clase . '"><a class="page-link" href="?' . h($query) . '">' . $texto . '</a></li>';
    };
    $html = '<nav aria-label="Páginas"><ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">';
    $html .= $enlace(max(1, $pagina - 1), '&laquo;', false, $pagina === 1);
    for ($p = max(1, $pagina - 2); $p <= min($paginas, $pagina + 2); $p++) {
        $html .= $enlace($p, (string) $p, $p === $pagina);
    }
    $html .= $enlace(min($paginas, $pagina + 1), '&raquo;', false, $pagina === $paginas);
    return $html . '</ul></nav>';
}
