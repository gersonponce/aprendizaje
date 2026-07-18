<?php
/**
 * Convierte las grillas globales de PNG a JPG para reducir su peso (~9x).
 *
 * Uso:
 *   php convertir_grillaglobal_jpg.php DRONE_0            # simulacion, no escribe nada
 *   php convertir_grillaglobal_jpg.php DRONE_0 --ejecutar # convierte
 *   php convertir_grillaglobal_jpg.php DRONE_0 --ejecutar --borrar-png
 *
 * Por defecto NO borra los PNG originales: la API sirve indistintamente .jpg o
 * .png (prefiere .jpg), asi que se puede convertir, verificar y recien despues
 * borrar los PNG con --borrar-png.
 */

const CALIDAD = 85;

$drone = $argv[1] ?? null;
$ejecutar = in_array('--ejecutar', $argv, true);
$borrarPng = in_array('--borrar-png', $argv, true);

if (!$drone || !preg_match('/^DRONE_\d+$/', $drone)) {
    fwrite(STDERR, "Uso: php convertir_grillaglobal_jpg.php DRONE_N [--ejecutar] [--borrar-png]\n");
    exit(1);
}

if (!extension_loaded('gd')) {
    fwrite(STDERR, "Error: la extension GD de PHP no esta disponible.\n");
    exit(1);
}

$dir = __DIR__ . "/imagenes/{$drone}/grillaglobal/";
if (!is_dir($dir)) {
    fwrite(STDERR, "Error: no existe el directorio {$dir}\n");
    exit(1);
}

$pngs = glob($dir . '*.png');
if (!$pngs) {
    fwrite(STDERR, "No se encontraron PNG en {$dir}\n");
    exit(1);
}

if (!$ejecutar) {
    echo "*** SIMULACION: no se escribira ni borrara nada. Usa --ejecutar para aplicar. ***\n";
}
echo "Directorio: {$dir}\n";
echo "Archivos PNG: " . count($pngs) . "\n\n";

$bytesAntes = 0;
$bytesDespues = 0;
$convertidos = 0;
$omitidos = 0;
$fallidos = 0;

foreach ($pngs as $i => $png) {
    $jpg = preg_replace('/\.png$/i', '.jpg', $png);
    $nombre = basename($png);
    $tamPng = filesize($png);

    if (file_exists($jpg)) {
        echo sprintf("[%d/%d] %s — ya existe el .jpg, se omite\n", $i + 1, count($pngs), $nombre);
        $omitidos++;
        continue;
    }

    if (!$ejecutar) {
        echo sprintf("[%d/%d] %s — se convertiria (%.1f MB)\n", $i + 1, count($pngs), $nombre, $tamPng / 1048576);
        $bytesAntes += $tamPng;
        $convertidos++;
        continue;
    }

    $im = @imagecreatefrompng($png);
    if (!$im) {
        fwrite(STDERR, sprintf("[%d/%d] %s — ERROR: no se pudo leer el PNG\n", $i + 1, count($pngs), $nombre));
        $fallidos++;
        continue;
    }

    // Se escribe a un temporal y recien despues se renombra, para no dejar
    // un .jpg a medio escribir si el proceso se interrumpe.
    $tmp = $jpg . '.tmp';
    $ok = imagejpeg($im, $tmp, CALIDAD);
    imagedestroy($im);

    if (!$ok || !file_exists($tmp) || filesize($tmp) === 0) {
        @unlink($tmp);
        fwrite(STDERR, sprintf("[%d/%d] %s — ERROR: fallo la conversion\n", $i + 1, count($pngs), $nombre));
        $fallidos++;
        continue;
    }

    rename($tmp, $jpg);
    $tamJpg = filesize($jpg);
    $bytesAntes += $tamPng;
    $bytesDespues += $tamJpg;
    $convertidos++;

    echo sprintf(
        "[%d/%d] %s — %.1f MB -> %.1f MB (%.1fx)\n",
        $i + 1, count($pngs), $nombre,
        $tamPng / 1048576, $tamJpg / 1048576, $tamPng / max($tamJpg, 1)
    );

    if ($borrarPng) {
        unlink($png);
    }
}

echo "\n--- Resumen ---\n";
echo "Convertidos: {$convertidos}\n";
echo "Omitidos (ya tenian .jpg): {$omitidos}\n";
echo "Fallidos: {$fallidos}\n";

if ($ejecutar && $convertidos > 0) {
    printf("Tamano: %.2f GB -> %.2f GB\n", $bytesAntes / 1073741824, $bytesDespues / 1073741824);
    if (!$borrarPng) {
        echo "\nLos PNG originales se conservaron. Verifica que las grillas se vean\n";
        echo "bien en la web y vuelve a correr con --borrar-png para liberar espacio.\n";
    }
} elseif (!$ejecutar) {
    printf("Tamano actual de los PNG a convertir: %.2f GB\n", $bytesAntes / 1073741824);
}
