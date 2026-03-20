<?php
/**
 * Script de migration — à exécuter UNE FOIS puis supprimer.
 * Ajoute les colonnes multilingues manquantes.
 */
declare(strict_types=1);
require_once __DIR__ . '/config/constantesPDO.php';

$config = ConstantesPDO::getInstance()->getConfig();
$dbCfg  = $config['db'] ?? null;
if (!$dbCfg) { die("❌ Config DB introuvable."); }

try {
    $pdo = new PDO(
        "mysql:host={$dbCfg['host']};dbname={$dbCfg['dbname']};charset=utf8mb4",
        $dbCfg['username'],
        $dbCfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("❌ Connexion DB échouée : " . htmlspecialchars($e->getMessage()));
}

/**
 * Ajoute une colonne seulement si elle est absente (compatible MySQL 5.x / 8.x).
 */
function migrate_add_column(PDO $pdo, string $table, string $column, string $definition): array {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    if ((int)$stmt->fetchColumn() > 0) {
        return ['skip', "{$table}.{$column} — déjà présente"];
    }
    try {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        return ['ok', "{$table}.{$column}"];
    } catch (PDOException $e) {
        return ['err', "{$table}.{$column} — " . $e->getMessage()];
    }
}

function migrate_modify_column(PDO $pdo, string $table, string $column, string $definition): array {
    try {
        $pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$definition}");
        return ['ok', "{$table}.{$column} modifiée"];
    } catch (PDOException $e) {
        return ['err', "{$table}.{$column} — " . $e->getMessage()];
    }
}

$results = [];
$results[] = migrate_add_column($pdo, 'pokedex_list', 'name_en', 'VARCHAR(100) NULL AFTER name');
$results[] = migrate_add_column($pdo, 'pokedex_list', 'name_de', 'VARCHAR(100) NULL AFTER name_en');
$results[] = migrate_add_column($pdo, 'pokedex_list', 'name_es', 'VARCHAR(100) NULL AFTER name_de');
$results[] = migrate_add_column($pdo, 'pokemon',      'name_es', 'VARCHAR(100) NULL AFTER name_de');
$results[] = migrate_modify_column($pdo, 'users', 'preferred_language', "ENUM('fr','en','de','es') NOT NULL DEFAULT 'fr'");
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Migration DB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
<div class="card mx-auto" style="max-width:600px">
    <div class="card-header fw-bold">Migration — colonnes multilingues</div>
    <div class="card-body">
        <?php foreach ($results as [$status, $msg]): ?>
            <?php
                $cls  = match($status) { 'ok' => 'success', 'skip' => 'secondary', default => 'danger' };
                $icon = match($status) { 'ok' => '✔', 'skip' => '–', default => '❌' };
            ?>
            <div class="alert alert-<?= $cls ?> py-2 mb-2">
                <?= $icon ?> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endforeach; ?>
        <hr>
        <p class="text-muted small mb-0">✅ Migration terminée. <strong>Supprime ce fichier</strong> (<code>migrate.php</code>) dès que possible.</p>
    </div>
</div>
</body>
</html>
