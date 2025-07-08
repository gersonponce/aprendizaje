# Sistema de Etiquetado de Imágenes de Grietas en Pavimento

Sistema web para etiquetar imágenes de grietas en pavimento asfáltico capturadas por drones, desarrollado para el proyecto de investigación sobre identificación de grietas mediante Machine Learning.

## 🚀 Características

- **Autenticación de usuarios** con sistema de login
- **Etiquetado de imágenes** con categorías principales y secundarias
- **Visualización de grilla global** con zoom y navegación
- **Sistema de coordenadas CSV** para ubicación precisa
- **Interfaz responsiva** con TailwindCSS
- **Base de datos SQLite** para almacenamiento local
- **API REST** para comunicación frontend-backend

## 📋 Etiquetas Disponibles

### Etiquetas Principales:
- LONGITUDINAL
- TRANSVERSAL
- LONGITUDINAL Y TRANSVERSAL
- TIPO MALLA
- SIN GRIETAS
- NO SE PUEDE DETERMINAR

### Etiquetas Secundarias:
- SOMBRA
- CABLES
- PARCHADO
- AGUA
- SEÑAL DE TRÁNSITO
- BUZÓN

## 🛠️ Instalación

### Requisitos:
- PHP 7.4 o superior
- Servidor web (Apache/Nginx)
- Navegador web moderno

### Pasos:

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/tu-usuario/sistema-etiquetado.git
   cd sistema-etiquetado
   ```

2. **Configurar la aplicación:**
   ```bash
   # Copiar el archivo de ejemplo
   cp env.example config.js
   
   # Editar config.js con tu configuración
   nano config.js
   ```

3. **Configurar usuarios:**
   ```bash
   # Editar users.json con tus usuarios
   nano users.json
   ```

4. **Iniciar el servidor:**
   ```bash
   # Con PHP built-in server
   php -S localhost:3000
   
   # O configurar Apache/Nginx
   ```

## ⚙️ Configuración

### Archivo `config.js`:
```javascript
const BASE_URL = 'http://localhost:3000';
```

### Archivo `users.json`:
```json
{
  "users": [
    {
      "username": "tu-usuario",
      "password": "tu-contraseña",
      "role": "admin"
    }
  ]
}
```

## 📁 Estructura del Proyecto

```
sistema-etiquetado/
├── index.html          # Página principal
├── login.html          # Página de login
├── config.js           # Configuración (crear desde env.example)
├── api.php             # API backend
├── auth.php            # Autenticación
├── logout.php          # Cierre de sesión
├── users.json          # Usuarios (crear localmente)
├── etiquetas.db        # Base de datos (se crea automáticamente)
├── imagenes/           # Imágenes de los drones
│   ├── DRONE_0/
│   ├── DRONE_1/
│   └── ...
├── .gitignore          # Archivos ignorados por Git
├── env.example         # Ejemplo de configuración
└── README.md           # Este archivo
```

## 🔐 Autenticación

El sistema incluye autenticación de usuarios con:
- Login con usuario y contraseña
- Sesiones PHP
- Roles de usuario (admin/user)
- Logout automático

## 🗄️ Base de Datos

- **SQLite** para almacenamiento local
- Tabla `etiquetas` con campos:
  - `id` (PRIMARY KEY)
  - `nombre_imagen`
  - `etiqueta_principal`
  - `etiquetas_secundarias`
  - `usuario`
  - `fecha`

## 📊 Funcionalidades

### Etiquetado:
- Selección de etiqueta principal (obligatoria)
- Múltiples etiquetas secundarias (opcionales)
- Guardado automático en base de datos
- Edición de registros existentes

### Navegación:
- Botones anterior/siguiente
- Contador de imágenes pendientes
- Selector de drone (DRONE_0 a DRONE_10)

### Visualización:
- Grilla global con zoom y drag
- Coordenadas CSV para centrado automático
- Etiquetas de orientación (TRANSVERSAL/LONGITUDINAL)
- Imágenes clickeables en tabla

## 🔧 Desarrollo

### Tecnologías Utilizadas:
- **Frontend:** HTML5, CSS3 (TailwindCSS), JavaScript (ES6+)
- **Backend:** PHP 7.4+
- **Base de Datos:** SQLite
- **Autenticación:** Sesiones PHP

### Estructura de Archivos:
- `index.html` - Interfaz principal
- `api.php` - Endpoints de la API
- `auth.php` - Autenticación
- `config.js` - Configuración

## 📝 Uso

1. **Acceder al sistema:** `http://localhost:3000`
2. **Iniciar sesión** con credenciales válidas
3. **Seleccionar drone** del dropdown
4. **Etiquetar imágenes** usando el formulario
5. **Navegar** con botones anterior/siguiente
6. **Ver historial** en la tabla inferior

## 🤝 Contribución

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 👥 Autores

- **Tu Nombre** - *Desarrollo inicial* - [TuUsuario](https://github.com/TuUsuario)

## 🙏 Agradecimientos

- Universidad de Puno
- Proyecto de investigación sobre Machine Learning
- Equipo de desarrollo

## 📞 Contacto

- **Email:** tu-email@ejemplo.com
- **GitHub:** [@TuUsuario](https://github.com/TuUsuario)
- **Proyecto:** [https://github.com/TuUsuario/sistema-etiquetado](https://github.com/TuUsuario/sistema-etiquetado) 