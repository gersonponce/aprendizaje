<?php
// Verificar autenticación
session_start();
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'msg' => 'No autenticado']);
    exit;
}

// Incluir configuración de base de datos
require_once 'database.php';

// Variable global para el directorio de imágenes (se actualiza según el drone)
$imagenes_dir = __DIR__ . '/imagenes/DRONE_6/grilla/';

// Conectar a MySQL
try {
    $database = new Database();
    $pdo = $database->getConnection();
    $database->createTables();
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    exit;
}

// Permitir CORS para desarrollo
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');


function obtenerNombreDrone(string $nombreArchivo): string
{
    // Extrae la subcadena de 2 caracteres que empieza en la posición 4 (el quinto carácter).
    $numeroExtraido = substr($nombreArchivo, 4, 2);
    // Convierte la cadena extraída a un número entero.
    $numeroDrone = intval($numeroExtraido);
    // Retorna el nuevo nombre con el prefijo "DRONE_".
    return "DRONE_" . $numeroDrone;
}

function obtenerImagenesExcluidas($pdo): array
{
    $stmt = $pdo->query('SELECT foto_nombre FROM excepto');
    $excluidas = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $excluidas[] = $row['foto_nombre'];
    }
    return $excluidas;
}

function filtrarImagenesExcluidas($imagenes, $excluidas): array
{
    return array_filter($imagenes, function($imagen) use ($excluidas) {
        foreach ($excluidas as $excluida) {
            if (strpos($imagen, $excluida) === 0) {
                return false; // Excluir esta imagen
            }
        }
        return true; // Incluir esta imagen
    });
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Servir grilla global si se pide ?grillaglobal=DJI_XXXX&drone=DRONE_XX
    if (isset($_GET['grillaglobal']) && isset($_GET['drone'])) {
        $drone = basename($_GET['drone']);
        $base = basename($_GET['grillaglobal']); // e.g. DJI_0601
        $drone_num = preg_replace('/^DRONE_/', '', $drone);
        $img_path = __DIR__ . "/imagenes/DRONE_{$drone_num}/grillaglobal/{$base}_grilla_validacion.png";
        echo $img_path;
        if (file_exists($img_path)) {
            ob_clean();
            $mime = mime_content_type($img_path);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($img_path));
            readfile($img_path);
            exit;
        } else {
            http_response_code(404);
            echo 'Imagen no encontrada: ' . $img_path;
            exit;
        }
    }
    // Servir imagen si se pide ?img=nombre.jpg&drone=DRONE_XX
    if (isset($_GET['img'])) {
        $img = basename($_GET['img']);
        $img_path = __DIR__ . '/imagenes/' . obtenerNombreDrone($img) . '/grilla/' . $img;
        if (file_exists($img_path)) {
            $mime = mime_content_type($img_path);
            ob_clean();
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($img_path));
            readfile($img_path);
            exit;
        } else {
            http_response_code(404);
            echo 'Imagen no encontrada';
            exit;
        }
    }
    // Si se pide total_usuario=usuario, devolver el total de imágenes etiquetadas por ese usuario
    if (isset($_GET['total_usuario'])) {
        $usuario = $_GET['total_usuario'];
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM etiquetas WHERE usuario = ?');
        $stmt->bindParam(1, $usuario, PDO::PARAM_STR);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'ok', 'total' => $resultado['total']]);
        exit;
    }
    
    // Si se pide ultimos=1, devolver los últimos 50 registros del usuario actual
    if (isset($_GET['ultimos'])) {
        $usuario = $_SESSION['user']['username'] ?? 'desconocido';
        $stmt = $pdo->prepare('SELECT nombre_imagen, imagen_original, x_imagen, y_imagen, etiqueta_principal, etiquetas_secundarias, usuario, fecha FROM etiquetas WHERE usuario = ? ORDER BY fecha DESC LIMIT 50');
        $stmt->bindParam(1, $usuario, PDO::PARAM_STR);
        $stmt->execute();
        $registros = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Ajustar la fecha a Lima
            $dt = new DateTime($row['fecha'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Lima'));
            $row['fecha'] = $dt->format('Y-m-d H:i:s');
            $registros[] = $row;
        }
        echo json_encode(['status' => 'ok', 'registros' => $registros]);
        exit;
    }
    // Si se pide todas=1, devolver todas las imágenes no etiquetadas
    if (isset($_GET['todas'])) {
        $drone = isset($_GET['drone']) ? basename($_GET['drone']) : 'DRONE_6';
        $imagenes_dir_drone = __DIR__ . '/imagenes/' . $drone . '/grilla/';
        $imagenes = is_dir($imagenes_dir_drone) ? array_diff(scandir($imagenes_dir_drone), ['.', '..']) : [];
        
        // Obtener imágenes excluidas de la tabla excepto
        $excluidas = obtenerImagenesExcluidas($pdo);
        
        // Filtrar imágenes excluidas
        $imagenes = filtrarImagenesExcluidas($imagenes, $excluidas);
        
        // Actualizar la variable global para servir imágenes
        global $imagenes_dir;
        $imagenes_dir = $imagenes_dir_drone;
        
        $stmt = $pdo->query('SELECT nombre_imagen FROM etiquetas');
        $etiquetadas = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $etiquetadas[] = $row['nombre_imagen'];
        }
        $no_etiquetadas = array_values(array_diff($imagenes, $etiquetadas));
        echo json_encode([
            'status' => count($no_etiquetadas) ? 'ok' : 'done',
            'imagenes' => $no_etiquetadas
        ]);
        exit;
    }
    // Obtener la siguiente imagen sin etiquetar (usar DRONE_6 por defecto)
    $drone = 'DRONE_6';
    $imagenes_dir_drone = __DIR__ . '/imagenes/' . $drone . '/grilla/';
    $imagenes = is_dir($imagenes_dir_drone) ? array_diff(scandir($imagenes_dir_drone), ['.', '..']) : [];
    
    // Obtener imágenes excluidas de la tabla excepto
    $excluidas = obtenerImagenesExcluidas($pdo);
    
    // Filtrar imágenes excluidas
    $imagenes = filtrarImagenesExcluidas($imagenes, $excluidas);
    
    $stmt = $pdo->query('SELECT nombre_imagen FROM etiquetas');
    $etiquetadas = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $etiquetadas[] = $row['nombre_imagen'];
    }
    $no_etiquetadas = array_values(array_diff($imagenes, $etiquetadas));
    if (count($no_etiquetadas) === 0) {
        echo json_encode(['status' => 'done']);
        exit;
    }
    $imagen = $no_etiquetadas[0];
    echo json_encode([
        'status' => 'ok',
        'imagen' => $imagen
    ]);
    exit;
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $imagen = $data['imagen'] ?? null;
    
    if (!$imagen) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'msg' => 'Falta el nombre de la imagen']);
        exit;
    }
    
    // Eliminar la etiqueta de la base de datos
    $stmt = $pdo->prepare('DELETE FROM etiquetas WHERE nombre_imagen = ?');
    $stmt->bindParam(1, $imagen, PDO::PARAM_STR);
    
    $ok = $stmt->execute();
    if ($ok) {
        echo json_encode(['status' => 'ok', 'msg' => 'Etiqueta eliminada correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'msg' => 'No se pudo eliminar la etiqueta']);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $imagen = $data['imagen'] ?? null;
    $principales = $data['principales'] ?? [];
    $secundarias = $data['secundarias'] ?? [];
    $editar = $data['editar'] ?? false;
    $x_imagen=$data['x_imagen']??null;
    $y_imagen=$data['y_imagen']??null;
    $drone_sel=$data['drone_sel']??"";

    if (!$imagen || empty($principales)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'msg' => 'Faltan datos']);
        exit;
    }
    
    // Obtener el usuario de la sesión
    $usuario = $_SESSION['user']['username'] ?? 'desconocido';
    $principales_str = implode(',', $principales);
    $secundarias_str = implode(',', $secundarias);
    
    // Extraer imagen_original y coordenadas del nombre de la imagen
    $imagen_original = ($imagen)?substr($imagen,0,8):"";

    $path_imagen = "/imagenes/{$drone_sel}/grilla/{$imagen}";

    if ($editar) {
        // Editar etiquetas existentes
        $stmt = $pdo->prepare('UPDATE etiquetas SET etiqueta_principal = ?, etiquetas_secundarias = ?, usuario = ?, fecha = CURRENT_TIMESTAMP WHERE nombre_imagen = ?');
        $stmt->bindParam(1, $principales_str, PDO::PARAM_STR);
        $stmt->bindParam(2, $secundarias_str, PDO::PARAM_STR);
        $stmt->bindParam(3, $usuario, PDO::PARAM_STR);
        $stmt->bindParam(4, $imagen, PDO::PARAM_STR);

        $ok = $stmt->execute();
        if ($ok) {
            echo json_encode(['status' => 'ok']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'msg' => 'No se pudo editar']);
        }
        exit;
    } else {
        // Insertar nueva etiqueta
        $stmt = $pdo->prepare('INSERT INTO etiquetas (nombre_imagen, imagen_original, etiqueta_principal, etiquetas_secundarias, usuario,x_imagen,y_imagen,path_imagen) VALUES (?, ?, ?, ?, ?,?,?,?)');
        $stmt->bindParam(1, $imagen, PDO::PARAM_STR);
        $stmt->bindParam(2, $imagen_original, PDO::PARAM_STR);
        $stmt->bindParam(3, $principales_str, PDO::PARAM_STR);
        $stmt->bindParam(4, $secundarias_str, PDO::PARAM_STR);
        $stmt->bindParam(5, $usuario, PDO::PARAM_STR);
        $stmt->bindParam(6, $x_imagen, PDO::PARAM_INT);
        $stmt->bindParam(7, $y_imagen, PDO::PARAM_INT);
        $stmt->bindParam(8, $path_imagen, PDO::PARAM_STR);

        $ok = $stmt->execute();
        if ($ok) {
            echo json_encode(['status' => 'ok']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'msg' => 'No se pudo guardar']);
        }
        exit;
    }
}

// Si no es GET, POST ni DELETE
http_response_code(405);
echo json_encode(['status' => 'error', 'msg' => 'Método no permitido']); 