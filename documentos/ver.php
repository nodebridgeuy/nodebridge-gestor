<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/archivos.php';

// Entrega el PDF solo a funcionarios con sesión iniciada. ?id=N  (&descargar=1 para bajarlo)
requerir_login();

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT ruta_archivo, nombre_original FROM documento WHERE id_documento = ?');
$st->execute([$id]);
$doc = $st->fetch();
$ruta = $doc ? ruta_pdf_segura($doc['ruta_archivo']) : null;

if ($ruta === null) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

$modo = isset($_GET['descargar']) ? 'attachment' : 'inline';
$nombre = str_replace(['"', "\r", "\n"], '', $doc['nombre_original']);

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: ' . $modo . '; filename="' . $nombre . '"');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
