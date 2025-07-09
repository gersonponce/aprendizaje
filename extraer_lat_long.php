<?php
// Verificar autenticación
session_start();
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'msg' => 'No autenticado']);
    exit;
}

// Función para convertir coordenadas GPS de formato decimal a grados
function convertirCoordenadas($coordenada, $hemisferio) {
    if ($coordenada == 0) return 0;
    
    $grados = floor($coordenada);
    $minutos = floor(($coordenada - $grados) * 60);
    $segundos = (($coordenada - $grados - $minutos / 60) * 3600);
    
    $resultado = $grados + ($minutos / 60) + ($segundos / 3600);
    
    if ($hemisferio == 'S' || $hemisferio == 'W') {
        $resultado = -$resultado;
    }
    
    return $resultado;
}

// Función para calcular distancia entre dos puntos GPS usando fórmula de Haversine
function calcularDistancia($lat1, $lon1, $lat2, $lon2) {
    // Radio de la Tierra en metros
    $radioTierra = 6371000;
    
    // Convertir grados a radianes
    $lat1Rad = deg2rad($lat1);
    $lon1Rad = deg2rad($lon1);
    $lat2Rad = deg2rad($lat2);
    $lon2Rad = deg2rad($lon2);
    
    // Diferencias en coordenadas
    $deltaLat = $lat2Rad - $lat1Rad;
    $deltaLon = $lon2Rad - $lon1Rad;
    
    // Fórmula de Haversine
    $a = sin($deltaLat/2) * sin($deltaLat/2) + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon/2) * sin($deltaLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    // Distancia en metros
    $distancia = $radioTierra * $c;
    
    return $distancia;
}

// Función para convertir fracciones string a decimal
function convertirFraccion($fraccion) {
    if (is_string($fraccion) && strpos($fraccion, '/') !== false) {
        $partes = explode('/', $fraccion);
        if (count($partes) == 2) {
            $numerador = floatval($partes[0]);
            $denominador = floatval($partes[1]);
            return $denominador != 0 ? $numerador / $denominador : 0;
        }
    }
    return floatval($fraccion);
}

// Función para limpiar datos para JSON seguro
function limpiarDatosParaJSON($data) {
    if (is_array($data)) {
        $resultado = [];
        foreach ($data as $key => $value) {
            $resultado[$key] = limpiarDatosParaJSON($value);
        }
        return $resultado;
    } elseif (is_string($data)) {
        // Remover caracteres de control y caracteres problemáticos
        $data = preg_replace('/[\x00-\x1F\x7F]/', '', $data);
        // Escapar caracteres especiales
        $data = str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $data);
        return $data;
    } else {
        return $data;
    }
}

