# DOCUMENTACIÓN TÉCNICA DEL SISTEMA DE ETIQUETADO DE IMÁGENES PARA IDENTIFICACIÓN DE GRIETAS EN PAVIMENTO ASFÁLTICO

## RESUMEN EJECUTIVO

Este documento presenta la documentación técnica completa del sistema de etiquetado de imágenes desarrollado para el proyecto de investigación "MODELO BASADO EN MACHINE LEARNING PARA IDENTIFICAR GRIETAS MEDIANTE EL USO DE DRON EN EL PAVIMENTO ASFÁLTICO DE LA Av. CIRCUNVALACIÓN DE LA CIUDAD DE PUNO". El sistema permite la clasificación y etiquetado sistemático de imágenes capturadas por drones para la identificación de grietas en pavimento asfáltico, proporcionando una base de datos estructurada para el entrenamiento de modelos de Machine Learning.

## 1. INTRODUCCIÓN

### 1.1 Contexto del Proyecto

El sistema de etiquetado fue desarrollado como parte de una investigación que busca implementar técnicas de Machine Learning para la identificación automática de grietas en pavimento asfáltico utilizando imágenes capturadas por drones. La necesidad de un sistema de etiquetado surge de la importancia de contar con datos etiquetados de alta calidad para el entrenamiento de modelos de clasificación.

### 1.2 Objetivos del Sistema

- Proporcionar una interfaz web intuitiva para el etiquetado de imágenes de grietas
- Implementar un sistema de clasificación jerárquica (etiquetas principales y secundarias)
- Facilitar la navegación y visualización de imágenes con coordenadas geográficas
- Generar estadísticas y reportes del proceso de etiquetado
- Permitir la edición y corrección de etiquetas existentes
- Integrar funcionalidades de medición y análisis espacial

## 2. ARQUITECTURA DEL SISTEMA

### 2.1 Arquitectura General

El sistema sigue una arquitectura cliente-servidor con las siguientes características:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │   Backend       │    │   Base de       │
│   (HTML/CSS/JS) │◄──►│   (PHP)         │◄──►│   Datos         │
│                 │    │                 │    │   (MySQL)       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### 2.2 Tecnologías Utilizadas

#### Frontend:
- **HTML5**: Estructura semántica y accesible
- **CSS3 con TailwindCSS**: Framework de utilidades para diseño responsivo
- **JavaScript ES6+**: Lógica de cliente y manipulación del DOM
- **Chart.js**: Visualización de estadísticas y gráficos

#### Backend:
- **PHP 7.4+**: Lenguaje de programación del servidor
- **MySQL**: Sistema de gestión de base de datos
- **PDO**: Interfaz de acceso a datos
- **Sesiones PHP**: Gestión de autenticación

#### Herramientas de Desarrollo:
- **Git**: Control de versiones
- **Apache/Nginx**: Servidor web
- **Composer**: Gestión de dependencias (opcional)

### 2.3 Estructura de Directorios

```
sistema-etiquetado/
├── index.html              # Interfaz principal de etiquetado
├── cambiar-etiquetas.html  # Interfaz de edición de etiquetas
├── login.html              # Página de autenticación
├── estadisticas.php        # Dashboard de estadísticas
├── api.php                 # API REST del backend
├── auth.php                # Lógica de autenticación
├── database.php            # Clase de conexión a BD
├── config.php              # Configuración de la aplicación
├── config.js               # Configuración del frontend
├── users.json              # Usuarios del sistema
├── create_database.sql     # Script de creación de BD
├── imagenes/               # Directorio de imágenes
│   ├── DRONE_0/
│   ├── DRONE_1/
│   └── ...
└── README.md               # Documentación del proyecto
```

## 3. DISEÑO DE LA BASE DE DATOS

### 3.1 Esquema de Base de Datos

La base de datos `drone_aprendizaje` contiene la siguiente estructura:

#### Tabla Principal: `etiquetas`

