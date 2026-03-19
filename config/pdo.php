<?php
require_once __DIR__ . "/constantesPDO.php";

class DB {
    private static ?PDO $pdo = null;

    public static function getPDO(): PDO {
        if (self::$pdo !== null) {
            try {
                self::$pdo->query('SELECT 1')->closeCursor();
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
            $port   = (int)($dbCfg['port'] ?? 3306);
            $dbname = $dbCfg['dbname'] ?? '';
            $user   = $dbCfg['username'] ?? '';
            $pass   = $dbCfg['password'] ?? '';

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true,  // Évite les erreurs 2006 sur certains hébergements
            ];

            // Ordre : TCP 127.0.0.1 (fonctionne sur XAMPP et Hostinger),
            //         puis socket Unix (fallback Linux)
            $candidates = [
                "mysql:host=127.0.0.1;port=$port;dbname=$dbname;charset=utf8mb4",
                "mysql:unix_socket=/var/lib/mysql/mysql.sock;dbname=$dbname;charset=utf8mb4",
                "mysql:host=localhost;dbname=$dbname;charset=utf8mb4",
            ];

            $lastError = null;
            foreach ($candidates as $dsn) {
                try {
                    $pdo = new PDO($dsn, $user, $pass, $options);
                    $pdo->query('SELECT 1')->closeCursor();
                    self::$pdo = $pdo;
                    $lastError = null;
                    break;
                } catch (PDOException $e) {
                    self::$pdo = null;
                    $lastError = $e;
                }
            }

            if ($lastError !== null) {
                throw new RuntimeException("Erreur de connexion BDD : " . $lastError->getMessage());
            }
        }

        return self::$pdo;
    }
}
