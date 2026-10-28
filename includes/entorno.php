<?php
declare(strict_types=1);

/**
 * Lee el archivo .env de la carpeta principal (líneas CLAVE=valor) y lo deja disponible
 * como variables de entorno. Una variable de entorno real siempre tiene prioridad.
 */
function cargar_env(string $ruta): void
{
    if (!is_readable($ruta)) {
        return;
    }
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim(trim($valor), "\"'");
        if ($clave !== '' && getenv($clave) === false) {
            putenv("$clave=$valor");
        }
    }
}

cargar_env(__DIR__ . '/../.env');