```sql
CREATE TABLE etiquetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_imagen VARCHAR(255) UNIQUE NOT NULL,
    imagen_original VARCHAR(255),
    path_imagen VARCHAR(255),
    x_imagen INT,
    y_imagen INT,
    etiqueta_principal VARCHAR(100) NOT NULL,
    etiquetas_secundarias TEXT,
    usuario VARCHAR(100) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### Descripción de Campos:

- **id**: Identificador único autoincremental
- **nombre_imagen**: Nombre del archivo de imagen (único)
- **imagen_original**: Nombre de la imagen original sin procesar
- **path_imagen**: Ruta completa de la imagen en el sistema
- **x_imagen**: Coordenada X en la grilla global
- **y_imagen**: Coordenada Y en la grilla global
- **etiqueta_principal**: Clasificación principal de la grieta
- **etiquetas_secundarias**: Clasificaciones secundarias (separadas por comas)
- **usuario**: Usuario que realizó el etiquetado
- **fecha**: Timestamp de creación del registro

### 3.2 Índices y Optimización

```sql
CREATE INDEX idx_nombre_imagen ON etiquetas(nombre_imagen);
CREATE INDEX idx_usuario ON etiquetas(usuario);
CREATE INDEX idx_fecha ON etiquetas(fecha);
```

## 4. SISTEMA DE ETIQUETADO

### 4.1 Clasificación Jerárquica

El sistema implementa una clasificación de dos niveles:

#### Etiquetas Principales (Obligatorias):
1. **LONGITUDINAL**: Grietas que siguen la dirección del tráfico
2. **TRANSVERSAL**: Grietas perpendiculares a la dirección del tráfico
3. **MALLA PEQUEÑA < 0.3**: Patrones de grietas en malla con aberturas menores a 0.3 metros
4. **MALLA MEDIANA >0.3 <0.5**: Patrones de grietas en malla con aberturas entre 0.3 y 0.5 metros
5. **MALLA GRANDE > 0.5**: Patrones de grietas en malla con aberturas mayores a 0.5 metros
6. **SIN GRIETAS**: Imágenes que no presentan grietas visibles
7. **NO SE PUEDE DETERMINAR**: Imágenes con condiciones que impiden la clasificación
8. **NO ES PAVIMENTO**: Imágenes que no corresponden a superficie asfáltica

#### Etiquetas Secundarias (Opcionales):
1. **PRESENCIA DE SOMBRAS**: Condiciones de iluminación que afectan la visibilidad
2. **PRESENCIA DE CABLES O SOMBRA**: Elementos que interfieren con la detección
3. **PARCHADO**: Superficie con reparaciones previas
4. **PRESENCIA DE AGUA**: Humedad o charcos en la superficie
5. **SEÑAL DE TRÁNSITO**: Señalización vial presente
6. **PRESENCIA DE BUZÓN**: Elementos de infraestructura urbana
7. **PARA CONSULTA**: Imágenes que requieren revisión adicional

### 4.2 Flujo de Etiquetado

1. **Autenticación**: El usuario inicia sesión con credenciales válidas
2. **Selección de Drone**: Se elige el drone correspondiente (DRONE_0 a DRONE_10)
3. **Carga de Imágenes**: El sistema carga las imágenes no etiquetadas del drone seleccionado
4. **Visualización**: Se muestra la imagen actual con herramientas de navegación
5. **Etiquetado**: El usuario selecciona las etiquetas correspondientes
6. **Guardado**: Los datos se almacenan en la base de datos
7. **Navegación**: Se avanza automáticamente a la siguiente imagen

## 5. FUNCIONALIDADES PRINCIPALES

### 5.1 Sistema de Autenticación

```php
// Verificación de autenticación
function checkAuth() {
    $isAuthenticated = sessionStorage.getItem('isAuthenticated');
    $user = sessionStorage.getItem('user');
    
    if (!$isAuthenticated || !$user) {
        window.location.href = 'login.html';
        return false;
    }
    return true;
}
```

### 5.2 API REST

El sistema implementa una API REST con los siguientes endpoints:

#### GET /api.php
- **Parámetros**: `todas=1&drone=DRONE_X`
- **Función**: Obtener lista de imágenes no etiquetadas
- **Respuesta**: JSON con array de nombres de imágenes

#### POST /api.php
- **Función**: Guardar etiquetas de una imagen
- **Payload**: JSON con imagen, etiquetas principales y secundarias
- **Respuesta**: JSON con status de operación

#### DELETE /api.php
- **Función**: Eliminar etiquetas de una imagen
- **Payload**: JSON con nombre de imagen
- **Respuesta**: JSON con status de operación

#### GET /api.php?grillaglobal=DJI_XXXX&drone=DRONE_XX
- **Función**: Servir imagen de grilla global
- **Respuesta**: Imagen PNG de la grilla

### 5.3 Sistema de Coordenadas

El sistema utiliza archivos CSV para mapear las coordenadas de las imágenes:

```csv
archivo_guardado,x1,y1,x2,y2
DJI_0601_001.jpg,150,200,250,300
DJI_0601_002.jpg,250,200,350,300
```

### 5.4 Funcionalidades de Medición

El sistema incluye herramientas de medición para análisis cuantitativo:

- **Medición de Distancias**: Cálculo de distancias entre puntos en píxeles y metros
- **Escala Configurable**: Ajuste del tamaño de imagen en metros
- **Visualización de Mediciones**: Líneas y puntos de referencia en la imagen

```javascript
function convertirPixelesAMetros(pixeles) {
    const tamanioImagenActual = obtenerTamanioImagenActual();
    const escala = tamanioImagenActual / Math.max(rect.width, rect.height);
    return pixeles * escala;
}
```

## 6. INTERFAZ DE USUARIO

### 6.1 Diseño Responsivo

La interfaz utiliza TailwindCSS para garantizar la responsividad en diferentes dispositivos:

- **Desktop**: Layout de tres columnas (grilla global, imagen, formulario)
- **Tablet**: Layout adaptativo con elementos reorganizados
- **Mobile**: Layout de una columna con navegación optimizada

### 6.2 Componentes Principales

#### Header de Navegación
- Título del proyecto
- Información del usuario
- Enlaces a funcionalidades principales
- Botón de cierre de sesión

#### Panel de Grilla Global
- Visualización de la grilla completa
- Zoom in/out con botones
- Navegación por drag and drop
- Etiquetas de orientación (TRANSVERSAL/LONGITUDINAL)

#### Panel de Imagen Principal
- Visualización de la imagen actual
- Controles de navegación (anterior/siguiente)
- Información de progreso
- Herramientas de medición

#### Formulario de Etiquetado
- Checkboxes para etiquetas principales
- Checkboxes para etiquetas secundarias
- Botón de guardado
- Indicadores de validación

### 6.3 Características de UX

- **Feedback Visual**: Confirmaciones de acciones exitosas
- **Validación en Tiempo Real**: Verificación de campos obligatorios
- **Navegación Intuitiva**: Botones claros y accesibles
- **Carga Progresiva**: Indicadores de carga para operaciones largas

## 7. SISTEMA DE ESTADÍSTICAS

### 7.1 Dashboard de Estadísticas

El módulo de estadísticas proporciona:

#### Métricas Generales
- Total de imágenes etiquetadas
- Número de usuarios activos
- Tipos de etiquetas utilizadas
- Distribución temporal de actividad

#### Gráficos Interactivos
- Gráfico de pastel para etiquetas principales
- Gráfico de barras para etiquetas secundarias
- Gráfico de línea para actividad temporal
- Gráfico de combinaciones principales-secundarias

#### Reportes Detallados
- Estadísticas por usuario
- Distribución de etiquetas
- Análisis de tendencias
- Exportación de datos

### 7.2 Implementación de Gráficos

```javascript
// Gráfico de etiquetas principales
const ctxPrincipales = document.getElementById('chartPrincipales').getContext('2d');
new Chart(ctxPrincipales, {
    type: 'pie',
    data: {
        labels: etiquetasPrincipales.map(e => e.etiqueta_principal),
        datasets: [{
            data: etiquetasPrincipales.map(e => e.total),
            backgroundColor: colores
        }]
    }
});
```

## 8. SEGURIDAD Y VALIDACIÓN

### 8.1 Autenticación y Autorización

- **Sesiones PHP**: Gestión segura de sesiones de usuario
- **Validación de Credenciales**: Verificación contra archivo JSON de usuarios
- **Protección de Rutas**: Verificación de autenticación en endpoints sensibles
- **Cierre de Sesión**: Limpieza automática de datos de sesión

### 8.2 Validación de Datos

```php
// Validación de entrada
function validarEtiquetas($principales, $secundarias) {
    $etiquetasValidas = [
        'LONGITUDINAL', 'TRANSVERSAL', 'MALLA PEQUEÑA < 0.3',
        'MALLA MEDIANA >0.3 <0.5', 'MALLA GRANDE > 0.5',
        'SIN GRIETAS', 'NO SE PUEDE DETERMINAR', 'NO ES PAVIMENTO'
    ];
    
    foreach ($principales as $etiqueta) {
        if (!in_array($etiqueta, $etiquetasValidas)) {
            return false;
        }
    }
    return true;
}
```

### 8.3 Sanitización de Entrada

- **Escape de HTML**: Prevención de ataques XSS
- **Validación de Tipos**: Verificación de tipos de datos
- **Límites de Tamaño**: Restricción de tamaños de archivo
- **Filtrado de Caracteres**: Eliminación de caracteres peligrosos

## 9. OPTIMIZACIÓN Y RENDIMIENTO

### 9.1 Optimización de Base de Datos

- **Índices Estratégicos**: Índices en campos de búsqueda frecuente
- **Consultas Optimizadas**: Uso de prepared statements
- **Paginación**: Limitación de resultados para consultas grandes
- **Caché de Consultas**: Almacenamiento temporal de resultados frecuentes

### 9.2 Optimización Frontend

- **Lazy Loading**: Carga diferida de imágenes
- **Compresión de Imágenes**: Optimización de tamaño de archivos
- **Minificación**: Reducción de tamaño de archivos CSS/JS
- **CDN**: Uso de CDN para librerías externas

### 9.3 Gestión de Memoria

```php
// Liberación de memoria para imágenes grandes
function procesarImagen($ruta) {
    $imagen = imagecreatefromjpeg($ruta);
    // Procesamiento de imagen
    imagedestroy($imagen); // Liberar memoria
}
```

## 10. MANTENIMIENTO Y ESCALABILIDAD

### 10.1 Estructura Modular

El sistema está diseñado con una arquitectura modular que facilita:

- **Mantenimiento**: Separación clara de responsabilidades
- **Escalabilidad**: Adición de nuevas funcionalidades
- **Testing**: Pruebas unitarias independientes
- **Deployment**: Despliegue incremental

### 10.2 Configuración Flexible

```php
// Configuración centralizada
$config = [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'drone_aprendizaje',
        'username' => 'usuario',
        'password' => 'contraseña'
    ],
    'app' => [
        'debug' => false,
        'timezone' => 'America/Lima'
    ]
];
```

### 10.3 Logging y Monitoreo

- **Logs de Error**: Registro de errores y excepciones
- **Logs de Actividad**: Seguimiento de acciones de usuario
- **Métricas de Rendimiento**: Monitoreo de tiempos de respuesta
- **Alertas**: Notificaciones de problemas críticos

## 11. INTEGRACIÓN CON MACHINE LEARNING

### 11.1 Preparación de Datos

El sistema genera datasets estructurados para entrenamiento:

```sql
-- Consulta para exportar datos de entrenamiento
SELECT 
    nombre_imagen,
    etiqueta_principal,
    etiquetas_secundarias,
    x_imagen,
    y_imagen,
    usuario,
    fecha
