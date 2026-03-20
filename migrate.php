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

$migrations = [
    "pokedex_list.name_en"       => "ALTER TABLE pokedex_list ADD COLUMN IF NOT EXISTS name_en VARCHAR(100) NULL AFTER name",
    "pokedex_list.name_de"       => "ALTER TABLE pokedex_list ADD COLUMN IF NOT EXISTS name_de VARCHAR(100) NULL AFTER name_en",
    "pokedex_list.name_es"       => "ALTER TABLE pokedex_list ADD COLUMN IF NOT EXISTS name_es VARCHAR(100) NULL AFTER name_de",
    "pokemon.name_es"            => "ALTER TABLE pokemon      ADD COLUMN IF NOT EXISTS name_es VARCHAR(100) NULL AFTER name_de",
    "users.preferred_language"   => "ALTER TABLE users MODIFY COLUMN preferred_language ENUM('fr','en','de','es') NOT NULL DEFAULT 'fr'",
];

$results = [];
foreach ($migrations as $label => $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ["ok", $label];
    } catch (PDOException $e) {
        $results[] = ["err", $label . " — " . $e->getMessage()];
    }
}
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
            <div class="alert alert-<?= $status === 'ok' ? 'success' : 'danger' ?> py-2 mb-2">
                <?= $status === 'ok' ? '✔' : '❌' ?> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endforeach; ?>
        <hr>
        <p class="text-muted small mb-0">✅ Migration terminée. <strong>Supprime ce fichier</strong> (<code>migrate.php</code>) dès que possible.</p>
    </div>
</div>
</body>
</html>
