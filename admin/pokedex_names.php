<?php
require_once __DIR__ . '/../users/admin_required.php';
require_once __DIR__ . '/../config/constantesPDO.php';

$config = ConstantesPDO::getInstance()->getConfig();
$dbCfg  = $config['db'] ?? null;
$pdo    = new PDO(
    "mysql:host={$dbCfg['host']};dbname={$dbCfg['dbname']};charset=utf8mb4",
    $dbCfg['username'], $dbCfg['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// S'assurer que les colonnes existent
try {
    $pdo->exec("ALTER TABLE pokedex_list
        ADD COLUMN IF NOT EXISTS name_en VARCHAR(100) NULL AFTER name,
        ADD COLUMN IF NOT EXISTS name_de VARCHAR(100) NULL AFTER name_en,
        ADD COLUMN IF NOT EXISTS name_es VARCHAR(100) NULL AFTER name_de");
} catch (PDOException $e) { /* colonnes déjà présentes */ }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $ids    = $_POST['id']      ?? [];
    $frs    = $_POST['name']    ?? [];
    $ens    = $_POST['name_en'] ?? [];
    $des    = $_POST['name_de'] ?? [];
    $ess    = $_POST['name_es'] ?? [];

    $stmt = $pdo->prepare("UPDATE pokedex_list SET name = ?, name_en = ?, name_de = ?, name_es = ? WHERE id = ?");
    foreach ($ids as $i => $id) {
        $stmt->execute([
            trim($frs[$i] ?? ''),
            trim($ens[$i] ?? '') ?: null,
            trim($des[$i] ?? '') ?: null,
            trim($ess[$i] ?? '') ?: null,
            (int)$id,
        ]);
    }
    $msg = 'Noms mis à jour.';
}

$dexList = $pdo->query("SELECT id, code, name, name_en, name_de, name_es FROM pokedex_list ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin – Noms des Pokédex</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .lang-badge { font-size:.7rem; font-weight:700; padding:2px 6px; border-radius:4px; }
        .lang-fr { background:#dbeafe; color:#1e40af; }
        .lang-en { background:#dcfce7; color:#166534; }
        .lang-de { background:#fef9c3; color:#854d0e; }
        .lang-es { background:#fee2e2; color:#991b1b; }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php"><img src="../img/logo_pokedex.png" alt="Mon Pokédex" style="height:72px;"></a>
        <span class="navbar-text text-white">Administration</span>
        <a class="btn btn-outline-light btn-sm" href="admin_import.php">← Import</a>
    </div>
</nav>

<div class="container" style="max-width:900px">
    <h1 class="mb-1">Noms des Pokédex</h1>
    <p class="text-muted mb-4">Gérez les noms en français, anglais et allemand pour chaque Pokédex.</p>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if (empty($dexList)): ?>
        <p class="text-muted">Aucun Pokédex dans la base de données.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="save" value="1">
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:80px">Code</th>
                            <th><span class="lang-badge lang-fr">FR</span> Nom français</th>
                            <th><span class="lang-badge lang-en">EN</span> English name</th>
                            <th><span class="lang-badge lang-de">DE</span> Deutscher Name</th>
                            <th><span class="lang-badge lang-es">ES</span> Nombre español</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dexList as $i => $dex): ?>
                        <input type="hidden" name="id[]" value="<?= (int)$dex['id'] ?>">
                        <tr>
                            <td class="align-middle fw-bold text-secondary"><?= htmlspecialchars($dex['code']) ?></td>
                            <td>
                                <input type="text" class="form-control form-control-sm" name="name[]"
                                       value="<?= htmlspecialchars($dex['name']) ?>" required maxlength="100">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm" name="name_en[]"
                                       value="<?= htmlspecialchars($dex['name_en'] ?? '') ?>"
                                       placeholder="English name…" maxlength="100">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm" name="name_de[]"
                                       value="<?= htmlspecialchars($dex['name_de'] ?? '') ?>"
                                       placeholder="Deutscher Name…" maxlength="100">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm" name="name_es[]"
                                       value="<?= htmlspecialchars($dex['name_es'] ?? '') ?>"
                                       placeholder="Nombre español…" maxlength="100">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-danger">Enregistrer</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
