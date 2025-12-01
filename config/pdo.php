<?php
require_once __DIR__ . "/constantesPDO.php";

class DB {
    private static ?PDO $pdo = null;

    public static function getPDO(): PDO {
        if (self::$pdo === null) {
            // Récupère la configuration
            $cfg = ConstantesPDO::getInstance()->getConfig();

            if (!isset($cfg['db'])) {
                throw new Exception("Configuration de la base de données introuvable dans config.json");
            }

            $dbCfg = $cfg['db'];

            $host = $dbCfg['host'] ?? 'localhost';
            $port = $dbCfg['port'] ?? 3306;
            $dbname = $dbCfg['dbname'] ?? '';
            $user = $dbCfg['username'] ?? '';
            $pass = $dbCfg['password'] ?? '';

            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                die("Erreur de connexion à la base de données : " . $e->getMessage());
            }
        }

        return self::$pdo;
    }
}
