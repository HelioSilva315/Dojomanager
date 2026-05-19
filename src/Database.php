<?php
// =============================================================
// src/Database.php — Conexão PDO (Singleton)
// Credenciais lidas de variáveis de ambiente (.env via vlucas/phpdotenv)
// =============================================================

namespace DojoManager;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host   = $_ENV['DB_HOST']     ?? '127.0.0.1';
        $port   = $_ENV['DB_PORT']     ?? '3306';
        $dbname = $_ENV['DB_NAME']     ?? '';
        $user   = $_ENV['DB_USER']     ?? '';
        $pass   = $_ENV['DB_PASSWORD'] ?? '';
        $charset = 'utf8mb4';

        if (empty($dbname) || empty($user)) {
            throw new RuntimeException('Configurações de banco de dados não encontradas. Configure o arquivo .env');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        try {
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            // Nunca expor detalhes da conexão em produção
            if (APP_DEBUG) {
                throw new RuntimeException('Erro de conexão: ' . $e->getMessage());
            }
            throw new RuntimeException('Erro interno. Tente novamente.');
        }

        return self::$instance;
    }

    // Impede clonagem
    private function __clone() {}
    private function __construct() {}
}
