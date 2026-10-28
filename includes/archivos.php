<?php
declare(strict_types=1);

/**
 * Manejo de los PDF subidos. Los archivos se guardan en uploads/documentos/ con un nombre
 * aleatorio (nunca el que trae el usuario) y solo se entregan por documentos/ver.php,
 * que exige sesión iniciada.
 */

function directorio_pdf(): string
{
    $dir = getenv('UPLOADS_DIR') ?: __DIR__ . '/../uploads/documentos';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de documentos.');
    }
    return rtrim((string) realpath($dir), '/\\');
}

function limite_mb(): int
{
    return max(1, (int) (getenv('MAX_UPLOAD_MB') ?: 10));
}

/**
 * Valida y guarda un PDF recibido por $_FILES.
 * Devuelve ['ruta' => ..., 'nombre_original' => ..., 'tamano' => ...].
 * Lanza RuntimeException con un mensaje apto para mostrar al usuario.
 */
function guardar_pdf_subido(array $f): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $motivos = [
            UPLOAD_ERR_INI_SIZE => 'El archivo supera el tamaño que permite el servidor.',
            UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño permitido.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió incompleto. Intente de nuevo.',
            UPLOAD_ERR_NO_FILE => 'Debe seleccionar un archivo PDF.',
        ];
        throw new RuntimeException($motivos[$f['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'No se pudo recibir el archivo.');
    }
    if ($f['size'] > limite_mb() * 1048576) {
        throw new RuntimeException('El archivo supera el máximo de ' . limite_mb() . ' MB.');
    }
    // Un PDF real empieza con "%PDF-" (no alcanza con mirar la extensión).
    if (file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') {
        throw new RuntimeException('Solo se permiten archivos PDF.');
    }

    $nombre = bin2hex(random_bytes(12)) . '.pdf';
    if (!move_uploaded_file($f['tmp_name'], directorio_pdf() . DIRECTORY_SEPARATOR . $nombre)) {
        throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
    }

    // Nombre con el que se descargará: sin rutas ni caracteres raros.
    $original = preg_replace('/[^\p{L}\p{N}._ -]/u', '_', basename((string) $f['name'])) ?: 'documento.pdf';
    return [
        'ruta' => $nombre,
        'nombre_original' => mb_substr($original, 0, 200),
        'tamano' => (int) $f['size'],
    ];
}

/** Ruta real del archivo, solo si está dentro de la carpeta de documentos. */
function ruta_pdf_segura(string $relativa): ?string
{
    $base = directorio_pdf();
    $completa = realpath($base . DIRECTORY_SEPARATOR . $relativa);
    if ($completa === false || strpos($completa, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($completa)) {
        return null;
    }
    return $completa;
}

function borrar_pdf(string $relativa): void
{
    $ruta = ruta_pdf_segura($relativa);
    if ($ruta !== null) {
        @unlink($ruta);
    }
}
