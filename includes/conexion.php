<?php
declare(strict_types=1);

require_once __DIR__ . '/entorno.php';

/**
 * Conexión a la base de datos (PDO).
 * Valores por defecto = XAMPP (usuario root sin contraseña, base "sigsm").
 * Para otro entorno (Docker, servidor del hospital) se pueden cambiar con
 * variables de entorno: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS.
 */
function env_o(string $clave, string $porDefecto): string
{
    $valor = getenv($clave);
    return ($valor === false || $valor === '') ? $porDefecto : $valor;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // DB_DSN solo se usa para pruebas automáticas; en producción se arma con los datos de abajo.
    $dsn = env_o('DB_DSN', sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env_o('DB_HOST', '127.0.0.1'),
        env_o('DB_PORT', '3306'),
        env_o('DB_NAME', 'sigsm')
    ));

    try {
        $pdo = new PDO($dsn, env_o('DB_USER', 'root'), env_o('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        // El detalle va al log del servidor; al usuario no se le muestran datos de la conexión.
        error_log('[sigsm] No se pudo conectar a la base: ' . $e->getMessage());
        http_response_code(500);
        exit('No se pudo conectar a la base de datos. Verifique que MySQL esté iniciado y que la base "sigsm" esté importada.');
    }
    return $pdo;
}
