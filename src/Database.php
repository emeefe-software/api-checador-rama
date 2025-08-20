<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private $pdo;

    public function __construct(array $settings)
    {
        $host = $settings['host'];
        $dbname = $settings['dbname'];
        $user = $settings['user'];
        $pass = $settings['pass'];
        $charset = $settings['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Manejo de errores
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Resultado como arreglo asociativo
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new \Exception("Error en conexión a base de datos: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->pdo;
    }
}
