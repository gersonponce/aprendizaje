<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$username = trim($input['username']);
$password = trim($input['password']);

// Load users from JSON file
$usersFile = 'users.json';
if (!file_exists($usersFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
    exit;
}

$usersData = json_decode(file_get_contents($usersFile), true);
if (!$usersData || !isset($usersData['users'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
    exit;
}

// Check credentials
$authenticated = false;
$user = null;

foreach ($usersData['users'] as $userData) {
    if ($userData['username'] === $username && $userData['password'] === $password) {
        $authenticated = true;
        $user = [
            'username' => $userData['username'],
            'role' => $userData['role']
        ];
        break;
    }
}

if ($authenticated) {
    // Start session and store user info
    session_start();
    $_SESSION['user'] = $user;
    $_SESSION['authenticated'] = true;
    
    echo json_encode([
        'success' => true,
        'message' => 'Autenticación exitosa',
        'user' => $user
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario o contraseña incorrectos'
    ]);
}
?> 