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

// Crear carpeta de entrenamiento
$clases = ['entrenamiento'];

// Mensaje de advertencia y confirmación
fwrite(STDOUT, "ADVERTENCIA: Se eliminarán todas las imágenes existentes en la carpeta de entrenamiento:\n");
fwrite(STDOUT, " - $entrenamientoDir\n");
fwrite(STDOUT, "¿Desea continuar? (s/N): ");
$confirmacion = trim(fgets(STDIN));
if (strtolower($confirmacion) !== 's') {
    fwrite(STDOUT, "Operación cancelada. No se borró ni copió ninguna imagen.\n");
    exit(0);
}

// Crear y limpiar carpeta de entrenamiento
crearDirectorioSiNoExiste($entrenamientoDir);
limpiarDirectorio($entrenamientoDir);

// Conectar a la base de datos
$database = new Database();
$pdo = $database->getConnection();

// Crear archivo CSV
$csvFile = $baseImagenes . '/dataset_entrenamiento.csv';
$csvHandle = fopen($csvFile, 'w');

// Escribir encabezados del CSV
fputcsv($csvHandle, ['direccion', 'etiqueta_principal', 'etiqueta_secundaria']);

// Consultar todas las imágenes y sus etiquetas
$sql = "SELECT path_imagen, etiqueta_principal, etiquetas_secundarias FROM etiquetas WHERE etiqueta_principal IS NOT NULL AND etiqueta_principal != ''";
$stmt = $pdo->query($sql);
$count = 0;
$csvCount = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $path = $row['path_imagen'];
    $etiquetaPrincipal = trim($row['etiqueta_principal']);
    $etiquetasSecundarias = trim($row['etiquetas_secundarias']);
    $src = __DIR__ . $path;
    
    if (!file_exists($src)) {
        echo "No existe: $src\n";
        continue;
    }
    
    // Copiar todas las imágenes con etiqueta principal válida a la carpeta de entrenamiento
    $dest = "$entrenamientoDir/" . basename($src);
    $direccion = basename($src); // Solo el nombre del archivo sin ruta
    
    if (!file_exists($dest)) {
        if (copy($src, $dest)) {
            echo "Copiado: $src -> $dest\n";
            $count++;
            
            // Escribir en CSV después de copiar exitosamente
            fputcsv($csvHandle, [$direccion, $etiquetaPrincipal, $etiquetasSecundarias]);
            $csvCount++;
        } else {
            echo "Error al copiar: $src\n";
        }
    } else {
        // Si la imagen ya existe, también escribir en CSV
        fputcsv($csvHandle, [$direccion, $etiquetaPrincipal, $etiquetasSecundarias]);
        $csvCount++;
    }
}

// Cerrar archivo CSV
fclose($csvHandle);

echo "Total de imágenes copiadas: $count\n";
echo "Total de registros en CSV: $csvCount\n";
echo "Archivo CSV creado: $csvFile\n";

// Comprimir la carpeta entrenamiento y el CSV en entrenamiento.zip
$zipFile = $baseImagenes . '/entrenamiento.zip';
if (file_exists($zipFile)) {
    unlink($zipFile);
}

// Ejecutar el comando zip incluyendo la carpeta entrenamiento y el archivo CSV
$cmd = "cd '" . escapeshellarg($baseImagenes) . "' && zip -r 'entrenamiento.zip' 'entrenamiento' 'dataset_entrenamiento.csv'";

// Mostrar mensaje de progreso
fwrite(STDOUT, "Comprimiendo carpeta entrenamiento y archivo CSV en entrenamiento.zip...\n");
exec($cmd, $output, $result);
if ($result === 0) {
    fwrite(STDOUT, "Compresión completada: $zipFile\n");
    fwrite(STDOUT, "El archivo ZIP contiene:\n");
    fwrite(STDOUT, " - Carpeta 'entrenamiento/' con todas las imágenes\n");
    fwrite(STDOUT, " - Archivo 'dataset_entrenamiento.csv' con las etiquetas\n");
} else {
    fwrite(STDOUT, "Error al comprimir la carpeta.\n");
}

// Mostrar estadísticas del CSV
fwrite(STDOUT, "\n=== ESTADÍSTICAS DEL DATASET ===\n");
fwrite(STDOUT, "Archivo CSV: $csvFile\n");
fwrite(STDOUT, "Total de registros: $csvCount\n");

// Mostrar distribución de todas las etiquetas principales
$sql = "SELECT etiqueta_principal, COUNT(*) as total FROM etiquetas WHERE etiqueta_principal IS NOT NULL AND etiqueta_principal != '' GROUP BY etiqueta_principal ORDER BY total DESC";
$stmt = $pdo->query($sql);
fwrite(STDOUT, "\nDistribución de todas las etiquetas principales:\n");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fwrite(STDOUT, " - {$row['etiqueta_principal']}: {$row['total']} imágenes\n");
} 