// Función para extraer coordenadas GPS y altura de una imagen
function extraerCoordenadasGPS($rutaImagen) {
    if (!function_exists('exif_read_data')) {
        return ['lat' => 'EXIF no disponible', 'long' => 'EXIF no disponible', 'altura' => 'EXIF no disponible'];
    }
    
    // Leer todos los datos EXIF
    $exif = @exif_read_data($rutaImagen, 'ANY_TAG', true);
    
    if (!$exif) {
        return ['lat' => 'No EXIF', 'long' => 'No EXIF', 'altura' => 'No EXIF'];
    }
    
    // Debug: mostrar todos los datos EXIF disponibles
    $debug = [];
    if (isset($exif['GPS'])) {
        $debug['GPS'] = $exif['GPS'];
    }
    if (isset($exif['EXIF'])) {
        $debug['EXIF'] = $exif['EXIF'];
    }
    if (isset($exif['IFD0'])) {
        $debug['IFD0'] = $exif['IFD0'];
    }
    
    // Variables para coordenadas y altura
    $lat = 'No disponible';
    $long = 'No disponible';
    $altura = 'No disponible';
    
    // Extraer coordenadas GPS
    if (isset($exif['GPS'])) {
        $gps = $exif['GPS'];
        
        // Extraer latitud
        if (isset($gps['GPSLatitude']) && isset($gps['GPSLatitudeRef'])) {
            $latArray = $gps['GPSLatitude'];
            $latRef = $gps['GPSLatitudeRef'];
            
            if (is_array($latArray) && count($latArray) >= 3) {
                // Convertir fracciones string a decimales
                $latDegrees = convertirFraccion($latArray[0]);
                $latMinutes = convertirFraccion($latArray[1]);
                $latSeconds = convertirFraccion($latArray[2]);
                
                // Convertir a formato decimal
                $latDecimal = $latDegrees + ($latMinutes / 60) + ($latSeconds / 3600);
                
                if ($latRef == 'S') {
                    $latDecimal = -$latDecimal;
                }
                
                $lat = $latDecimal;
            }
        }
        
        // Extraer longitud
        if (isset($gps['GPSLongitude']) && isset($gps['GPSLongitudeRef'])) {
            $longArray = $gps['GPSLongitude'];
            $longRef = $gps['GPSLongitudeRef'];
            
            if (is_array($longArray) && count($longArray) >= 3) {
                // Convertir fracciones string a decimales
                $longDegrees = convertirFraccion($longArray[0]);
                $longMinutes = convertirFraccion($longArray[1]);
                $longSeconds = convertirFraccion($longArray[2]);
                
                // Convertir a formato decimal
                $longDecimal = $longDegrees + ($longMinutes / 60) + ($longSeconds / 3600);
                
                if ($longRef == 'W') {
                    $longDecimal = -$longDecimal;
                }
                
                $long = $longDecimal;
            }
        }
        
        // Extraer altura
        if (isset($gps['GPSAltitude'])) {
            $altValue = $gps['GPSAltitude'];
            if (is_string($altValue) && strpos($altValue, '/') !== false) {
                // Altura en formato fracción string
                $altura = convertirFraccion($altValue);
                
                // Si hay referencia de altura, ajustar
                if (isset($gps['GPSAltitudeRef']) && $gps['GPSAltitudeRef'] == 1) {
                    $altura = -$altura; // Altura bajo el nivel del mar
                }
            } else {
                $altura = $altValue; // Valor directo
            }
        }
    }
    
    // Buscar coordenadas en campos personalizados de DJI
    if ($lat === 'No disponible' || $long === 'No disponible') {
        if (isset($exif['EXIF'])) {
            $exifData = $exif['EXIF'];
            
            // Buscar campos específicos de DJI
            foreach ($exifData as $key => $value) {
                if (stripos($key, 'gps') !== false || stripos($key, 'lat') !== false || stripos($key, 'long') !== false || stripos($key, 'alt') !== false) {
                    $debug['DJI_' . $key] = $value;
                }
            }
        }
        
        // Buscar en campos de IFD0 también
        if (isset($exif['IFD0'])) {
            $ifd0Data = $exif['IFD0'];
            foreach ($ifd0Data as $key => $value) {
                if (stripos($key, 'gps') !== false || stripos($key, 'lat') !== false || stripos($key, 'long') !== false) {
                    $debug['IFD0_' . $key] = $value;
                }
            }
        }
    }
    
    return [
        'lat' => $lat, 
        'long' => $long,
        'altura' => $altura,
        'debug' => $debug
    ];
}

// Directorio de imágenes (se puede cambiar dinámicamente)
$drone_seleccionado = isset($_GET['drone']) ? $_GET['drone'] : 'DRONE_2';
$directorio = __DIR__ . '/imagenes/' . $drone_seleccionado . '/images/';

// Verificar si el directorio existe
if (!is_dir($directorio)) {
    echo "<div class='max-w-7xl mx-auto px-4 py-8'>";
    echo "<div class='bg-red-50 border border-red-200 rounded-lg p-6'>";
    echo "<h1 class='text-xl font-bold text-red-800 mb-2'>Error: El directorio no existe</h1>";
    echo "<p class='text-red-700'>Directorio: $directorio</p>";
    echo "<p class='text-red-600 mt-2'>Por favor, selecciona un drone diferente o verifica que el directorio exista.</p>";
    echo "<a href='?drone=DRONE_2' class='inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700'>Volver a DRONE_2</a>";
    echo "</div>";
    echo "</div>";
    exit;
}

