<?php
require_once __DIR__ . "/constantesPDO.php";

class DB {
    private static ?PDO $pdo = null;

    public static function getPDO(): PDO {
        // Vérifie si la connexion est toujours active (évite "MySQL server has gone away")
        if (self::$pdo !== null) {
            try {
                self::$pdo->query('SELECT 1');
            } catch (PDOException $e) {
                self::$pdo = null;
            }
        }

        if (self::$pdo === null) {
            $cfg = ConstantesPDO::getInstance()->getConfig();

            if (!isset($cfg['db'])) {
                throw new Exception("Configuration de la base de données introuvable dans config.json");
            }

            $dbCfg = $cfg['db'];

            // Utilise 127.0.0.1 plutôt que localhost pour éviter les erreurs de socket Unix
            $host   = ($dbCfg['host'] ?? 'localhost') === 'localhost' ? '127.0.0.1' : ($dbCfg['host'] ?? '127.0.0.1');
            $port   = $dbCfg['port'] ?? 3306;
            $dbname = $dbCfg['dbname'] ?? '';
            $user   = $dbCfg['username'] ?? '';
            $pass   = $dbCfg['password'] ?? '';

            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 5,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                ]);
            } catch (PDOException $e) {
                die("Erreur de connexion à la base de données : " . $e->getMessage());
            }
        }

        return self::$pdo;
    }
}
