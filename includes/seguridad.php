<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Protecciones del login: captcha propio (funciona sin internet) y bloqueo por intentos fallidos.
 */

const MAX_INTENTOS_LOGIN = 5;       // fallos permitidos...
const MINUTOS_BLOQUEO = 10;         // ...dentro de esta ventana de tiempo

/* ---------- Captcha ---------- */

/** Letras y números sin caracteres que se confunden (sin 0/O, 1/I/L). */
const CAPTCHA_CARACTERES = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

/** Con la extensión GD se dibuja un PNG; sin ella se usa un SVG (no necesita ninguna extensión). */
function captcha_usa_gd(): bool
{
    return function_exists('imagecreatetruecolor') && function_exists('imagepng');
}

/** El captcha siempre está activo: ya no depende de que el servidor tenga GD. */
function captcha_disponible(): bool
{
    return true;
}

/** Misma idea que la imagen PNG, pero en SVG: letras rotadas y desparejas, con líneas de ruido. */
function captcha_svg(string $codigo): void
{
    $ancho = 170;
    $alto = 56;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $ancho . '" height="' . $alto . '" viewBox="0 0 ' . $ancho . ' ' . $alto . '">'
        . '<rect width="100%" height="100%" fill="#eef2f6"/>';
    for ($i = 0; $i < 7; $i++) {
        $g = random_int(140, 200);
        $svg .= '<line x1="' . random_int(0, $ancho) . '" y1="' . random_int(0, $alto) . '" x2="' . random_int(0, $ancho) . '" y2="' . random_int(0, $alto)
            . '" stroke="rgb(' . $g . ',' . $g . ',' . random_int(150, 210) . ')" stroke-width="2"/>';
    }
    $n = strlen($codigo);
    for ($i = 0; $i < $n; $i++) {
        $x = 14 + $i * 30 + random_int(-2, 2);
        $y = random_int(36, 44);
        $svg .= '<text x="' . $x . '" y="' . $y . '" font-family="Arial, Helvetica, sans-serif" font-weight="bold" font-size="' . random_int(28, 36) . '"'
            . ' fill="rgb(' . random_int(0, 70) . ',' . random_int(0, 90) . ',' . random_int(40, 110) . ')"'
            . ' transform="rotate(' . random_int(-22, 22) . ' ' . $x . ' ' . $y . ')">' . $codigo[$i] . '</text>';
    }
    for ($i = 0; $i < 4; $i++) {
        $svg .= '<line x1="' . random_int(0, 40) . '" y1="' . random_int(0, $alto) . '" x2="' . random_int(130, $ancho) . '" y2="' . random_int(0, $alto)
            . '" stroke="rgb(' . random_int(60, 140) . ',' . random_int(60, 140) . ',' . random_int(60, 140) . ')" stroke-width="1.5"/>';
    }
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo $svg . '</svg>';
}

/** Crea un código nuevo, lo guarda en la sesión y lo devuelve. */
function captcha_nuevo_codigo(): string
{
    $codigo = '';
    for ($i = 0; $i < 5; $i++) {
        $codigo .= CAPTCHA_CARACTERES[random_int(0, strlen(CAPTCHA_CARACTERES) - 1)];
    }
    $_SESSION['captcha'] = $codigo;
    return $codigo;
}

/** Revisa lo que escribió la persona. El código se usa una sola vez: siempre se descarta. */
function captcha_correcto(string $ingresado): bool
{
    $esperado = (string) ($_SESSION['captcha'] ?? '');
    unset($_SESSION['captcha']);
    return $esperado !== '' && hash_equals($esperado, strtoupper(trim($ingresado)));
}

/** Dibuja el código como imagen PNG (letras desparejas y líneas de ruido, para que un programa no las lea fácil). */
function captcha_imagen(string $codigo): void
{
    $ancho = 170;
    $alto = 56;
    $img = imagecreatetruecolor($ancho, $alto);
    imagefilledrectangle($img, 0, 0, $ancho, $alto, imagecolorallocate($img, 238, 242, 246));

    for ($i = 0; $i < 7; $i++) {   // ruido de fondo
        $c = imagecolorallocate($img, random_int(140, 200), random_int(140, 200), random_int(140, 200));
        imageline($img, random_int(0, $ancho), random_int(0, $alto), random_int(0, $ancho), random_int(0, $alto), $c);
    }
    $n = strlen($codigo);
    for ($i = 0; $i < $n; $i++) {
        // Cada letra se dibuja chica y se agranda en una posición y tamaño un poco distintos.
        $chica = imagecreatetruecolor(9, 15);
        imagefilledrectangle($chica, 0, 0, 9, 15, imagecolorallocate($chica, 238, 242, 246));
        imagestring($chica, 5, 0, 0, $codigo[$i], imagecolorallocate($chica, random_int(0, 70), random_int(0, 90), random_int(40, 110)));
        $tam = random_int(30, 38);
        imagecopyresampled($img, $chica, 12 + $i * 30 + random_int(-2, 2), random_int(4, 14), 0, 0, (int) ($tam * 0.62), $tam, 9, 15);
    }
    for ($i = 0; $i < 4; $i++) {   // líneas por encima del texto
        $c = imagecolorallocate($img, random_int(60, 140), random_int(60, 140), random_int(60, 140));
        imageline($img, random_int(0, 40), random_int(0, $alto), random_int(130, $ancho), random_int(0, $alto), $c);
    }

    header('Content-Type: image/png');
    header('Cache-Control: no-store, max-age=0');
    imagepng($img);
}

/** HTML del campo de captcha para los formularios de acceso. */
function captcha_campo(): string
{
    if (!captcha_disponible()) {
        return '';
    }
    $src = h(url('captcha.php'));
    return '<div class="login-campo">'
        . '<label for="captcha">Código de seguridad</label>'
        . '<div style="display:flex;gap:10px;align-items:center;margin-bottom:8px;">'
        . '<img id="captcha-img" src="' . $src . '?r=' . time() . '" width="170" height="56" alt="Código de seguridad">'
        . '<a href="#" onclick="document.getElementById(\'captcha-img\').src=\'' . $src . '?r=\'+Date.now();return false;">Cambiar imagen</a>'
        . '</div>'
        . '<input id="captcha" name="captcha" required autocomplete="off" maxlength="5" placeholder="Escriba las 5 letras de la imagen">'
        . '</div>';
}

/* ---------- Intentos fallidos ---------- */

function ip_cliente(): string
{
    return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function desde_ventana(): string
{
    return date('Y-m-d H:i:s', time() - MINUTOS_BLOQUEO * 60);
}

/** ¿Hay demasiados intentos fallidos recientes con este usuario o desde esta IP? */
function login_bloqueado(string $usuario): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM intento_login WHERE fecha > ? AND (usuario = ? OR ip = ?)');
    $st->execute([desde_ventana(), mb_substr($usuario, 0, 50), ip_cliente()]);
    return (int) $st->fetchColumn() >= MAX_INTENTOS_LOGIN;
}

function login_registrar_fallo(string $usuario): void
{
    db()->prepare('INSERT INTO intento_login (usuario, ip, fecha) VALUES (?, ?, ?)')
        ->execute([mb_substr($usuario, 0, 50), ip_cliente(), date('Y-m-d H:i:s')]);
    // Limpieza: lo viejo ya no sirve.
    db()->prepare('DELETE FROM intento_login WHERE fecha < ?')->execute([desde_ventana()]);
}

function login_limpiar_fallos(string $usuario): void
{
    db()->prepare('DELETE FROM intento_login WHERE usuario = ?')->execute([mb_substr($usuario, 0, 50)]);
}