// Obtener todas las imágenes del directorio
$extensiones = ['jpg', 'jpeg', 'png', 'tiff', 'tif'];
$imagenes = [];

$archivos = scandir($directorio);
foreach ($archivos as $archivo) {
    if ($archivo != '.' && $archivo != '..') {
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        if (in_array($extension, $extensiones)) {
            $imagenes[] = $archivo;
        }
    }
}

// Ordenar las imágenes alfabéticamente
sort($imagenes);

// Procesar todas las coordenadas y calcular distancias
$coordenadasImagenes = [];
$distancias = [];
$distanciasAcumuladas = [];

foreach ($imagenes as $index => $imagen) {
    $rutaCompleta = $directorio . $imagen;
    $coordenadas = extraerCoordenadasGPS($rutaCompleta);
    $coordenadasImagenes[$imagen] = $coordenadas;
    
    // Calcular distancia con la imagen anterior (si ambas tienen coordenadas válidas)
    if ($index > 0) {
        $imagenAnterior = $imagenes[$index - 1];
        $coordAnterior = $coordenadasImagenes[$imagenAnterior];
        
        if (is_numeric($coordAnterior['lat']) && is_numeric($coordAnterior['long']) && 
            is_numeric($coordenadas['lat']) && is_numeric($coordenadas['long'])) {
            $distancia = calcularDistancia(
                $coordAnterior['lat'], $coordAnterior['long'],
                $coordenadas['lat'], $coordenadas['long']
            );
            $distancias[$imagen] = round($distancia, 2);
        } else {
            $distancias[$imagen] = 'N/A';
        }
    } else {
        $distancias[$imagen] = '-';
    }
    
    // Calcular distancia acumulada
    if ($index == 0) {
        $distanciasAcumuladas[$imagen] = 0;
    } else {
        $distanciaAnterior = $distanciasAcumuladas[$imagenes[$index - 1]];
        if (is_numeric($distancias[$imagen])) {
            $distanciasAcumuladas[$imagen] = round($distanciaAnterior + $distancias[$imagen], 2);
        } else {
            $distanciasAcumuladas[$imagen] = $distanciaAnterior; // Mantener la distancia anterior si no hay nueva
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extraer Coordenadas GPS - <?php echo $drone_seleccionado; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .table-container {
            max-height: 70vh;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-bold text-blue-900">Extraer Coordenadas GPS - <?php echo $drone_seleccionado; ?></h1>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">
                        Usuario: <?php echo $_SESSION['user']['username'] ?? 'Desconocido'; ?>
                    </span>
                    <a href="index.html" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium transition duration-200">
                        Volver al etiquetado
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Controles de configuración -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Configuración</h2>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Seleccionar Drone:</label>
                    <select name="drone" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php for ($i = 0; $i <= 10; $i++): ?>
                            <option value="DRONE_<?php echo $i; ?>" <?php echo ($drone_seleccionado === "DRONE_$i") ? 'selected' : ''; ?>>
                                DRONE_<?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Largo de foto (m):</label>
                    <input type="number" id="fotoLargoInput" value="38.51" step="0.01" min="1" max="100" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ancho de foto (m):</label>
                    <input type="number" id="fotoAnchoInput" value="21.35" step="0.01" min="1" max="100" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition duration-200">
                        Cargar Datos
                    </button>
                </div>
            </form>
        </div>

        <!-- Información del directorio -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Información del directorio</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="font-medium">Directorio:</span>
                    <p class="text-gray-600"><?php echo $directorio; ?></p>
                </div>
                <div>
                    <span class="font-medium">Total de imágenes:</span>
                    <p class="text-gray-600"><?php echo count($imagenes); ?></p>
                </div>
                <div>
                    <span class="font-medium">Extensiones soportadas:</span>
                    <p class="text-gray-600"><?php echo implode(', ', $extensiones); ?></p>
                </div>
            </div>
        </div>

        <!-- Tabla de coordenadas -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h2 class="text-lg font-semibold">Coordenadas GPS de las imágenes</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Mostrando coordenadas extraídas de los metadatos EXIF de las imágenes
                </p>
            </div>
            
            <div class="table-container">
                <table class="min-w-full">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                #
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Nombre de la imagen
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Latitud
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Longitud
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Altura (m)
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Estado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Distancia (m)
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Dist. Acumulada (m)
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Graficado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Debug
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($imagenes)): ?>
                            <tr>
                                <td colspan="10" class="px-6 py-4 text-center text-gray-500">
                                    No se encontraron imágenes en el directorio
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($imagenes as $index => $imagen): ?>
                                <?php 
                                $coordenadas = $coordenadasImagenes[$imagen];
                                $tieneGPS = (is_numeric($coordenadas['lat']) && is_numeric($coordenadas['long']));
                                $distancia = $distancias[$imagen];
                                $distanciaAcumulada = $distanciasAcumuladas[$imagen];
                                ?>
                                <tr class="<?php echo $index % 2 == 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $index + 1; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($imagen); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php 
                                        if (is_numeric($coordenadas['lat'])) {
                                            echo number_format($coordenadas['lat'], 6);
                                        } else {
                                            echo $coordenadas['lat'];
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php 
                                        if (is_numeric($coordenadas['long'])) {
                                            echo number_format($coordenadas['long'], 6);
                                        } else {
                                            echo $coordenadas['long'];
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php 
                                        if (is_numeric($coordenadas['altura'])) {
                                            echo number_format($coordenadas['altura'], 2);
                                        } else {
                                            echo $coordenadas['altura'];
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($tieneGPS): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                GPS OK
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Sin GPS
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($distancia === '-'): ?>
                                            <span class="text-gray-400">-</span>
                                        <?php elseif ($distancia === 'N/A'): ?>
                                            <span class="text-red-400">N/A</span>
                                        <?php else: ?>
                                            <span class="font-medium text-blue-600"><?php echo $distancia; ?> m</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($distanciaAcumulada === 0): ?>
                                            <span class="text-gray-400">0 m</span>
                                        <?php elseif (is_numeric($distanciaAcumulada)): ?>
                                            <span class="font-medium text-green-600"><?php echo $distanciaAcumulada; ?> m</span>
                                        <?php else: ?>
                                            <span class="text-red-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span id="graficado-<?php echo $index; ?>" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                            No
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if (isset($coordenadas['debug']) && !empty($coordenadas['debug'])): ?>
                                            <?php 
                                            $debugLimpio = limpiarDatosParaJSON($coordenadas['debug']);
                                            $debugJSON = json_encode($debugLimpio, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
                                            ?>
                                            <button onclick="mostrarDebug('<?php echo htmlspecialchars($debugJSON); ?>', '<?php echo htmlspecialchars($imagen); ?>')" 
                                                    class="text-xs text-blue-600 hover:text-blue-800 underline">
                                                Ver datos EXIF
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-400">Sin datos</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Resumen -->
        <?php if (!empty($imagenes)): ?>
            <?php 
            $conGPS = 0;
            $sinGPS = 0;
            
            foreach ($imagenes as $imagen) {
                $coordenadas = $coordenadasImagenes[$imagen];
                $tieneGPS = (is_numeric($coordenadas['lat']) && is_numeric($coordenadas['long']));
                
                if ($tieneGPS) {
                    $conGPS++;
                } else {
                    $sinGPS++;
                }
            }
            ?>
            <div class="bg-white rounded-lg shadow p-6 mt-6">
                <h3 class="text-lg font-semibold mb-4">Resumen</h3>
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600"><?php echo count($imagenes); ?></div>
                        <div class="text-sm text-gray-600">Total de imágenes</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600"><?php echo $conGPS; ?></div>
                        <div class="text-sm text-gray-600">Con coordenadas GPS</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-600"><?php echo $sinGPS; ?></div>
                        <div class="text-sm text-gray-600">Sin coordenadas GPS</div>
                    </div>
                    <div class="text-center">
                        <?php 
                        $distanciasValidas = array_filter($distancias, function($d) { return is_numeric($d); });
                        $distanciaPromedio = !empty($distanciasValidas) ? round(array_sum($distanciasValidas) / count($distanciasValidas), 2) : 0;
                        ?>
                        <div class="text-2xl font-bold text-purple-600"><?php echo $distanciaPromedio; ?> m</div>
                        <div class="text-sm text-gray-600">Distancia promedio</div>
                    </div>
                    <div class="text-center">
                        <?php 
                        $alturasValidas = array_filter($coordenadasImagenes, function($coord) { 
                            return is_numeric($coord['altura']); 
                        });
                        $alturaPromedio = !empty($alturasValidas) ? 
                            round(array_sum(array_column($alturasValidas, 'altura')) / count($alturasValidas), 2) : 0;
                        ?>
                        <div class="text-2xl font-bold text-orange-600"><?php echo $alturaPromedio; ?> m</div>
                        <div class="text-sm text-gray-600">Altura promedio</div>
                    </div>
                    <div class="text-center">
                        <?php 
                        $distanciaTotal = end($distanciasAcumuladas);
                        $distanciaTotal = is_numeric($distanciaTotal) ? round($distanciaTotal, 2) : 0;
                        ?>
                        <div class="text-2xl font-bold text-indigo-600"><?php echo $distanciaTotal; ?> m</div>
                        <div class="text-sm text-gray-600">Distancia total</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Gráfico de puntos GPS -->
    <div class="bg-white rounded-lg shadow p-6 mt-6">
        <h3 class="text-lg font-semibold mb-4">Visualización de la ruta del drone</h3>
        <p class="text-sm text-gray-600 mb-4">
            Gráfico de los puntos GPS de las imágenes. Los puntos se conectan en orden secuencial. Los rectángulos muestran el área cubierta por cada foto con dimensiones configurables. El lado largo está perpendicular a la ruta y el lado ancho está paralelo. Las dimensiones se pueden ajustar en la sección de configuración.
        </p>
        <div class="flex items-center space-x-4 mb-4">
            <label class="flex items-center space-x-2 text-sm">
                <input type="checkbox" id="mostrarRectangulos" checked class="rounded">
                <span>Mostrar áreas de fotos</span>
            </label>
            <label class="flex items-center space-x-2 text-sm">
                <span>Mostrar cada</span>
                <input type="number" id="intervaloRectangulos" value="5" min="1" max="50" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm">
                <span>puntos</span>
            </label>
            <button onclick="dibujarGraficoGPS()" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                Actualizar gráfico
            </button>
        </div>
        <div class="flex items-center space-x-6 mb-4 text-sm">
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-red-500 rounded-full border-2 border-white"></div>
                <span>Punto de inicio</span>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                <span>Puntos intermedios</span>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-blue-500 rounded-full"></div>
                <span>Ruta del drone</span>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-red-500 bg-opacity-30 border border-red-500"></div>
                <span>Área foto inicial</span>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-4 h-4 bg-green-500 bg-opacity-30 border border-green-500"></div>
                <span>Área fotos</span>
            </div>
        </div>
        <div class="relative">
            <div class="w-full overflow-x-auto">
                <canvas id="gpsChart" width="800" height="400" class="border border-gray-300 rounded-lg max-w-full"></canvas>
            </div>
            <div id="chartInfo" class="mt-2 text-sm text-gray-600"></div>
        </div>
    </div>

    <!-- Modal para mostrar debug -->
    <div id="debugModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="debugTitle">Datos EXIF</h3>
                    <button onclick="cerrarDebug()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="bg-gray-100 p-4 rounded-lg max-h-96 overflow-y-auto">
                    <pre id="debugContent" class="text-xs text-gray-800 whitespace-pre-wrap"></pre>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Datos de coordenadas para el gráfico
        const coordenadasGPS = <?php 
            $datosGrafico = [];
            foreach ($imagenes as $index => $imagen) {
                $coord = $coordenadasImagenes[$imagen];
                if (is_numeric($coord['lat']) && is_numeric($coord['long'])) {
                    $datosGrafico[] = [
                        'imagen' => $imagen,
                        'lat' => $coord['lat'],
                        'long' => $coord['long'],
                        'altura' => is_numeric($coord['altura']) ? $coord['altura'] : null,
                        'index' => $index + 1,
                        'distanciaAcumulada' => $distanciasAcumuladas[$imagen]
                    ];
                }
            }
            echo json_encode($datosGrafico);
        ?>;

        function mostrarDebug(debugData, imagen) {
            try {
                // Limpiar datos antes de parsear
                const dataLimpia = debugData.replace(/[\x00-\x1F\x7F]/g, '');
                const data = JSON.parse(dataLimpia);
                document.getElementById('debugTitle').textContent = 'Datos EXIF - ' + imagen;
                document.getElementById('debugContent').textContent = JSON.stringify(data, null, 2);
                document.getElementById('debugModal').classList.remove('hidden');
            } catch (e) {
                console.error('Error al parsear datos de debug:', e);
                console.error('Datos problemáticos:', debugData);
                
                // Mostrar datos como texto plano si falla el JSON
                document.getElementById('debugTitle').textContent = 'Datos EXIF (Error JSON) - ' + imagen;
                document.getElementById('debugContent').textContent = 'Error al parsear JSON: ' + e.message + '\n\nDatos crudos:\n' + debugData;
                document.getElementById('debugModal').classList.remove('hidden');
            }
        }

        function cerrarDebug() {
            document.getElementById('debugModal').classList.add('hidden');
        }

        // Cerrar modal al hacer clic fuera de él
        document.getElementById('debugModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarDebug();
            }
        });

        // Función para dibujar el gráfico GPS
        function dibujarGraficoGPS() {
            if (coordenadasGPS.length === 0) {
                document.getElementById('chartInfo').textContent = 'No hay coordenadas GPS válidas para graficar.';
                return;
            }

            const canvas = document.getElementById('gpsChart');
            const ctx = canvas.getContext('2d');
            
            // Limpiar canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Dimensiones de la foto en metros (dinámicas)
            const fotoLargo = parseFloat(document.getElementById('fotoLargoInput').value) || 38.51; // metros
            const fotoAncho = parseFloat(document.getElementById('fotoAnchoInput').value) || 21.35; // metros
            
            // Calcular límites de coordenadas
            const lats = coordenadasGPS.map(p => p.lat);
            const longs = coordenadasGPS.map(p => p.long);
            const minLat = Math.min(...lats);
            const maxLat = Math.max(...lats);
            const minLong = Math.min(...longs);
            const maxLong = Math.max(...longs);
            
            // Agregar margen para incluir las fotos
            const latMargin = Math.max((maxLat - minLat) * 0.1, fotoLargo / 111000); // 1 grado lat ≈ 111km
            const longMargin = Math.max((maxLong - minLong) * 0.1, fotoAncho / (111000 * Math.cos(minLat * Math.PI / 180)));
            
            // Función para convertir coordenadas a píxeles
            function coordToPixel(lat, long) {
                const x = ((long - (minLong - longMargin)) / ((maxLong + longMargin) - (minLong - longMargin))) * canvas.width;
                const y = canvas.height - ((lat - (minLat - latMargin)) / ((maxLat + latMargin) - (minLat - latMargin))) * canvas.height;
                return { x, y };
            }
            
            // Función para calcular la orientación de la foto basada en la ruta
            function calcularOrientacionFoto(index) {
                if (index === 0) {
                    // Para la primera foto, usar la dirección hacia la segunda
                    if (coordenadasGPS.length > 1) {
                        const dx = coordenadasGPS[1].long - coordenadasGPS[0].long;
                        const dy = coordenadasGPS[1].lat - coordenadasGPS[0].lat;
                        return Math.atan2(dy, dx);
                    }
                    return 0; // Sin orientación si solo hay un punto
                } else if (index === coordenadasGPS.length - 1) {
                    // Para la última foto, usar la dirección desde la anterior
                    const dx = coordenadasGPS[index].long - coordenadasGPS[index - 1].long;
                    const dy = coordenadasGPS[index].lat - coordenadasGPS[index - 1].lat;
                    return Math.atan2(dy, dx);
                } else {
                    // Para fotos intermedias, usar la dirección promedio
                    const dx1 = coordenadasGPS[index].long - coordenadasGPS[index - 1].long;
                    const dy1 = coordenadasGPS[index].lat - coordenadasGPS[index - 1].lat;
                    const dx2 = coordenadasGPS[index + 1].long - coordenadasGPS[index].long;
                    const dy2 = coordenadasGPS[index + 1].lat - coordenadasGPS[index].lat;
                    
                    const angle1 = Math.atan2(dy1, dx1);
                    const angle2 = Math.atan2(dy2, dx2);
                    
                    // Promedio de ángulos
                    return (angle1 + angle2) / 2;
                }
            }

            // Función para calcular dimensiones de la foto en coordenadas
            function calcularDimensionesFoto(lat, long, orientacion) {
                // Convertir metros a grados (aproximado)
                const latPorMetro = 1 / 111000; // 1 grado lat ≈ 111km
                const longPorMetro = 1 / (111000 * Math.cos(lat * Math.PI / 180)); // Ajustar por latitud
                
                // Dimensiones en coordenadas
                // Lado largo (38.51m) perpendicular a la ruta
                const ladoLargoDelta = fotoLargo * latPorMetro / 2;
                // Lado ancho (21.35m) paralelo a la ruta
                const ladoAnchoDelta = fotoAncho * longPorMetro / 2;
                
                // Calcular los vectores base del rectángulo
                // Vector perpendicular (lado largo) - perpendicular a la ruta
                const perpX = Math.cos(orientacion + Math.PI / 2);
                const perpY = Math.sin(orientacion + Math.PI / 2);
                
                // Vector paralelo (lado ancho) - paralelo a la ruta
                const parX = Math.cos(orientacion);
                const parY = Math.sin(orientacion);
                
                // Calcular las 4 esquinas del rectángulo
                const puntos = [
                    // Esquina superior izquierda
                    { 
                        lat: lat + ladoLargoDelta * perpY - ladoAnchoDelta * parY, 
                        long: long + ladoLargoDelta * perpX - ladoAnchoDelta * parX 
                    },
                    // Esquina superior derecha
                    { 
                        lat: lat + ladoLargoDelta * perpY + ladoAnchoDelta * parY, 
                        long: long + ladoLargoDelta * perpX + ladoAnchoDelta * parX 
                    },
                    // Esquina inferior derecha
                    { 
                        lat: lat - ladoLargoDelta * perpY + ladoAnchoDelta * parY, 
                        long: long - ladoLargoDelta * perpX + ladoAnchoDelta * parX 
                    },
                    // Esquina inferior izquierda
                    { 
                        lat: lat - ladoLargoDelta * perpY - ladoAnchoDelta * parY, 
                        long: long - ladoLargoDelta * perpX - ladoAnchoDelta * parX 
                    }
                ];
                
                return puntos;
            }
            
            // Dibujar rectángulos de las fotos (cada N puntos)
            const mostrarRectangulos = document.getElementById('mostrarRectangulos').checked;
            const intervalo = parseInt(document.getElementById('intervaloRectangulos').value) || 5;
            if (mostrarRectangulos) {
                coordenadasGPS.forEach((punto, index) => {
                    // Mostrar solo cada N puntos (0, N, 2N, 3N, etc.)
                    if (index % intervalo === 0 || index === coordenadasGPS.length - 1) {
                        const orientacion = calcularOrientacionFoto(index);
                        const puntosRectangulo = calcularDimensionesFoto(punto.lat, punto.long, orientacion);
                        
                        // Convertir puntos a píxeles
                        const pixels = puntosRectangulo.map(p => coordToPixel(p.lat, p.long));
                        
                        // Dibujar rectángulo rotado
                        ctx.fillStyle = index === 0 ? 'rgba(239, 68, 68, 0.3)' : 'rgba(16, 185, 129, 0.3)';
                        ctx.beginPath();
                        ctx.moveTo(pixels[0].x, pixels[0].y);
                        for (let i = 1; i < pixels.length; i++) {
                            ctx.lineTo(pixels[i].x, pixels[i].y);
                        }
                        ctx.closePath();
                        ctx.fill();
                        
                        // Borde del rectángulo
                        ctx.strokeStyle = index === 0 ? '#EF4444' : '#10B981';
                        ctx.lineWidth = 1;
                        ctx.stroke();
                    }
                });
            }
            
            // Dibujar líneas de conexión
            ctx.strokeStyle = '#3B82F6';
            ctx.lineWidth = 2;
            ctx.beginPath();
            
            for (let i = 0; i < coordenadasGPS.length; i++) {
                const punto = coordToPixel(coordenadasGPS[i].lat, coordenadasGPS[i].long);
                if (i === 0) {
                    ctx.moveTo(punto.x, punto.y);
                } else {
                    ctx.lineTo(punto.x, punto.y);
                }
            }
            ctx.stroke();
            
            // Dibujar puntos
            coordenadasGPS.forEach((punto, index) => {
                const pixel = coordToPixel(punto.lat, punto.long);
                
                // Círculo del punto
                ctx.fillStyle = index === 0 ? '#EF4444' : '#10B981'; // Rojo para inicio, verde para otros
                ctx.beginPath();
                ctx.arc(pixel.x, pixel.y, 6, 0, 2 * Math.PI);
                ctx.fill();
                
                // Borde del punto
                ctx.strokeStyle = '#FFFFFF';
                ctx.lineWidth = 2;
                ctx.stroke();
                
                // Número del punto
                ctx.fillStyle = '#FFFFFF';
                ctx.font = 'bold 12px Arial';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(punto.index, pixel.x, pixel.y);
            });
            
            // Información del gráfico
            const areaTotal = coordenadasGPS.length * fotoLargo * fotoAncho;
            const rectangulosMostrados = Math.ceil(coordenadasGPS.length / intervalo);
            const info = `Puntos mostrados: ${coordenadasGPS.length} | Rectángulos mostrados: ${rectangulosMostrados} (cada ${intervalo} puntos) | Área total cubierta: ${(areaTotal/10000).toFixed(2)} ha | Dimensiones foto: ${fotoLargo}m x ${fotoAncho}m | Lado largo (${fotoLargo}m) perpendicular a la ruta, lado ancho (${fotoAncho}m) paralelo | Rango Lat: ${minLat.toFixed(6)} a ${maxLat.toFixed(6)} | Rango Long: ${minLong.toFixed(6)} a ${maxLong.toFixed(6)}`;
            document.getElementById('chartInfo').textContent = info;
        }

        // Función para actualizar indicadores de graficado
        function actualizarIndicadoresGraficado() {
            const intervalo = parseInt(document.getElementById('intervaloRectangulos').value) || 5;
            
            coordenadasGPS.forEach((punto, index) => {
                const elemento = document.getElementById(`graficado-${index}`);
                if (elemento) {
                    const esGraficado = index % intervalo === 0 || index === coordenadasGPS.length - 1;
                    if (esGraficado) {
                        elemento.textContent = 'Sí';
                        elemento.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800';
                    } else {
                        elemento.textContent = 'No';
                        elemento.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600';
                    }
                }
            });
        }

        // Dibujar gráfico cuando se carga la página
        window.addEventListener('load', function() {
            dibujarGraficoGPS();
            actualizarIndicadoresGraficado();
            
            // Event listener para el checkbox
            document.getElementById('mostrarRectangulos').addEventListener('change', function() {
                dibujarGraficoGPS();
            });
            
            // Event listener para el textbox de intervalo
            document.getElementById('intervaloRectangulos').addEventListener('change', function() {
                dibujarGraficoGPS();
                actualizarIndicadoresGraficado();
            });
            
            // Event listeners para los textboxes de dimensiones
            document.getElementById('fotoLargoInput').addEventListener('change', function() {
                dibujarGraficoGPS();
            });
            
            document.getElementById('fotoAnchoInput').addEventListener('change', function() {
                dibujarGraficoGPS();
            });
        });
    </script>
</body>
</html> 