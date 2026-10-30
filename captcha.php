<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/seguridad.php';

// Imagen del captcha (cada vez que se pide, se crea un código nuevo).
// PNG si el servidor tiene la extensión GD; si no, SVG.
$codigo = captcha_nuevo_codigo();
if (captcha_usa_gd()) {
    captcha_imagen($codigo);
} else {
    captcha_svg($codigo);
}
