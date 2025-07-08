<?php
// Configuración de la base de datos MySQL
class Database {
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    private $pdo;

    public function __construct() {
        // Cargar configuración desde config.php
        $config = require_once 'config.php';
        $dbConfig = $config['database'];
        
        $this->host = $dbConfig['host'];
        $this->port = $dbConfig['port'];
        $this->dbname = $dbConfig['name'];
        $this->username = $dbConfig['username'];
        $this->password = $dbConfig['password'];

        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4", 
                $this->username, 
                $this->password
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            throw new Exception('Error de conexión a la base de datos: ' . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function createTables() {
        try {
            // Crear tabla etiquetas si no existe
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS etiquetas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre_imagen VARCHAR(255) UNIQUE,
                imagen_original VARCHAR(255),
                X INT,
                Y INT,
                etiqueta_principal VARCHAR(100),
                etiquetas_secundarias TEXT,
                usuario VARCHAR(100),
                fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Verificar si existen las nuevas columnas, si no, agregarlas
            $this->addColumnsIfNotExist();
            
            return true;
        } catch(PDOException $e) {
            throw new Exception('Error al crear tablas: ' . $e->getMessage());
        }
    }

    private function addColumnsIfNotExist() {
        try {
            // Verificar si existe la columna imagen_original
            $stmt = $this->pdo->query("SHOW COLUMNS FROM etiquetas LIKE 'imagen_original'");
            if ($stmt->rowCount() == 0) {
                $this->pdo->exec("ALTER TABLE etiquetas ADD COLUMN imagen_original VARCHAR(255) AFTER nombre_imagen");
            }
            
            // Verificar si existe la columna X
            $stmt = $this->pdo->query("SHOW COLUMNS FROM etiquetas LIKE 'X'");
            if ($stmt->rowCount() == 0) {
                $this->pdo->exec("ALTER TABLE etiquetas ADD COLUMN X INT AFTER imagen_original");
            }
            
            // Verificar si existe la columna Y
            $stmt = $this->pdo->query("SHOW COLUMNS FROM etiquetas LIKE 'Y'");
            if ($stmt->rowCount() == 0) {
                $this->pdo->exec("ALTER TABLE etiquetas ADD COLUMN Y INT AFTER X");
            }
        } catch(PDOException $e) {
            // Ignorar errores si las columnas ya existen
        }
    }
}
?> 