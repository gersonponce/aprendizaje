<?php
session_start();
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    header('Location: login.html');
    exit;
}

require_once 'database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    $database->createTables();
} catch(Exception $e) {
    die('Error de conexión: ' . $e->getMessage());
}

// Obtener estadísticas de etiquetas principales
$stmt = $pdo->query('SELECT etiqueta_principal, COUNT(*) as total FROM etiquetas GROUP BY etiqueta_principal ORDER BY total DESC');
$etiquetasPrincipales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas de etiquetas secundarias
$stmt = $pdo->query('SELECT etiquetas_secundarias, COUNT(*) as total FROM etiquetas WHERE etiquetas_secundarias != "" GROUP BY etiquetas_secundarias ORDER BY total DESC');
$etiquetasSecundarias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas por usuario
$stmt = $pdo->query('SELECT usuario, COUNT(*) as total FROM etiquetas GROUP BY usuario ORDER BY total DESC');
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas por fecha (últimos 30 días)
$stmt = $pdo->query('SELECT DATE(fecha) as fecha_dia, COUNT(*) as total FROM etiquetas WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(fecha) ORDER BY fecha_dia DESC');
$fechas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener total general
$stmt = $pdo->query('SELECT COUNT(*) as total FROM etiquetas');
$totalGeneral = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Obtener estadísticas de combinaciones principales-secundarias
$stmt = $pdo->query('SELECT etiqueta_principal, etiquetas_secundarias, COUNT(*) as total FROM etiquetas WHERE etiquetas_secundarias != "" GROUP BY etiqueta_principal, etiquetas_secundarias ORDER BY total DESC LIMIT 20');
$combinaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar etiquetas secundarias individuales
$etiquetasSecundariasIndividuales = [];
foreach ($etiquetasSecundarias as $row) {
    $secundarias = explode(',', $row['etiquetas_secundarias']);
    foreach ($secundarias as $secundaria) {
        $secundaria = trim($secundaria);
        if (!empty($secundaria)) {
            if (isset($etiquetasSecundariasIndividuales[$secundaria])) {
                $etiquetasSecundariasIndividuales[$secundaria] += $row['total'];
            } else {
                $etiquetasSecundariasIndividuales[$secundaria] = $row['total'];
            }
        }
    }
}
arsort($etiquetasSecundariasIndividuales);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Etiquetado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 text-center mb-6">Estadísticas de Etiquetado</h1>
        <div class="flex justify-end items-center mb-8">
            <div class="flex space-x-4">
                <a href="index.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Volver al Etiquetado
                </a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                    Cerrar Sesión
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Resumen General -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8 w-full">
                <h2 class="text-2xl font-semibold mb-4">Resumen General</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-blue-100 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-blue-800"><?php echo $totalGeneral; ?></div>
                        <div class="text-blue-600">Total de Imágenes Etiquetadas</div>
                    </div>
                    <div class="bg-green-100 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-green-800"><?php echo count($usuarios); ?></div>
                        <div class="text-green-600">Usuarios Activos</div>
                    </div>
                    <div class="bg-purple-100 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-purple-800"><?php echo count($etiquetasPrincipales); ?></div>
                        <div class="text-purple-600">Tipos de Etiquetas Principales</div>
                    </div>
                    <div class="bg-orange-100 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-orange-800"><?php echo count($etiquetasSecundariasIndividuales); ?></div>
                        <div class="text-orange-600">Tipos de Etiquetas Secundarias</div>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Etiquetas Principales -->
            <div class="bg-white rounded-lg shadow-md p-6 w-full">
                <h3 class="text-xl font-semibold mb-4">Etiquetas Principales</h3>
                <div class="relative" style="height: 300px;">
                    <canvas id="chartPrincipales"></canvas>
                </div>
                <div class="mt-4 overflow-x-auto w-full">
                    <table class="w-full min-w-[300px] text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 px-2">Etiqueta</th>
                                <th class="text-right py-2 px-2">Cantidad</th>
                                <th class="text-right py-2 px-2">Porcentaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($etiquetasPrincipales as $etiqueta): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 px-2"><?php echo htmlspecialchars($etiqueta['etiqueta_principal']); ?></td>
                                <td class="text-right py-2 px-2"><?php echo $etiqueta['total']; ?></td>
                                <td class="text-right py-2 px-2"><?php echo round(($etiqueta['total'] / $totalGeneral) * 100, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Gráfico de Etiquetas Secundarias -->
            <div class="bg-white rounded-lg shadow-md p-6 w-full">
                <h3 class="text-xl font-semibold mb-4">Etiquetas Secundarias (Top 10)</h3>
                <div class="relative" style="height: 300px;">
                    <canvas id="chartSecundarias"></canvas>
                </div>
                <div class="mt-4 overflow-x-auto w-full">
                    <table class="w-full min-w-[300px] text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 px-2">Etiqueta</th>
                                <th class="text-right py-2 px-2">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $topSecundarias = array_slice($etiquetasSecundariasIndividuales, 0, 10, true);
                            foreach ($topSecundarias as $etiqueta => $total): 
                            ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 px-2"><?php echo htmlspecialchars($etiqueta); ?></td>
                                <td class="text-right py-2 px-2"><?php echo $total; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Estadísticas por Usuario -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8 w-full">
                <h3 class="text-xl font-semibold mb-4">Estadísticas por Usuario</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="text-left py-3 px-4">Usuario</th>
                                <th class="text-right py-3 px-4">Imágenes Etiquetadas</th>
                                <th class="text-right py-3 px-4">Porcentaje del Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                                <td class="text-right py-3 px-4"><?php echo $usuario['total']; ?></td>
                                <td class="text-right py-3 px-4"><?php echo round(($usuario['total'] / $totalGeneral) * 100, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Actividad Reciente -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8 w-full">
                <h3 class="text-xl font-semibold mb-4">Actividad de los Últimos 30 Días</h3>
                <div class="relative" style="height: 300px;">
                    <canvas id="chartActividad"></canvas>
                </div>
            </div>

            <!-- Combinaciones Principales-Secundarias -->
            <div class="bg-white rounded-lg shadow-md p-6 w-full">
                <h3 class="text-xl font-semibold mb-4">Combinaciones Principales-Secundarias (Top 20)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="text-left py-3 px-4">Etiqueta Principal</th>
                                <th class="text-left py-3 px-4">Etiquetas Secundarias</th>
                                <th class="text-right py-3 px-4">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($combinaciones as $combinacion): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($combinacion['etiqueta_principal']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($combinacion['etiquetas_secundarias']); ?></td>
                                <td class="text-right py-3 px-4"><?php echo $combinacion['total']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gráfico de Etiquetas Principales
        const ctxPrincipales = document.getElementById('chartPrincipales').getContext('2d');
        new Chart(ctxPrincipales, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($etiquetasPrincipales, 'etiqueta_principal')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($etiquetasPrincipales, 'total')); ?>,
                    backgroundColor: [
                        '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                        '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6366F1'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 10,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // Gráfico de Etiquetas Secundarias
        const ctxSecundarias = document.getElementById('chartSecundarias').getContext('2d');
        const topSecundarias = <?php echo json_encode(array_slice($etiquetasSecundariasIndividuales, 0, 10, true)); ?>;
        new Chart(ctxSecundarias, {
            type: 'bar',
            data: {
                labels: Object.keys(topSecundarias),
                datasets: [{
                    label: 'Cantidad',
                    data: Object.values(topSecundarias),
                    backgroundColor: '#10B981'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Gráfico de Actividad
        const ctxActividad = document.getElementById('chartActividad').getContext('2d');
        const fechas = <?php echo json_encode(array_reverse($fechas)); ?>;
        new Chart(ctxActividad, {
            type: 'line',
            data: {
                labels: fechas.map(f => f.fecha_dia),
                datasets: [{
                    label: 'Imágenes Etiquetadas',
                    data: fechas.map(f => f.total),
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 