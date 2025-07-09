<?php
// Script para crear dataset de entrenamiento
// Ejecutar en consola: php crear_entrenamiento.php

require_once __DIR__ . '/database.php';

function crearDirectorioSiNoExiste($ruta) {
    if (!is_dir($ruta)) {
        mkdir($ruta, 0777, true);
    }
}

function limpiarDirectorio($ruta) {
    if (!is_dir($ruta)) return;
    $archivos = glob($ruta . '/*');
    foreach ($archivos as $archivo) {
        if (is_file($archivo)) {
            unlink($archivo);
        }
    }
}

// Directorio base de imagenes
$baseImagenes = __DIR__ . '/imagenes';
$entrenamientoDir = $baseImagenes . '/entrenamiento';

// Crear carpetas principales
$clases = [
    'sin_grietas',
    'malla',
    'longitudinal',
    'transversal'
];

// Mensaje de advertencia y confirmación
fwrite(STDOUT, "ADVERTENCIA: Se eliminarán todas las imágenes existentes en las carpetas de entrenamiento:\n");
foreach ($clases as $clase) {
    fwrite(STDOUT, " - $entrenamientoDir/$clase\n");
}
fwrite(STDOUT, "¿Desea continuar? (s/N): ");
$confirmacion = trim(fgets(STDIN));
if (strtolower($confirmacion) !== 's') {
    fwrite(STDOUT, "Operación cancelada. No se borró ni copió ninguna imagen.\n");
    exit(0);
}

// Crear y limpiar carpetas
foreach ($clases as $clase) {
    $ruta = "$entrenamientoDir/$clase";
    crearDirectorioSiNoExiste($ruta);
    limpiarDirectorio($ruta);
}

// Conectar a la base de datos
$database = new Database();
$pdo = $database->getConnection();

// Consultar todas las imágenes y sus etiquetas
$sql = "SELECT path_imagen, etiqueta_principal FROM etiquetas";
$stmt = $pdo->query($sql);
$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $path = $row['path_imagen'];
    $etiqueta = trim($row['etiqueta_principal']);
    $src = __DIR__ . $path;
    if (!file_exists($src)) {
        echo "No existe: $src\n";
        continue;
    }
    // Clasificación según etiqueta
    if ($etiqueta === 'SIN GRIETAS') {
        $dest = "$entrenamientoDir/sin_grietas/" . basename($src);
    } elseif ($etiqueta === 'MALLA PEQUEÑA < 0.3' || $etiqueta === 'MALLA MEDIANA >0.3 <0.5') {
        $dest = "$entrenamientoDir/malla/" . basename($src);
    } elseif ($etiqueta === 'LONGITUDINAL') {
        $dest = "$entrenamientoDir/longitudinal/" . basename($src);
    } elseif ($etiqueta === 'TRANSVERSAL') {
        $dest = "$entrenamientoDir/transversal/" . basename($src);
    } else {
        continue;
    }
    if (!file_exists($dest)) {
        if (copy($src, $dest)) {
            echo "Copiado: $src -> $dest\n";
            $count++;
        } else {
            echo "Error al copiar: $src\n";
        }
    }
}
echo "Total de imágenes copiadas: $count\n";

// Comprimir la carpeta entrenamiento en entrenamiento.zip
$zipFile = $baseImagenes . '/entrenamiento.zip';
if (file_exists($zipFile)) {
    unlink($zipFile);
}

// Ejecutar el comando zip
$cmd = "cd '" . escapeshellarg($baseImagenes) . "' && zip -r 'entrenamiento.zip' 'entrenamiento'";

// Mostrar mensaje de progreso
fwrite(STDOUT, "Comprimiendo carpeta entrenamiento en entrenamiento.zip...\n");
exec($cmd, $output, $result);
if ($result === 0) {
    fwrite(STDOUT, "Compresión completada: $zipFile\n");
} else {
    fwrite(STDOUT, "Error al comprimir la carpeta.\n");
} 