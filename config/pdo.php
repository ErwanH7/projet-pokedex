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

            $dbCfg  = $cfg['db'];
            $host   = $dbCfg['host'] ?? 'localhost';
            $port   = $dbCfg['port'] ?? 3306;
            $dbname = $dbCfg['dbname'] ?? '';
            $user   = $dbCfg['username'] ?? '';
            $pass   = $dbCfg['password'] ?? '';

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ];

            // Liste des DSN à tester dans l'ordre
            $candidates = [
                "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
                "mysql:host=127.0.0.1;port=$port;dbname=$dbname;charset=utf8mb4",
                "mysql:host=localhost;dbname=$dbname;charset=utf8mb4",
            ];
            // Dédoublonnage (évite de retenter le même DSN si host = localhost ou 127.0.0.1)
            $candidates = array_values(array_unique($candidates));

            $lastError = null;
            foreach ($candidates as $dsn) {
                try {
                    self::$pdo = new PDO($dsn, $user, $pass, $options);
                    $lastError = null;
                    break; // Connexion réussie
                } catch (PDOException $e) {
                    self::$pdo = null;
                    $lastError = $e;
                }
            }

            if ($lastError !== null) {
                die("Erreur de connexion à la base de données : " . $lastError->getMessage());
            }
        }

        return self::$pdo;
    }
}
