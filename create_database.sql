-- Script para crear la base de datos drone_aprendizaje
-- Ejecutar en MySQL/MariaDB

-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS drone_aprendizaje 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Usar la base de datos
USE drone_aprendizaje;

-- Crear la tabla etiquetas
CREATE TABLE IF NOT EXISTS etiquetas (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear índices para mejorar el rendimiento
CREATE INDEX idx_nombre_imagen ON etiquetas(nombre_imagen);
CREATE INDEX idx_usuario ON etiquetas(usuario);
CREATE INDEX idx_fecha ON etiquetas(fecha);

-- Mostrar la estructura de la tabla
DESCRIBE etiquetas; 