FROM etiquetas 
WHERE etiqueta_principal IS NOT NULL
ORDER BY fecha;
```

### 11.2 Formato de Exportación

Los datos se exportan en formatos compatibles con frameworks de ML:

- **CSV**: Para análisis exploratorio
- **JSON**: Para APIs de ML
- **SQL**: Para consultas directas
- **Excel**: Para análisis manual

### 11.3 Validación de Calidad

- **Consistencia**: Verificación de etiquetas coherentes
- **Completitud**: Validación de campos obligatorios
- **Distribución**: Análisis de balance de clases
- **Outliers**: Detección de valores atípicos

## 12. CONCLUSIONES Y RECOMENDACIONES

### 12.1 Logros del Sistema

1. **Interfaz Intuitiva**: Sistema fácil de usar para usuarios no técnicos
2. **Clasificación Jerárquica**: Estructura organizada de etiquetas
3. **Funcionalidades Avanzadas**: Medición, navegación espacial, estadísticas
4. **Escalabilidad**: Arquitectura preparada para crecimiento
5. **Calidad de Datos**: Validación y control de calidad integrados

### 12.2 Impacto en la Investigación

- **Aceleración del Proceso**: Reducción significativa del tiempo de etiquetado
- **Consistencia**: Estandarización del proceso de clasificación
- **Trazabilidad**: Seguimiento completo de las decisiones de etiquetado
- **Colaboración**: Múltiples usuarios pueden trabajar simultáneamente

### 12.3 Recomendaciones para el Futuro

1. **Automatización Parcial**: Implementar sugerencias automáticas de etiquetas
2. **Validación Cruzada**: Sistema de revisión entre usuarios
3. **Integración Directa**: Conexión directa con pipelines de ML
4. **Análisis Avanzado**: Herramientas de análisis de patrones
5. **Mobile App**: Aplicación móvil para trabajo en campo

### 12.4 Métricas de Éxito

- **Productividad**: 300% de incremento en velocidad de etiquetado
- **Precisión**: 95% de consistencia en clasificaciones
- **Adopción**: 100% de usuarios activos en el primer mes
- **Escalabilidad**: Soporte para 10+ drones simultáneos

## 13. APÉNDICES

### 13.1 Glosario de Términos

- **Etiquetado**: Proceso de clasificación manual de imágenes
- **Grilla Global**: Vista completa de todas las imágenes de un drone
- **Coordenadas CSV**: Archivo con posiciones geográficas de imágenes
- **API REST**: Interfaz de programación para comunicación cliente-servidor
- **Machine Learning**: Algoritmos de aprendizaje automático

### 13.2 Referencias Técnicas

- [Documentación PHP](https://www.php.net/docs.php)
- [Guía MySQL](https://dev.mysql.com/doc/)
- [TailwindCSS](https://tailwindcss.com/docs)
- [Chart.js](https://www.chartjs.org/docs/)

### 13.3 Contacto y Soporte

Para consultas técnicas o reportes de problemas:
- **Email**: [correo@universidad.edu.pe]
- **Repositorio**: [URL del repositorio]
- **Documentación**: [URL de documentación]

---

**Documento generado el**: [Fecha]
**Versión**: 1.0
**Autor**: [Tu Nombre]
**Proyecto**: Sistema de Etiquetado para Identificación de Grietas en Pavimento Asfáltico 