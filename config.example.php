<?php
// Archivo de ejemplo para configuración de la base de datos
// Copiar este archivo como config.php y ajustar los valores según tu entorno

return [
    'database' => [
        'host' => '127.0.0.1',        // Host de la base de datos
        'port' => 33060,              // Puerto de MySQL (3306 por defecto, 33060 para Laravel Homestead)
        'name' => 'drone_aprendizaje', // Nombre de la base de datos
        'username' => 'homestead',     // Usuario de la base de datos
        'password' => 'secret'         // Contraseña de la base de datos
    ],
    'app' => [
        'base_url' => 'http://localhost/aprendizaje' // URL base de la aplicación
    ]
];
?> 